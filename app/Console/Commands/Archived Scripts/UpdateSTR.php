<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PDO;
use PDOException;

class UpdateSTR extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'str:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Use php command line update STR table';

    public function handle()
    {
        putenv('LD_LIBRARY_PATH=/usr/lib/oracle/19.5/client64/lib:/lib:/usr/lib');
        set_time_limit(0);
        echo 'Running STR Update..' . PHP_EOL;
        
        // Assuming the PostgreSQL database connection details
        $host = "172.19.2.86";
        $port = "5432";
        $dbname = "tajbank";
        $username = "postgres";
        $password = "Tajbank123_";

        try {
            // Connect to PostgreSQL
            $pgsqlConn = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $username, $password);
            $pgsqlConn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // All transactions last 2 hours
            $current_hour = date('H') - 2;
            $transaction_limit = 5000000;
            
            // Connect to Oracle
            $Lconn = $this->db_connect('local');

            $sql = "SELECT * FROM str_rules WHERE LOWER(status) = 'active' AND TRIM(sql_query) IS NOT NULL";

            $stid = oci_parse($Lconn, $sql);
            oci_execute($stid);
            echo "\n";

            $cnt = 0;

            while ($row = oci_fetch_assoc($stid)) {
                echo 'Running query..' . PHP_EOL;
                $cnt++;

                echo 'Status: ' . $row['STATUS'] . PHP_EOL;
                echo 'SQL Query: ' . $row['SQL_QUERY'] . PHP_EOL;

                $dbsql = $row['SQL_QUERY'];

               // $stid1 = oci_parse($Lconn, $dbsql);


               // oci_execute($stid1);

                $stmt1 = $pgsqlConn->prepare($dbsql);
                $stmt1->execute();


                echo "\n";

                while ($dbrow = $stmt1->fetch(PDO::FETCH_ASSOC)) {
                $bind = [
                        'CUST_NAME' => $dbrow['CUST_NAME'],
                        'TXN_ROWID' => $dbrow['ROW_ID'],
                        'RULE_ID' => $row['ID'],
                        'ACCOUNT_NUMBER' => $dbrow['ACCOUNT_NUMBER'],
                        'TXN_CURRENCY' => $dbrow['TXN_CURRENCY'],
                        'AMOUNT' => $dbrow['LOCAL_AMOUNT'],
                        'CREATION_DATE' => date('m/d/Y H:i:s', strtotime($dbrow['CREATION_DATE'])),
                        'EVENT_NUMBER' => $dbrow['EVENT_NUMBER'],
                        'STATUS' => '1',
                        'BRANCH_CODE' => $dbrow['BRANCH_CODE'],
                    ];

                    // Insert into PostgreSQL
                    $this->insert_str($pgsqlConn, $bind);
                }
            }
        } catch (\Throwable $e) {
            echo $e->getMessage();
        }
        echo 'Done!' . PHP_EOL;
    }

    private function db_connect($tag = null)
    {
        $host = '172.19.20.60';
        $user = 'novaji';
        $pwd = 'novali123';
        $port = '1521';
        $conn = oci_connect($user, $pwd, "172.19.20.60:$port/tajrep");

        if ($tag == 'local') {
            $host = '172.19.2.86';
            $user = 'tajbank';
            $pwd = 'Tajbank123_';
            $port = '1522';
            $conn = oci_connect($user, $pwd, "172.19.2.86:$port/xepdb1");
        }

        if (!$conn) {
            $e = oci_error();
            exit(json_encode(['status' => 'error', 'statuscode' => "$port", 'message' => 'Internal System Error']));
        }

        return $conn;
    }

    private function insert_str($pgsqlConn, $bind)
    {
        try {
            // Insert into PostgreSQL database
            $stmt = $pgsqlConn->prepare("INSERT INTO str_transactions (
                CUST_NAME,
                TXN_ROWID,
                RULE_ID,
                ACCOUNT_NUMBER,
                TXN_CURRENCY,
                AMOUNT,
                CREATION_DATE,
                EVENT_NUMBER,
                STATUS,
                BRANCH_CODE
            ) VALUES (
                :CUST_NAME,
                :TXN_ROWID,
                :RULE_ID,
                :ACCOUNT_NUMBER,
                :TXN_CURRENCY,
                :AMOUNT,
                TO_TIMESTAMP(:CREATION_DATE, 'MM/DD/YYYY HH24:MI:SS'),
                :EVENT_NUMBER,
                :STATUS,
                :BRANCH_CODE
            )");

            // Bind parameters
            foreach ($bind as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }

            // Execute the query
            $stmt->execute();
        } catch (PDOException $e) {
            echo $e->getMessage();
        }
    }
}
