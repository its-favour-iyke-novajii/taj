<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use PDO;
use PDOException;

class UpdateGL extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'ctr:update-gl {days} {currency_code}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Use php command line update GL';

    public function handle()
    {
        set_time_limit(0);
        echo 'Updating GL..' . PHP_EOL;
        // all transactions last 2 hours
        $days = (int)$this->argument('days');
        $currency_code = $this->argument('currency_code');
        $days_before = (int)$days + 1;

        $conn = oci_pconnect('novaji', 'novali123', '172.19.20.60:1521/tajrep');

        try {
            // core connect
            $conn = $this->db_connect();
            // local connect
            $local = $this->db_connect('local');

            $sql = 'SELECT trim(b.ncp) account_number, nvl(b.txind, 1) exchange_rate, trim(b.age) branch_code, trim(b.dev) currency_code, trim(b.dco) tran_date, '
                . 'b.cha gl_code, b.mvtd debited, b.mvtc credited, nvl(b.sde, 0) closing_bal, '
                . "(select trunc(sysdate) - $days_before from dual) prev_date, "
                . '(select trunc(sysdate)  from dual) cur_date, '
                . "(select upper(trim(lib2)) FROM tajprod.bknom where trim(ctab)='005'  and trim(cacc)=trim(b.dev)  AND ROWNUM = 1) currency_name, "
                . '(select upper(inti) FROM tajprod.bkcom  WHERE trim(ncp) = trim(b.ncp)  AND trim(age) = TRIM(b.age) AND trim(dev) = TRIM(b.dev) AND ROWNUM = 1) account_name, '
                . '(select nvl(sin, 0) FROM tajprod.bkcom  WHERE trim(ncp) = trim(b.ncp)  AND trim(age) = TRIM(b.age) AND trim(dev) = TRIM(b.dev) AND ROWNUM = 1) bal, '
                . "(SELECT  nvl(sde, 0) FROM tajprod.bksld  WHERE trim(ncp) = trim(b.ncp) AND dco = trunc(sysdate) - $days_before AND trim(age) = TRIM(b.age) AND trim(dev) = TRIM(b.dev) AND ROWNUM = 1) opening_bal "
                . " FROM tajprod.bksld b WHERE b.dco = trunc(sysdate) - $days and trim(b.dev) = $currency_code ";

            $stid = oci_parse($conn, $sql);
            oci_execute($stid);
            $cnt = 0;

            // core db
            while ($row = oci_fetch_assoc($stid)) {
                $cnt = $cnt + 1;
                echo 'Record No: ' . $cnt . PHP_EOL;
                echo $row['ACCOUNT_NUMBER'] . PHP_EOL;
                echo $row['ACCOUNT_NAME'] . PHP_EOL;
                echo $row['BRANCH_CODE'] . PHP_EOL;
                echo $row['TRAN_DATE'] . PHP_EOL;
                echo $row['OPENING_BAL'] . PHP_EOL;
                echo $row['CLOSING_BAL'] . PHP_EOL;
                echo $row['CURRENCY_NAME'] . PHP_EOL;
                echo $row['PREV_DATE'] . PHP_EOL;
                echo $row['CUR_DATE'] . PHP_EOL;

                $exchange_rate = $row['EXCHANGE_RATE']; // Convert to string (varchar)
            echo "Exchange Rate: $exchange_rate" . PHP_EOL; //

                $commit = '';
                // commit after every 200 inserts, free up logs and lock resources
                if ($cnt % 200 == 0) {
                    echo 'Threshold reached.. will commit ' . PHP_EOL;
                    $commit = ' COMMIT; ';
                }

                $bind_vars = [
                    'account_number' => $row['ACCOUNT_NUMBER'],
                    'account_name' => $row['ACCOUNT_NAME'],
                    'branch_code' => $row['BRANCH_CODE'],
                    'tran_date' => $row['TRAN_DATE'],
                    'opening_bal' => ($row['OPENING_BAL'] !== null) ? $row['OPENING_BAL'] : 0,
                    'closing_bal' => ($row['CLOSING_BAL'] !== null) ? $row['CLOSING_BAL'] : 0,
                    'prev_date' => $row['PREV_DATE'],
                    'currency_name' => $row['CURRENCY_NAME'],
                    'gl_code' => $row['GL_CODE'],
                    'exchange_rate' => ($row['EXCHANGE_RATE'] !== null) ? $row['EXCHANGE_RATE'] : 0,
                    'debited' => ($row['DEBITED'] !== null) ? $row['DEBITED'] : 0,
                    'credited' => ($row['CREDITED'] !== null) ? $row['CREDITED'] : 0,
                    'bal' => ($row['BAL'] !== null) ? $row['BAL'] : 0,
                  
                ];

                // Establish a connection to PostgreSQL
                try {
                    $dsn = "pgsql:host=127.0.0.1;dbname=tajbank";
                    $username = "postgres";
                    $password = "Tajbank123_";

                    $p_conn = new PDO($dsn, $username, $password);
                    $p_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    // Now you can call the pg_db_execute function with the $p_conn object
                    $this->pg_db_execute($p_conn, "INSERT INTO ctl_general_ledger_hist (
                        branch_code,
                        account_number,
                        gl_code,
                        currency_code,
                        account_name,
                        prev_bal,
                        cur_bal,
                        bal,
                        cur_date,
                        prev_date,
                        exchange_rate,
                        debited,
                        credited
                    ) VALUES (
                        TRIM(:branch_code),
                        TRIM(:account_number),
                        TRIM(:gl_code),
                        :currency_name,
                        :account_name,
                        :opening_bal,
                        :closing_bal,
                        :bal,
                        :tran_date,
                        :prev_date,
                        :exchange_rate::numeric,
                        :debited,
                        :credited
                    );" . $commit, $bind_vars);

                    echo '1 row inserted ' . PHP_EOL;
                } catch (PDOException $e) {
                    echo "Connection failed: " . $e->getMessage();
                }
            }
        } catch (\Throwable $e) {
            echo $e->getMessage();
        }
    }

    // Oracle db connection
    private function db_connect($tag = null)
    {
        // avoid hardcoding, things could change
        $host = '172.19.20.60';
        $user = 'novaji';
        $pwd = 'novali123';
        $port = '1521';
        $desc = "(DESCRIPTION =(ADDRESS = (PROTOCOL = TCP)(HOST = $host)(PORT = $port))(CONNECT_DATA =(SERVER = DEDICATED)(SERVICE_NAME=tajrep)))";
        $conn = oci_pconnect($user, $pwd, "172.19.20.60:$port/tajrep");

        if ($tag == 'local') {
            $host = '172.19.2.86';
            $user = 'tajbank';
            $pwd = 'Tajbank123_';
            $port = '1522';
            $desc = "(DESCRIPTION =(ADDRESS = (PROTOCOL = TCP)(HOST = $host)(PORT = $port))(CONNECT_DATA =(SERVER = DEDICATED)(SERVICE_NAME=xepdb1)))";
            $conn = oci_pconnect($user, $pwd, "172.19.2.86:$port/xepdb1");
        }

        if (!$conn) {
            $e = oci_error();
            exit(json_encode(['status' => 'error', 'statuscode' => "$port", 'message' => 'Internal System Error']));
        }

        return $conn;
    }

    /*
     * Note in Oracle, variables returned to PHP are capitalized
     */
    private function db_execute($conn, $sql, $bind = null)
    {
        $stmt = oci_parse($conn, $sql);
        if ($bind) {
            foreach ($bind as $key => $value) {
                @oci_bind_by_name($stmt, $key, htmlentities($value, ENT_QUOTES));
            }
        }

        $data = oci_execute($stmt);
        oci_close($conn);

        return $data;
    }

    private function pg_db_execute($conn, $sql, $bind = null)
    {
        $stmt = $conn->prepare($sql);

        if ($bind) {
            foreach ($bind as $key => $value) {
                $stmt->bindValue(':' . $key, htmlspecialchars($value, ENT_QUOTES));
            }
        }

        $data = $stmt->execute();
        $stmt->closeCursor();

        return $data;
    }
}
