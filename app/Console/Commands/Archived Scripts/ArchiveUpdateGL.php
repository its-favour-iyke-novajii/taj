<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

//use GuzzleHttp\Client as HttpClient;

class UpdateGL extends Command
{
    /**
     * The console command name.
     *
     * @var string
     *             /usr/bin/php /var/www/html/taj/artisan ctr:update-gl {date}
     *             nohup /usr/bin/php /var/www/html/taj/artisan ctr:update-gl 1 566 >> /dev/null 2&>1
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
        echo 'Updating GL..'.PHP_EOL;
        // all transactions last 2 hours
        $days = (int) $this->argument('days');
        $currency_code = $this->argument('currency_code');
        //echo $days;return;
        $days_before = (int) $days + 1;
        $conn = oci_pconnect('novaji', 'novali123', '172.19.20.60:1521/tajrep');
        try {
            // core connect
            $conn = $this->db_connect();
            // local connect
            $local = $this->db_connect('local');

            // $conn = oci_pconnect('novaji', 'novali123', '172.19.20.60:1521/tajrep');
            $sql = 'SELECT trim(b.ncp) account_number,nvl(b.txind,1) exchange_rate,trim(b.age) branch_code,trim(b.dev) currency_code,trim(b.dco) tran_date,'
            .'b.cha gl_code,b.mvtd debited,b.mvtc credited,nvl(b.sde,0) closing_bal,'
            ."(select trunc(sysdate) - $days_before from dual) prev_date,"
            .'(select trunc(sysdate)  from dual) cur_date,'
            ."(select upper(trim(lib2)) FROM tajprod.bknom where trim(ctab)='005'  and trim(cacc)=trim(b.dev)  AND ROWNUM = 1) currency_name, "
            .'(select upper(inti) FROM tajprod.bkcom  WHERE trim(ncp) = trim(b.ncp)  AND trim(age) = TRIM(b.age) AND trim(dev) = TRIM(b.dev) AND ROWNUM = 1) account_name, '
            .'(select nvl(sin,0) FROM tajprod.bkcom  WHERE trim(ncp) = trim(b.ncp)  AND trim(age) = TRIM(b.age) AND trim(dev) = TRIM(b.dev) AND ROWNUM = 1) bal, '
            ."(SELECT  nvl(sde, 0) FROM tajprod.bksld  WHERE trim(ncp) = trim(b.ncp) AND dco = trunc(sysdate) - $days_before AND trim(age) = TRIM(b.age) AND trim(dev) = TRIM(b.dev) AND ROWNUM = 1) opening_bal "
            ." FROM tajprod.bksld b WHERE b.dco = trunc(sysdate) - $days and trim(b.dev) = $currency_code ";
            $stid = oci_parse($conn, $sql);
            //echo $sql;return;
            oci_execute($stid);
            $cnt = 0;
            //core db
            while ($row = oci_fetch_assoc($stid)) {
                $cnt = $cnt + 1;
                echo 'Record No: '.$cnt.PHP_EOL;
                //echo 'Current Hour: '.$current_hour.PHP_EOL;
                echo $row['ACCOUNT_NUMBER'].PHP_EOL;
                echo $row['ACCOUNT_NAME'].PHP_EOL;
                echo $row['BRANCH_CODE'].PHP_EOL;
                echo $row['TRAN_DATE'].PHP_EOL;
                echo $row['OPENING_BAL'].PHP_EOL;
                echo $row['CLOSING_BAL'].PHP_EOL;
                echo $row['CURRENCY_NAME'].PHP_EOL;
                echo $row['PREV_DATE'].PHP_EOL;
                echo $row['CUR_DATE'].PHP_EOL;
                $commit = '';
                // commit after every 200 inserts, free up logs and lock resources
                if ($cnt % 200 == 0) {
                    echo 'Threshold reached.. will commit '.PHP_EOL;
                    $commit = ' commit; ';
                }
                $bind_vars = [
                    'account_number' => $row['ACCOUNT_NUMBER'],
                    'account_name' => $row['ACCOUNT_NAME'],
                    'branch_code' => $row['BRANCH_CODE'],
                    'tran_date' => $row['TRAN_DATE'],
                    'opening_bal' => $row['OPENING_BAL'],
                    'closing_bal' => $row['CLOSING_BAL'],
                    'prev_date' => $row['PREV_DATE'],
                    'currency_name' => $row['CURRENCY_NAME'],
                    'gl_code' => $row['GL_CODE'],
                    'debited' => $row['DEBITED'],
                    'exchange_rate' => $row['EXCHANGE_RATE'],
                    'credited' => $row['CREDITED'],
                    'bal' => $row['BAL'],
                ];
                $this->db_execute($local, "begin insert into ctl_general_ledger_hist (
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
                    nvl(:opening_bal, 0),
                    nvl(:closing_bal, 0),
                    nvl(:bal, 0),
                    :tran_date,
                    :prev_date,
                    nvl(:exchange_rate,1),
                    nvl(:debited, 0), 
                    nvl(:credited, 0)
                ); $commit exception when others then null; end;", $bind_vars);

                echo '1 row inserted '.PHP_EOL;
            }
        } catch (\Throwable $e) {
            echo $e->getMessage();
        }
    }

    //oracle db connection
    private function db_connect($tag = null)
    {
        // avoid harcoding, things could change
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
            //var_dump($e);
            exit(json_encode(['status' => 'error', 'statuscode' => "$port", 'message' => 'Internal System Error']));
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
