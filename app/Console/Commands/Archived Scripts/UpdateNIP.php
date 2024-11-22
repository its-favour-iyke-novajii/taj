<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Throwable;

use PDO;
use PDOException;

//use GuzzleHttp\Client as HttpClient;

class UpdateNIP extends Command
{
    /**
     * The console command name.
     *
     * @var string
     *             /usr/bin/php /var/www/html/taj/artisan ctr:update
     */
    protected $signature = 'ctr:update-nip';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Use php command line update CTR table';

    public function handle()
    {
        //putenv('LD_LIBRARY_PATH=/usr/lib/oracle/19.5/client64/lib:/lib:/usr/lib');
        // echo oci_client_version(); exit;
        set_time_limit(0);
        echo 'Running CTR Update for NIP..'.PHP_EOL;
        // all transactions last 2 hours
        /*
         Get Forex transation above 10,000
         and Local Transaction above N5m ( individual) or N10m (COrporate)

        */
        //postgres database connection
        $dsn = "pgsql:host=127.0.0.1;dbname=tajbank";
        $username = "postgres";
        $password = "Tajbank123_";

        $p_conn = new PDO($dsn, $username, $password);
        $p_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


        $local_limit = 5000000;
        $foreign_limit = 10000;
        //$conn = $this->db_connect('local');
        $conn = oci_connect('novaji', 'novali123', '172.19.20.60:1521/tajrep');
        // local connect
        $Lconn = $this->db_connect('local');

        echo 'Processing NIP Buffers..'.PHP_EOL;
        try {
            echo 'Processing Inward Buffers..'.PHP_EOL;
            $inward = "SELECT * FROM NIP_INWARD_BUFFER a where STATUS='S' AND AMOUNT >= $local_limit AND trunc(recorddate) >= ( trunc(systimestamp) - interval '2' day)";
            $stid2 = oci_parse($Lconn, $inward);

            oci_execute($stid2);

            $cnt = 0;
            //inward
            while ($row = oci_fetch_assoc($stid2)) {
                echo 'Row ID: '.$row['SOPRATRANID'].PHP_EOL;
                echo 'Record Date: '.$row['RECORDDATE'].PHP_EOL;
                echo 'Amount: '.$row['AMOUNT'].PHP_EOL;
                echo 'Account No: '.$row['DESTINATIONACCOUNTNO'].PHP_EOL;

                $tran_type = 'INWARD_BUFFER';
                $transmode_code = 'C';
                $funds_code = 'B';
                $data = $this->customer_details($conn, $row['DESTINATIONACCOUNTNO'], '566');
                $currency_name = $this->getCurrency($conn, '566');
                $bank_name = $this->getBankName($p_conn, $row['SENDERBANKCODE']);
                /*
                echo 'Customer Data: '.PHP_EOL;
                var_dump($data);
                //dd();

                echo 'Currency..'.PHP_EOL;
                var_dump($currency_name);
                //dd();
                // var_dump($tran_type,$data,$currency_name); exit;
*/
                if (!empty($data) && (!empty($currency_name))) {
                    echo 'Preparing data bindings..'.PHP_EOL;
                    $posted1 = explode(' ', $row['RECORDDATE']);
                    $dob1 = explode(' ', $data['DOB']);
                    $dev1 = explode(' ', $data['ID_DELIVERY_DATE']);
                    $reg1 = explode(' ', $data['REG_DATE']);

                    $bind = [
                        'T_ACCOUNT_NUMBER' => $row['DESTINATIONACCOUNTNO'],
                        'T_TRANS_NUMBER' => $row['SOPRATRANID'],
                        'T_LOCATION' => 'HEAD OFFICE',
                        'TRANSACTION_DESCRIPTION' => $row['NARRATION'],
                        'T_DATE' => date('d/m/Y H:i:s', strtotime($posted1[0])),
                        'T_TELLER' => 'SYSTEM',
                        'T_AUTHORIZED' => 'SYSTEM',
                        'T_LATE_DEPOSIT' => 0,
                        'T_DATE_POSTING' => date('d/m/Y H:i:s', strtotime($posted1[0])),
                        'T_VALUE_DATE' => date('d/m/Y H:i:s', strtotime($posted1[0])),
                        'T_TRANSMODE_CODE' => $transmode_code,
                        'T_AMOUNT_LOCAL' => $row['AMOUNT'],
                        'T_SOURCE_CLIENT_TYPE' => '',
                        'T_SOURCE_TYPE' => '',
                        'T_SOURCE_FUNDS_CODE' => $funds_code,
                        'T_SOURCE_CURRENCY_CODE' => $currency_name['CURRENCY_NAME'],
                        'T_SOURCE_FOREIGN_AMOUNT' => '',
                        'T_SOURCE_EXCHANGE_RATE' => '',
                        'T_SOURCE_COUNTRY' => 'NG',
                        'T_SOURCE_INSTITUTION_CODE' => $row['SENDERBANKCODE'],
                        'T_SOURCE_INSTITUTION_NAME' => $bank_name,
                        'T_SOURCE_ACCOUNT_NUMBER' => $row['ORIGINATORACCOUNTNO'],
                        'T_SOURCE_ACCOUNT_NAME' => $row['ORIGINATORNAME'],
                        'T_SOURCE_PERSON_FIRST_NAME' => '',
                        'T_SOURCE_PERSON_LAST_NAME' => '',
                        'T_SOURCE_ENTITY_NAME' => '',
                        'T_DEST_CLIENT_TYPE' => '',
                        'T_DEST_TYPE' => '',
                        'T_DEST_FUNDS_CODE' => $funds_code,
                        'T_DEST_CURRENCY_CODE' => $currency_name['CURRENCY_NAME'],
                        'T_DEST_FOREIGN_AMOUNT' => '',
                        'T_DEST_EXCHANGE_RATE' => '',
                        'T_DEST_COUNTRY' => 'NG',
                        'T_DEST_INSTITUTION_CODE' => $row['DESTINATIONBANKCODE'],
                        'T_DEST_INSTITUTION_NAME' => 'TAJ BANK',
                        'T_DEST_ACCOUNT_NUMBER' => $row['DESTINATIONACCOUNTNO'],
                        'T_DEST_ACCOUNT_NAME' => $row['ACCOUNTNAME'],
                        'T_DEST_PERSON_FIRST_NAME' => '',
                        'T_DEST_PERSON_LAST_NAME' => '',
                        'T_DEST_ENTITY_NAME' => '',
                        'TRAN_TYPE' => $tran_type,
                        'T_CLIENT_NUMBER' => $data['BVN'],
                        'T_GENDER' => $data['SEX'],
                        'T_TITLE' => $data['TITLE'],
                        'T_FIRSTNAME' => $data['FIRSTNAME'],
                        'T_LASTNAME' => $data['LASTNAME'],
                        'T_DOB' => date('d/m/Y H:i:s', strtotime($dob1[0])),
                        'T_PHONE' => $data['PHONE_NUMBER'],
                        'T_ADDRESS' => $data['ADDRESS'],
                        'T_CITY' => $data['TOWN'],
                        'T_STATE' => $data['STATE'],
                        'T_IDNUMBER' => $data['ID_NUMBER'],
                        'T_IDREGDATE' => date('d/m/Y H:i:s', strtotime($dev1[0])),
                        'T_TAXNO' => $data['TAX_ID'],
                        'T_TAXREGDATE' => $data['TAX_ID'],
                        'T_ACCTOPNDATE' => date('d/m/Y H:i:s', strtotime($reg1[0])),
                        'T_BALANCE' => $data['ACCOUNT_BALANCE'],
                        'ROW_REF' => $row['SOPRATRANID'],
                    ];

                    //insert
                    //var_dump($bind);

                    $this->insert_ctr($cnt, $bind, 'nip');
                    echo '-----End-----------'.PHP_EOL;
                }
            }
            //dd();
            echo 'Processing Outward Buffers..'.PHP_EOL;

            $outward = "SELECT * FROM NIP_OUTWARD_BUFFER a where STATUS='S' AND AMOUNT >= $local_limit AND trunc(posteddate) >= (trunc(systimestamp) - interval '2' day)";
            $stid3 = oci_parse($Lconn, $outward);

            oci_execute($stid3);

            $cnt = 0;
            //outward
            while ($row = oci_fetch_assoc($stid3)) {
                $cnt = $cnt + 1;

                echo 'RowID: '.$row['TRANID'].PHP_EOL;
                echo 'Posted Date: '.$row['POSTEDDATE'].PHP_EOL;
                echo 'Amount: '.$row['AMOUNT'].PHP_EOL;
                echo 'Account Number: '.$row['TAJSOURCEACCOUNTNO'].PHP_EOL;

                $tran_type = 'OUTWARD_BUFFER';
                $transmode_code = 'A';
                $funds_code = 'L';
                $data = $this->customer_details($conn, $row['TAJSOURCEACCOUNTNO'], '566');

                $bank_name = $this->getBankName($p_conn, $row['DESTINATIONINSTITUTIONCODE']);
                $currency_name = $this->getCurrency($conn, '566');

                // var_dump($tran_type,$data,$currency_name); exit;
                //var_dump($currency_name);

                if (!empty($data) && !empty($currency_name)) {
                    $posted = explode(' ', $row['POSTEDDATE']);
                    $dob = explode(' ', $data['DOB']);
                    $dev = explode(' ', $data['ID_DELIVERY_DATE']);
                    $reg = explode(' ', $data['REG_DATE']);
                    // echo date('m/d/Y H:i:s',strtotime($posted[0])); exit;

                    $bind = [
                        'T_ACCOUNT_NUMBER' => $row['TAJSOURCEACCOUNTNO'],
                        'T_TRANS_NUMBER' => $row['TRANID'],
                        'T_LOCATION' => 'HEAD OFFICE',
                        'TRANSACTION_DESCRIPTION' => $row['NARRATION'],
                        'T_DATE' => date('d/m/Y H:i:s', strtotime($posted[0])),
                        'T_TELLER' => 'SYSTEM',
                        'T_AUTHORIZED' => 'SYSTEM',
                        'T_LATE_DEPOSIT' => 0,
                        'T_DATE_POSTING' => date('d/m/Y H:i:s', strtotime($posted[0])),
                        'T_VALUE_DATE' => date('d/m/Y H:i:s', strtotime($posted[0])),
                        'T_TRANSMODE_CODE' => $transmode_code,
                        'T_AMOUNT_LOCAL' => $row['AMOUNT'],
                        'T_SOURCE_CLIENT_TYPE' => '',
                        'T_SOURCE_TYPE' => '',
                        'T_SOURCE_FUNDS_CODE' => $funds_code,
                        'T_SOURCE_CURRENCY_CODE' => $currency_name['CURRENCY_NAME'],
                        'T_SOURCE_FOREIGN_AMOUNT' => '',
                        'T_SOURCE_EXCHANGE_RATE' => '',
                        'T_SOURCE_COUNTRY' => 'NG',
                        'T_SOURCE_INSTITUTION_CODE' => '000026',
                        'T_SOURCE_INSTITUTION_NAME' => 'TAJ BANK',
                        'T_SOURCE_ACCOUNT_NUMBER' => $row['TAJSOURCEACCOUNTNO'],
                        'T_SOURCE_ACCOUNT_NAME' => $data['FULLNAME'],
                        'T_SOURCE_PERSON_FIRST_NAME' => '',
                        'T_SOURCE_PERSON_LAST_NAME' => '',
                        'T_SOURCE_ENTITY_NAME' => '',
                        'T_DEST_CLIENT_TYPE' => '',
                        'T_DEST_TYPE' => '',
                        'T_DEST_FUNDS_CODE' => $funds_code,
                        'T_DEST_CURRENCY_CODE' => $currency_name['CURRENCY_NAME'],
                        'T_DEST_FOREIGN_AMOUNT' => '',
                        'T_DEST_EXCHANGE_RATE' => '',
                        'T_DEST_COUNTRY' => 'NG',
                        'T_DEST_INSTITUTION_CODE' => $row['DESTINATIONINSTITUTIONCODE'],
                        'T_DEST_INSTITUTION_NAME' => $bank_name,
                        'T_DEST_ACCOUNT_NUMBER' => $row['INTERBANKDESTINATIONACCOUNTNO'],
                        'T_DEST_ACCOUNT_NAME' => $row['BENEFICIARYACCOUNTNAME'],
                        'T_DEST_PERSON_FIRST_NAME' => '',
                        'T_DEST_PERSON_LAST_NAME' => '',
                        'T_DEST_ENTITY_NAME' => '',
                        'TRAN_TYPE' => $tran_type,
                        'T_CLIENT_NUMBER' => $data['BVN'],
                        'T_GENDER' => $data['SEX'],
                        'T_TITLE' => $data['TITLE'],
                        'T_FIRSTNAME' => $data['FIRSTNAME'],
                        'T_LASTNAME' => $data['LASTNAME'],
                        'T_DOB' => date('d/m/Y H:i:s', strtotime($dob[0])),
                        'T_PHONE' => $data['PHONE_NUMBER'],
                        'T_ADDRESS' => $data['ADDRESS'],
                        'T_CITY' => $data['TOWN'],
                        'T_STATE' => $data['STATE'],
                        'T_IDNUMBER' => $data['ID_NUMBER'],
                        'T_IDREGDATE' => date('d/m/Y H:i:s', strtotime($dev[0])),
                        'T_TAXNO' => $data['TAX_ID'],
                        'T_TAXREGDATE' => $data['TAX_ID'],
                        'T_ACCTOPNDATE' => date('d/m/Y H:i:s', strtotime($reg[0])),
                        'T_BALANCE' => $data['ACCOUNT_BALANCE'],
                        'ROW_REF' => $row['TRANID'],
                    ];

                    //insert
                    $this->insert_ctr($cnt, $bind, 'nip');

                    echo '-----End-----------'.PHP_EOL;
                }
            }
            echo 'Done!'.PHP_EOL;
        } catch (Throwable $e) {
        }
    }

