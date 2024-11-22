<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

//use GuzzleHttp\Client as HttpClient;

class UpdateCustomers extends Command
{
    /**
     * The console command name.
     *
     * @var string
     *             /usr/bin/php /var/www/html/taj/artisan icomply:update-customers {date}
     *             nohup /usr/bin/php /var/www/html/taj/artisan icomply:update-customers 10 >> /dev/null 2&>1
     */
    protected $signature = 'icomply:update-customers {days}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Use update customer registry';

    public function handle()
    {
        set_time_limit(0);
        echo 'Updating Customer Registry..'.PHP_EOL;
        // all transactions last 2 hours
        $days = (int) $this->argument('days');
        try {
            // core connect
            //$conn = $this->db_connect();
            // local connect

            $conn = oci_pconnect('novaji', 'novali123', '172.19.20.60:1521/tajrep');
            $app_conn = $this->db_connect('local');
            $sql = "select age branch_code,cli customer_id,upper(nomrest) name,dou tran_date,tcli tier_level,rrc risk_rating,
            ges account_officer_id,met sector_id,tid id_type,nid id_number,vid id_expiry_date  
            from tajprod.bkcli where dou >= trunc(sysdate) - $days ";
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
                echo $row['NAME'].PHP_EOL;
                echo $row['BRANCH_CODE'].PHP_EOL;
                echo $row['TRAN_DATE'].PHP_EOL;
                echo $row['TIER_LEVEL'].PHP_EOL;
                echo $row['RISK_RATING'].PHP_EOL;
                $bind_vars = [
                    'customer_id' => $row['CUSTOMER_ID'],
                    'name' => $row['NAME'],
                    'branch_code' => $row['BRANCH_CODE'],
                    'tran_date' => $row['TRAN_DATE'],
                    'tier_level' => $row['TIER_LEVEL'],
                    'risk_rating' => $row['RISK_RATING'],
                    'account_officer_id' => $row['ACCOUNT_OFFICER_ID'],
                    'sector_id' => $row['SECTOR_ID'],
                    'id_type' => $row['ID_TYPE'],
                    'id_number' => $row['ID_NUMBER'],
                    'id_expiry_date' => $row['ID_EXPIRY_DATE'],
                ];

                $this->db_execute($app_conn, 'begin INSERT INTO ctl_customer (
                    branch_code,customer_id,name,date_created,tier_level,risk_rating,
                    account_officer_id,sector_id,id_type,id_number,id_expiry_date
                ) VALUES (
                    TRIM(:branch_code),
                    TRIM(:customer_id),
                    :name,
                    :tran_date,
                    :tier_level,
                    :risk_rating,
                    trim(:account_officer_id),
                    trim(:sector_id),
                    trim(:id_type),
                    trim(:id_number),
                    :id_expiry_date
                ); exception when others then null; end;', $bind_vars);

                echo '----------Registry Updated-----------'.PHP_EOL;
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
