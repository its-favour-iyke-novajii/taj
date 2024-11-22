<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

//use GuzzleHttp\Client as HttpClient;

class UpdateAccounts extends Command
{
    /**
     * The console command name.
     *
     * @var string
     *             /usr/bin/php /var/www/html/taj/artisan icomply:update-accounts {date}
     *             nohup /usr/bin/php /var/www/html/taj/artisan icomply:update-accounts 10 >> /dev/null 2&>1
     */
    protected $signature = 'icomply:gather-customer-stats';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gather Customer Stats';

    public function handle()
    {
        set_time_limit(0);
        echo 'Gathering Customer Stats..'.PHP_EOL;
        // all transactions last 2 hours
        $days = (int) $this->argument('days');
        try {
            // core connect
            //$conn = $this->db_connect();
            // local connect

            //$conn = oci_pconnect('novaji', 'novali123', '172.19.20.60:1521/tajrep');
            $conn = oci_pconnect(env('CBA_USERNAME'), env('CBA_PASSWORD'), env('CBA_DB'));
            $app_conn = $this->db_connect('local');
            $sql = "select ncp account_number,age branch_code,dev currency_code,cha gl_code,cli customer_id,upper(inti) name,dou tran_date from tajprod.bkcom where dou >= trunc(sysdate) - $days";
            $stid = oci_parse($conn, $sql);
            //echo $sql;return;
            oci_execute($stid);
            $cnt = 0;
            //core db
            while ($row = oci_fetch_assoc($stid)) {
                $cnt = $cnt + 1;
                echo 'Record: '.$cnt.PHP_EOL;
                //echo 'Current Hour: '.$current_hour.PHP_EOL;
                echo $row['CUSTOMER_ID'].PHP_EOL;
                echo $row['ACCOUNT_NUMBER'].PHP_EOL;
                echo $row['NAME'].PHP_EOL;
                echo $row['BRANCH_CODE'].PHP_EOL;
                echo $row['TRAN_DATE'].PHP_EOL;
                echo $row['CURRENCY_CODE'].PHP_EOL;
                echo $row['GL_CODE'].PHP_EOL;
                $bind_vars = [
                    'customer_id' => $row['CUSTOMER_ID'],
                    'name' => $row['NAME'],
                    'branch_code' => $row['BRANCH_CODE'],
                    'tran_date' => $row['TRAN_DATE'],
                    'account_number' => $row['ACCOUNT_NUMBER'],
                    'gl_code' => $row['GL_CODE'],
                    'currency_code' => $row['CURRENCY_CODE'],
                ];
                echo '---------- Updating Registry-----------'.PHP_EOL;
                $this->db_execute($app_conn, 'begin 
                insert into ctl_account(branch_code,customer_id,name,date_created,account_number,gl_code,currency_code)
                VALUES (
                    TRIM(:branch_code),
                    TRIM(:customer_id),
                    upper(:name),
                    :tran_date,
                    trim(:account_number),
                    trim(:gl_code),
                    trim(:currency_code)
                ); exception when others then null; end;', $bind_vars);
                echo '----------Accounts Registry Updated-----------'.PHP_EOL;
            }
        } catch (\Throwable $e) {
            echo $e->getMessage();
        }
    }

    //oracle db connection
    private function db_connect($tag = null)
    {
        if ($tag == 'local') {
            $host = '172.19.2.86';
            $user = 'tajbank';
            $pwd = 'Tajbank123_';
            $port = '1522';
            $desc = "(DESCRIPTION =(ADDRESS = (PROTOCOL = TCP)(HOST = $host)(PORT = $port))(CONNECT_DATA =(SERVER = DEDICATED)(SERVICE_NAME=xepdb1)))";
            $conn = oci_pconnect($user, $pwd, "172.19.2.86:$port/xepdb1");
        } else {
            // avoid harcoding, things could change
            $host = env('CBA_DB');
            $user = env('CBA_USERNAME');
            $pwd = env('CBA_PASSWORD');
            //$desc = "(DESCRIPTION =(ADDRESS = (PROTOCOL = TCP)(HOST = $host)(PORT = $port))(CONNECT_DATA =(SERVER = DEDICATED)(SERVICE_NAME=tajrep)))";
            $conn = oci_pconnect($user, $pwd, $host);
        }

        if (!$conn) {
            $e = oci_error();
            var_dump($e);
            //exit(json_encode(['status' => 'error', 'statuscode' => "$port", 'message' => 'Internal System Error']));
            //trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
        }

        return $conn;
    }

    private function db_query($conn, $sql, $bind = null)
    {
        $stmt = oci_parse($conn, $sql);
        if ($bind) {
            foreach ($bind as $key => $value) {
                oci_bind_by_name($stmt, $key, $value);
            }
        }
        oci_execute($stmt);
        oci_fetch_all($stmt, $res, null, null, OCI_FETCHSTATEMENT_BY_ROW);
        oci_close($conn);

        return $res;
    }

    private function db_query_one($conn, $sql, $bind = null)
    {
        $stmt = oci_parse($conn, $sql);
        if ($bind) {
            foreach ($bind as $key => $value) {
                oci_bind_by_name($stmt, $key, $value);
            }
        }
        oci_execute($stmt);
        $data = oci_fetch_assoc($stmt);
        oci_close($conn);

        return $data;
        //oci_free_statement($s);
    }

    /*
     * do insert, update and delete using bind variables.
     * bind variables better for performance
     * $bind = array(
      'ID' => $id,
      'MSISDN' => $msisdn,
      'NAME' => $name,
      'AGE' => $age,
      'SEX' => $sex,
      'LOC' => $location,
      'CHANNEL' => $client_id
      );
      $res = db_execute("insert into vs_subscriber(id,msisdn,name,age,location,sex,vs_channel_id)
      values( :id , :msisdn , :name , :age , :loc , :sex , :channel )", $bind);
      return $id;
     * note in oracle variables returned to php are capitalized
     *
     */
    private function db_execute($conn, $sql, $bind = null)
    {
        $stmt = oci_parse($conn, $sql);
        if ($bind) {
            foreach ($bind as $key => $value) {
                @oci_bind_by_name($stmt, $key, htmlentities($value, ENT_QUOTES));
            }
        }
        //    echo $sql; exit;
        $data = oci_execute($stmt);
        oci_close($conn);

        return $data;
        //oci_free_statement($s);
       //oci_close($c);
    }
}