    private function insert_ctr($cnt, $bind, $tag = null)
    {
        try {

            $dsn = "pgsql:host=127.0.0.1;dbname=tajbank";
            $username = "postgres";
            $password = "Tajbank123_";

            $p_conn = new PDO($dsn, $username, $password);
            $p_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            // insert into local database or use web service
            echo 'Inserting..'.$bind['TRAN_TYPE'].PHP_EOL;

            $commit = '';
            // commit after every 200 inserts, free up logs and lock resources
            if ($cnt % 200 == 0) {
                echo 'Threshold reached.. will commit ' . PHP_EOL;
                $commit = ' COMMIT; ';
            }
            // insert into local database or use web service
            echo 'Inserting..'.$bind['TRAN_TYPE'].PHP_EOL;
            if ($tag != 'nip') {
                 // Now you can call the pg_db_execute function with the $p_conn object
                $this->pg_db_execute($p_conn, "INSERT INTO ctr_transactions (
                T_ACCOUNT_NUMBER ,
                T_TRANS_NUMBER ,
                T_LOCATION ,
                TRANSACTION_DESCRIPTION ,
                T_DATE ,
                T_TELLER ,
                T_AUTHORIZED ,
                T_LATE_DEPOSIT ,
                T_DATE_POSTING ,
                T_VALUE_DATE ,
                T_TRANSMODE_CODE ,
                T_AMOUNT_LOCAL ,
                T_SOURCE_CLIENT_TYPE ,
                T_SOURCE_TYPE ,
                T_SOURCE_FUNDS_CODE ,
                T_SOURCE_CURRENCY_CODE ,
                T_SOURCE_FOREIGN_AMOUNT ,
                T_SOURCE_EXCHANGE_RATE ,
                T_SOURCE_COUNTRY ,
                T_SOURCE_INSTITUTION_CODE ,
                T_SOURCE_INSTITUTION_NAME ,
                T_SOURCE_ACCOUNT_NUMBER ,
                T_SOURCE_ACCOUNT_NAME ,
                T_SOURCE_PERSON_FIRST_NAME ,
                T_SOURCE_PERSON_LAST_NAME ,
                T_SOURCE_ENTITY_NAME ,
                T_DEST_CLIENT_TYPE ,
                T_DEST_TYPE ,
                T_DEST_FUNDS_CODE ,
                T_DEST_CURRENCY_CODE ,
                T_DEST_FOREIGN_AMOUNT ,
                T_DEST_EXCHANGE_RATE ,
                T_DEST_COUNTRY ,
                T_DEST_INSTITUTION_CODE ,
                T_DEST_INSTITUTION_NAME ,
                T_DEST_ACCOUNT_NUMBER ,
                T_DEST_ACCOUNT_NAME ,
                T_DEST_PERSON_FIRST_NAME ,
                T_DEST_PERSON_LAST_NAME ,
                T_DEST_ENTITY_NAME ,
                TRAN_TYPE,
                T_CLIENT_NUMBER,
                T_GENDER,
                T_TITLE,
                T_FIRSTNAME,
                T_LASTNAME,
                T_DOB,
                T_PHONE,
                T_ADDRESS,
                T_CITY,
                T_STATE,
                T_IDNUMBER,
                T_IDREGDATE,
                T_TAXNO,
                T_TAXREGDATE,
                T_ACCTOPNDATE,
                T_BALANCE,
                ROW_REF
            ) values ( 
                :T_ACCOUNT_NUMBER,
                :T_TRANS_NUMBER,
                :T_LOCATION,
                :TRANSACTION_DESCRIPTION,
                to_timestamp(:T_DATE,'MM/DD/YYYY HH24:MI:SS'),
                :T_TELLER,
                :T_AUTHORIZED,
                :T_LATE_DEPOSIT,
                to_timestamp(:T_DATE_POSTING,'MM/DD/YYYY HH24:MI:SS'),
                to_timestamp(:T_VALUE_DATE,'MM/DD/YYYY HH24:MI:SS'),
                :T_TRANSMODE_CODE,
                :T_AMOUNT_LOCAL,
                :T_SOURCE_CLIENT_TYPE,
                :T_SOURCE_TYPE,
                :T_SOURCE_FUNDS_CODE,
                :T_SOURCE_CURRENCY_CODE,
                :T_SOURCE_FOREIGN_AMOUNT,
                :T_SOURCE_EXCHANGE_RATE,
                :T_SOURCE_COUNTRY,
                :T_SOURCE_INSTITUTION_CODE,
                :T_SOURCE_INSTITUTION_NAME,
                :T_SOURCE_ACCOUNT_NUMBER,
                :T_SOURCE_ACCOUNT_NAME,
                :T_SOURCE_PERSON_FIRST_NAME,
                :T_SOURCE_PERSON_LAST_NAME,
                :T_SOURCE_ENTITY_NAME,
                :T_DEST_CLIENT_TYPE,
                :T_DEST_TYPE,
                :T_DEST_FUNDS_CODE,
                :T_DEST_CURRENCY_CODE,
                :T_DEST_FOREIGN_AMOUNT,
                :T_DEST_EXCHANGE_RATE,
                :T_DEST_COUNTRY,
                :T_DEST_INSTITUTION_CODE,
                :T_DEST_INSTITUTION_NAME,
                :T_DEST_ACCOUNT_NUMBER,
                :T_DEST_ACCOUNT_NAME,
                :T_DEST_PERSON_FIRST_NAME,
                :T_DEST_PERSON_LAST_NAME,
                :T_DEST_ENTITY_NAME,
                replace(trim(:TRAN_TYPE),' ','_'),
                :T_CLIENT_NUMBER,
                :T_GENDER,
                :T_TITLE,
                :T_FIRSTNAME,
                :T_LASTNAME,
                to_timestamp(:T_DOB,'MM/DD/YYYY HH24:MI:SS'),
                :T_PHONE,
                :T_ADDRESS,
                :T_CITY,
                :T_STATE,
                :T_IDNUMBER,
                to_timestamp(:T_IDREGDATE,'MM/DD/YYYY HH24:MI:SS'),
                :T_TAXNO,
                :T_TAXREGDATE,
                to_timestamp(:T_ACCTOPNDATE,'MM/DD/YYYY HH24:MI:SS'),
                :T_BALANCE,
                :ROW_REF
            );" . $commit, $bind);
            } else {
                   // Now you can call the pg_db_execute function with the $p_conn object
                $this->pg_db_execute($p_conn, "INSERT INTO ctr_transactions (
                T_ACCOUNT_NUMBER ,
                T_TRANS_NUMBER ,
                T_LOCATION ,
                TRANSACTION_DESCRIPTION ,
                T_DATE ,
                T_TELLER ,
                T_AUTHORIZED ,
                T_LATE_DEPOSIT ,
                T_DATE_POSTING ,
                T_VALUE_DATE ,
                T_TRANSMODE_CODE ,
                T_AMOUNT_LOCAL ,
                T_SOURCE_CLIENT_TYPE ,
                T_SOURCE_TYPE ,
                T_SOURCE_FUNDS_CODE ,
                T_SOURCE_CURRENCY_CODE ,
                T_SOURCE_FOREIGN_AMOUNT ,
                T_SOURCE_EXCHANGE_RATE ,
                T_SOURCE_COUNTRY ,
                T_SOURCE_INSTITUTION_CODE ,
                T_SOURCE_INSTITUTION_NAME ,
                T_SOURCE_ACCOUNT_NUMBER ,
                T_SOURCE_ACCOUNT_NAME ,
                T_SOURCE_PERSON_FIRST_NAME ,
                T_SOURCE_PERSON_LAST_NAME ,
                T_SOURCE_ENTITY_NAME ,
                T_DEST_CLIENT_TYPE ,
                T_DEST_TYPE ,
                T_DEST_FUNDS_CODE ,
                T_DEST_CURRENCY_CODE ,
                T_DEST_FOREIGN_AMOUNT ,
                T_DEST_EXCHANGE_RATE ,
                T_DEST_COUNTRY ,
                T_DEST_INSTITUTION_CODE ,
                T_DEST_INSTITUTION_NAME ,
                T_DEST_ACCOUNT_NUMBER ,
                T_DEST_ACCOUNT_NAME ,
                T_DEST_PERSON_FIRST_NAME ,
                T_DEST_PERSON_LAST_NAME ,
                T_DEST_ENTITY_NAME ,
                TRAN_TYPE,
                T_CLIENT_NUMBER,
                T_GENDER,
                T_TITLE,
                T_FIRSTNAME,
                T_LASTNAME,
                T_DOB,
                T_PHONE,
                T_ADDRESS,
                T_CITY,
                T_STATE,
                T_IDNUMBER,
                T_IDREGDATE,
                T_TAXNO,
                T_TAXREGDATE,
                T_ACCTOPNDATE,
                T_BALANCE,
                ROW_REF
            ) values ( 
                :T_ACCOUNT_NUMBER,
                :T_TRANS_NUMBER,
                :T_LOCATION,
                :TRANSACTION_DESCRIPTION,
                to_timestamp(:T_DATE,'DD/MM/YYYY HH24:MI:SS'),
                :T_TELLER,
                :T_AUTHORIZED,
                :T_LATE_DEPOSIT,
                to_timestamp(:T_DATE_POSTING,'DD/MM/YYYY HH24:MI:SS'),
                to_timestamp(:T_VALUE_DATE,'DD/MM/YYYY HH24:MI:SS'),
                :T_TRANSMODE_CODE,
                :T_AMOUNT_LOCAL,
                :T_SOURCE_CLIENT_TYPE,
                :T_SOURCE_TYPE,
                :T_SOURCE_FUNDS_CODE,
                :T_SOURCE_CURRENCY_CODE,
                :T_SOURCE_FOREIGN_AMOUNT,
                :T_SOURCE_EXCHANGE_RATE,
                :T_SOURCE_COUNTRY,
                :T_SOURCE_INSTITUTION_CODE,
                :T_SOURCE_INSTITUTION_NAME,
                :T_SOURCE_ACCOUNT_NUMBER,
                :T_SOURCE_ACCOUNT_NAME,
                :T_SOURCE_PERSON_FIRST_NAME,
                :T_SOURCE_PERSON_LAST_NAME,
                :T_SOURCE_ENTITY_NAME,
                :T_DEST_CLIENT_TYPE,
                :T_DEST_TYPE,
                :T_DEST_FUNDS_CODE,
                :T_DEST_CURRENCY_CODE,
                :T_DEST_FOREIGN_AMOUNT,
                :T_DEST_EXCHANGE_RATE,
                :T_DEST_COUNTRY,
                :T_DEST_INSTITUTION_CODE,
                :T_DEST_INSTITUTION_NAME,
                :T_DEST_ACCOUNT_NUMBER,
                :T_DEST_ACCOUNT_NAME,
                :T_DEST_PERSON_FIRST_NAME,
                :T_DEST_PERSON_LAST_NAME,
                :T_DEST_ENTITY_NAME,
                replace(trim(:TRAN_TYPE),' ','_'),
                :T_CLIENT_NUMBER,
                :T_GENDER,
                :T_TITLE,
                :T_FIRSTNAME,
                :T_LASTNAME,
                to_timestamp(:T_DOB,'DD/MM/YYYY HH24:MI:SS'),
                :T_PHONE,
                :T_ADDRESS,
                :T_CITY,
                :T_STATE,
                :T_IDNUMBER,
                to_timestamp(:T_IDREGDATE,'DD/MM/YYYY HH24:MI:SS'),
                :T_TAXNO,
                :T_TAXREGDATE,
                to_timestamp(:T_ACCTOPNDATE,'DD/MM/YYYY HH24:MI:SS'),
                :T_BALANCE,
                :ROW_REF
            );" . $commit, $bind);
                echo 'Inserted..'.$bind['TRAN_TYPE'].PHP_EOL;
            }
        } catch (\Throwable $e) {
            echo $e->getMessage();
        }
    }

