<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PDO;
use PDOException;

class FlagSTR extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'flag:str';

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

                $rule_id = $row['ID'];

               // $stid1 = oci_parse($Lconn, $dbsql);


               // oci_execute($stid1);

                $stmt1 = $pgsqlConn->prepare($dbsql);
                $stmt1->execute();


                echo "\n";

                while ($dbrow = $stmt1->fetch(PDO::FETCH_ASSOC)) {

                    var_dump($dbrow);
                 // Prepare bind variables

                    $accountNumber = $dbrow['destinationaccountno'];
                    $accountName   = $dbrow['destinationaccountname'];

                //Check for Transaction Type, the transaction Type will determine the refrence account

                if($dbrow['trantype'] = 'OSC'){
                    $accountNumber = $dbrow['originatoraccountno'];
                    $accountName   = $dbrow['originatorname'];
                }

                var_dump($dbrow);
                
                //else{
                //    $accountNumber = 'NA';
                //    $accountName   = 'NA';
               // }


                 $bind_vars = [
                    'AMOUNT' => $dbrow['amount'], // Adjust other keys similarly if needed
                    'TRANTYPE' => $dbrow['trantype'],
                    'NARRATION' => $dbrow['narration'],
                    'RECORDDATE' => $dbrow['recorddate'],
                    'ACCOUNTNAME' =>  $accountName,
                    'BENEFICIARYBVN' => $dbrow['beneficiarybvn'],
                    'ORIGINATORNAME' => $dbrow['originatorname'],
                    'SENDERBANKCODE' => $dbrow['senderbankcode'],
                    'SOURCEACCOUNTNO' => $dbrow['originatoraccountno'],
                    'DESTINATIONBANKCODE' => $dbrow['destinationbankcode'],
                    'DESTINATIONACCOUNTNO' => $dbrow['destinationaccountno'],
                    'RULE_ID' => $rule_id,
                    'T_TRANS_NUMBER' => $dbrow['rowid'],
                    'T_ACCOUNT_NUMBER' => $accountNumber,
                ];
    

                    // Insert into PostgreSQL
                    $this->insert_str($pgsqlConn,  $bind_vars);
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
            $stmt = $pgsqlConn->prepare("INSERT INTO str (
                t_amount_local,
                tran_type,
                transaction_description,
                t_value_date,
                t_dest_account_name,
                t_client_number,
                t_source_account_name,
                t_source_institution_code,
                t_source_account_number,
                t_dest_institution_code,
                t_dest_account_number,
                rule_id,
                t_trans_number,
                t_account_number,
                status

            ) VALUES (
                :AMOUNT,
                :TRANTYPE,
                :NARRATION,
                to_timestamp(:RECORDDATE, 'YYYY-MM-DD HH24:MI:SS.FF3'),
                :ACCOUNTNAME,
                :BENEFICIARYBVN,
                :ORIGINATORNAME,
                :SENDERBANKCODE,
                :SOURCEACCOUNTNO,
                :DESTINATIONBANKCODE,
                :DESTINATIONACCOUNTNO,
                :RULE_ID,
                :T_TRANS_NUMBER,
                :T_ACCOUNT_NUMBER,
                '1'

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
