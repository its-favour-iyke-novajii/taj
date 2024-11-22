<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

//use GuzzleHttp\Client as HttpClient;

class UpdatePEP extends Command
{
    /**
     * The console command name.
     *
     * @var string
     *             /usr/bin/php /var/www/html/taj/artisan icomply:update-pep {date}
     *             nohup /usr/bin/php /var/www/html/taj/artisan icomply:update-pep 10 >> /dev/null 2&>1
     */
    protected $signature = 'icomply:update-pep {days}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Use update PEP registry';

    public function handle()
    {
        set_time_limit(0);
        echo 'Updating PEP Registry..'.PHP_EOL;
        // all transactions last 2 hours
        $days = (int) $this->argument('days');
        try {
            // core connect
            //$conn = $this->db_connect();
            // local connect
            $username = trim(env('CBA_USERNAME'));
            $passwd = trim(env('CBA_PASSWORD'));
            $db = trim(env('CBA_DB'));
            $host = trim(env('CBA_HOST'));
            $port = trim(env('CBA_PORT'));
            $connect_string = trim("$host:$port/$db");
            //echo "$username,$passwd,$connect_string".PHP_EOL;
            //dd();
            $conn = oci_pconnect($username, $passwd, $connect_string);
            $app_conn = $this->db_connect('local');
            $sql = "select trim(a.age) branch_code, trim(a.cli) customer_id,upper(a.nomrest) customer_name,trim(c.ncp) account_number,c.dou date_opened
            from tajprod.bkcli a, tajprod.bkicli b, tajprod.bkcom c
            where a.cli = b.cli and a.cli = c.cli and b.cli =c.cli  and ((b.iden='0000000014' and b.vala='O') or (b.iden='0000000015' and b.vala='O'))
            and c.cfe='N' and a.dou >= trunc(sysdate) - $days ";
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
                echo $row['CUSTOMER_NAME'].PHP_EOL;
                echo $row['BRANCH_CODE'].PHP_EOL;
                echo $row['DATE_OPENED'].PHP_EOL;
                echo $row['ACCOUNT_NUMBER'].PHP_EOL;
                $bind_vars = [
                    'customer_id' => $row['CUSTOMER_ID'],
                    'customer_name' => $row['CUSTOMER_NAME'],
                    'branch_code' => $row['BRANCH_CODE'],
                    'date_opened' => $row['DATE_OPENED'],
                    'account_number' => $row['ACCOUNT_NUMBER'],
                ];

                $this->db_execute($app_conn, 'begin INSERT INTO mt_pep (
                    branch_code,customer_id,customer_name,date_opened,account_number
                ) VALUES (
                    TRIM(:branch_code),
                    TRIM(:customer_id),
                    :customer_name,
                    :date_opened,
                    trim(:account_number)
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
            $host = env('APP_HOST');
            $user = env('APP_USERNAME');
            $pwd = env('APP_PASSWORD');
            $port = env('APP_PORT');
            $db = env('APP_DB');
            $conn = oci_pconnect($user, $pwd, "$host:$port/$db");
        } else {
            $username = trim(env('CBA_USERNAME'));
            $passwd = trim(env('CBA_PASSWORD'));
            $db = trim(env('CBA_DB'));
            $host = trim(env('CBA_HOST'));
            $port = trim(env('CBA_PORT'));
            $connect_string = trim("$host:$port/$db");
            $conn = oci_pconnect($username, $passwd, $connect_string);
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