    private function getCurrency($conn, $currency_id)
    {
        $sql = "select trim(cacc) currency_id,upper(trim(lib2)) currency_name,lib1 narration
        from tajprod.bknom where trim(cacc) = '$currency_id' and trim(ctab)='005' ";
        // echo $sql; exit;
        $data = $this->db_query_one($conn, $sql);
        //   var_dump($data['CURRENCY_ID']); exit;
        if (isset($data['CURRENCY_ID'])) {
            return $data;
        }
    }

    private function customer_details($conn, $account_num, $currency)
    {
        $sql = "SELECT 
         b.pre Firstname
        ,b.nom Lastname
        ,b.sext Sex
        ,b.dna Dob
        ,b.viln Town
        ,b.depn State
        ,b.nrc rc_num
        ,b.nidf tax_id
        ,b.dou Reg_date
        ,b.drc company_reg_date
        ,b.nid id_number
        ,b.vid id_expiry_date
        ,b.did id_delivery_date
        ,b.nomrest fullname
        ,g.lib1 title
        ,b.nat nationality_code
        ,trim(c.dev) account_currency_code
        ,c.dou account_created_date
        ,c.sin account_balance
        ,f.adr1 || ' ' || f.adr2 || ' ' || f.adr3 address
        ,d.valmt bvn
        ,e.num phone_number
        FROM tajprod.bkcli b, tajprod.bkcom c, tajprod.bkicli d, tajprod.bktelcli e, tajprod.bkadcli f , tajprod.bknom g
        WHERE (trim(b.cli)=trim(c.cli) and trim(c.dev) = trim('$currency') and trim(c.ncp) = trim('$account_num'))
            and trim(b.cli)=trim(d.cli) 
            and trim(b.cli)=trim(e.cli) 
            and trim(b.cli)=trim(f.cli) 
            and (trim(g.cacc)=trim(b.lib) and trim(ctab)='036') fetch first 1 rows only";

        $data = $this->db_query_one($conn, $sql);
        // echo $sql;
        //var_dump($data);
        //dd();
        if (!empty($data)) {
            return $data;
        }
    }

    //oracle db connection
    private function db_connect($tag = null)
    {
        $host = '172.19.20.60';
        $user = 'novaji';
        $pwd = 'novali123';
        $port = '1521';
        $desc = "(DESCRIPTION =(ADDRESS = (PROTOCOL = TCP)(HOST = $host)(PORT = $port))(CONNECT_DATA =(SERVER = DEDICATED)(SERVICE_NAME=tajrep)))";
        $conn = oci_connect($user, $pwd, "172.19.20.60:$port/tajrep");

        if ($tag == 'local') {
            $host = '172.19.2.86';
            $user = 'tajbank';
            $pwd = 'Tajbank123_';
            $port = '1522';
            $desc = "(DESCRIPTION =(ADDRESS = (PROTOCOL = TCP)(HOST = $host)(PORT = $port))(CONNECT_DATA =(SERVER = DEDICATED)(SERVICE_NAME=xepdb1)))";
            $conn = oci_connect($user, $pwd, "172.19.2.86:$port/xepdb1");
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
        /*if($row = oci_fetch_array($stid, OCI_ASSOC)) == true){
            return $row;
          }*/
       //oci_free_statement($s);
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

    private function getBankName($p_conn, $bankCode)
    {
    // Prepare a select statement
    $stmt = $p_conn->prepare("SELECT bank_name FROM banks WHERE bank_code = :bankCode");

    // Bind parameters
    $stmt->bindParam(':bankCode', $bankCode, PDO::PARAM_STR);

    // Execute the statement
    $stmt->execute();

    // Fetch the result
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    // If the bank code is found, return the bank name, otherwise return 'Others'
    return $result ? $result['bank_name'] : 'Others';
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
