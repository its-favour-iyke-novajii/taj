<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Throwable;

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

            $cnt2 = 0;
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
                $bank_name = $this->getBankName($row['SENDERBANKCODE']);
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

                    $this->insert_ctr($Lconn, $bind, 'nip');
                    echo '-----End-----------'.PHP_EOL;
                }
            }
            //dd();
            echo 'Processing Outward Buffers..'.PHP_EOL;

            $outward = "SELECT * FROM NIP_OUTWARD_BUFFER a where STATUS='S' AND AMOUNT >= $local_limit AND trunc(posteddate) >= (trunc(systimestamp) - interval '2' day)";
            $stid3 = oci_parse($Lconn, $outward);

            oci_execute($stid3);

            $cnt3 = 0;
            //outward
            while ($row = oci_fetch_assoc($stid3)) {
                $cnt3 = $cnt3 + 1;

                echo 'RowID: '.$row['TRANID'].PHP_EOL;
                echo 'Posted Date: '.$row['POSTEDDATE'].PHP_EOL;
                echo 'Amount: '.$row['AMOUNT'].PHP_EOL;
                echo 'Account Number: '.$row['TAJSOURCEACCOUNTNO'].PHP_EOL;

                $tran_type = 'OUTWARD_BUFFER';
                $transmode_code = 'A';
                $funds_code = 'L';
                $data = $this->customer_details($conn, $row['TAJSOURCEACCOUNTNO'], '566');

                $bank_name = $this->getBankName($row['DESTINATIONINSTITUTIONCODE']);
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
                    $this->insert_ctr($Lconn, $bind, 'nip');

                    echo '-----End-----------'.PHP_EOL;
                }
            }
            echo 'Done!'.PHP_EOL;
        } catch (Throwable $e) {
        }
    }

    private function insert_ctr($conn, $bind, $tag = null)
    {
        try {
            // insert into local database or use web service
            echo 'Inserting..'.$bind['TRAN_TYPE'].PHP_EOL;
            if ($tag != 'nip') {
                $this->db_execute($conn, "begin insert into ctr_transactions (
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
            ); commit; exception when others then null; end;", $bind);
            } else {
                $this->db_execute($conn, "begin insert into ctr_transactions (
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
            ); commit; exception when others then null; end;", $bind);
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

    private function getBankName($bankCode)
    {
        switch ($bankCode) {
            case '000001':
                return 'Sterling Bank';
            case '000002':
                return 'Keystone Bank';
            case '000003':
                return 'FCMB';
            case '000004':
                return 'United Bank for Africa';
            case '000005':
                return 'Access Bank PLC (Diamond)';
            case '000006':
                return 'JAIZ Bank';
            case '000007':
                return 'Fidelity Bank';
            case '000008':
                return 'POLARIS BANK';
            case '000009':
                return 'Citi Bank';
            case '000010':
                return 'Ecobank Bank';
            case '000011':
                return 'Unity Bank';
            case '000012':
                return 'StanbicIBTC Bank';
            case '000013':
                return 'GTBank Plc';
            case '000014':
                return 'Access Bank';
            case '000015':
                return 'ZENITH BANK PLC';
            case '000016':
                return 'First Bank of Nigeria';
            case '000017':
                return 'Wema Bank';
            case '000018':
                return 'Union Bank';
            case '000019':
                return 'Enterprise Bank';
            case '000020':
                return 'Heritage';
            case '000021':
                return 'StandardChartered';
            case '000022':
                return 'SUNTRUST BANK';
            case '000023':
                return 'Providus Bank ';
            case '000024':
                return 'Rand Merchant Bank';
            case '000025':
                return 'TITAN TRUST BANK';
            case '000026':
                return 'Taj Bank';
            case '000027':
                return 'Globus Bank';
            case '000028':
                return 'Central Bank of Nigeria';
            case '060001':
                return 'Coronation';
            case '060002':
                return 'FBNQuest MERCHANT BANK';
            case '060003':
                return 'NOVA MB';
            case '070001':
                return 'NPF MicroFinance Bank';
            case '070002':
                return 'Fortis Microfinance Bank';
            case '070006':
                return 'Covenant MFB';
            case '070007':
                return 'Omoluabi Mortgage Bank Plc';
            case '070008':
                return 'PAGE FINANCIALS';
            case '070009':
                return 'GATEWAY MORTGAGE BANK';
            case '070010':
                return 'ABBEY MORTGAGE BANK';
            case '070011':
                return 'Refuge Mortgage Bank';
            case '070012':
                return 'LBIC Mortgage Bank';
            case '070013':
                return 'PLATINUM MORTGAGE BANK';
            case '070014':
                return 'First Generation Mortgage Bank';
            case '070015':
                return 'Brent Mortgage Bank';
            case '070016':
                return 'Infinity trust  Mortgage Bank';
            case '070017':
                return 'Haggai Mortgage Bank';
            case '070019':
                return 'MAYFRESH MORTGAGE BANK';
            case '090001':
                return 'ASOSavings';
            case '090003':
                return 'JubileeLife';
            case '090004':
                return 'Parralex';
            case '090005':
                return 'Trustbond';
            case '090006':
                return 'SafeTrust';
            case '090097':
                return 'Ekondo MFB';
            case '090107':
                return 'FBN Morgages Limited';
            case '090108':
                return 'New Prudential Bank';
            case '090110':
                return 'VFD MFB';
            case '090111':
                return 'FinaTrust Microfinance Bank';
            case '090112':
                return 'Seed Capital Microfinance Bank';
            case '090113':
                return 'MICROVIS MICROFINANCE BANK';
            case '090114':
                return 'EmpireTrust Microfinance bank';
            case '090115':
                return 'TCF';
            case '090116':
                return 'AMML MFB';
            case '090117':
                return 'Boctrust Microfinance Bank';
            case '090118':
                return 'IBILE Microfinance Bank';
            case '090119':
                return 'OHAFIA MFB';
            case '090120':
                return 'WETLAND MFB';
            case '090121':
                return 'HASAL MFB';
            case '090122':
                return 'GOWANS MFB';
            case '090123':
                return 'Verite Microfinance Bank';
            case '090124':
                return 'XSLNCE Microfinance Bank';
            case '090125':
                return 'REGENT MFB';
            case '090126':
                return 'FidFund MFB';
            case '090127':
                return 'BC Kash MFB';
            case '090128':
                return 'Ndiorah MFB';
            case '090129':
                return 'MONEYTRUST MFB';
            case '090130':
                return 'CONSUMER  MFB';
            case '090131':
                return 'ALLWORKERS MFB';
            case '090132':
                return 'RICHWAY MFB';
            case '090133':
                return 'AL-BARKAH MFB';
            case '090134':
                return 'ACCION MFB';
            case '090135':
                return 'Personal Trust Microfinance Bank';
            case '090136':
                return 'Microcred Microfinance Bank';
            case '090137':
                return 'Pecan Trust Microfinance Bank';
            case '090138':
                return 'Royal Exchange Microfinance Bank';
            case '090139':
                return 'Visa Microfinance Bank';
            case '090140':
                return 'Sagamu Microfinance Bank';
            case '090141':
                return 'Chikum Microfinance Bank';
            case '090142':
                return 'Yes MFB';
            case '090143':
                return 'APEKS Microfinance Bank';
            case '090144':
                return 'CIT Microfinance Bank';
            case '090145':
                return 'Full range MFB';
            case '090146':
                return 'Trident Microfinance Bank';
            case '090147':
                return 'Hackman Microfinance Bank';
            case '090148':
                return 'Bowen MFB';
            case '090149':
                return 'IRL Microfinance Bank';
            case '090150':
                return 'Virtue MFB';
            case '090151':
                return 'Mutual Trust Microfinance Bank';
            case '090152':
                return 'Nargata MFB';
            case '090153':
                return 'FFS Microfinance Bank';
            case '090154':
                return 'CEMCS MFB';
            case '090155':
                return 'La Fayette Microfinance Bank';
            case '090156':
                return 'e-BARCs MFB';
            case '090157':
                return 'Infinity MFB';
            case '090158':
                return 'FUTO MFB';
            case '090159':
                return 'Credit Afrique MFB';
            case '090160':
                return 'Addosser MFBB';
            case '090161':
                return 'Okpoga MFB';
            case '090162':
                return 'Stanford MFB';
            case '090163':
                return 'First Multiple MFB';
            case '090164':
                return 'First Royal Microfinance Bank';
            case '090165':
                return 'Petra Microfinance Bank';
            case '090166':
                return 'Eso-E Microfinance Bank';
            case '090167':
                return 'Daylight Microfinance Bank';
            case '090168':
                return 'Gashua Microfinance Bank';
            case '090169':
                return 'Alphakapital MFB';
            case '090170':
                return 'Rahama MFB';
            case '090171':
                return 'Mainstreet MFB';
            case '090172':
                return 'Astrapolis MFB';
            case '090173':
                return 'Reliance MFB';
            case '090174':
                return 'Malachy MFB';
            case '090175':
                return 'HighStreet MFB';
            case '090176':
                return 'Bosak MFB';
            case '090177':
                return 'Lapo MFB';
            case '090178':
                return 'GreenBank MFB';
            case '090179':
                return 'FAST MFB';
            case '090180':
                return 'Amju MFB';
            case '090186':
                return 'Girei MFB';
            case '090188':
                return 'Baines Credit MFB';
            case '090189':
                return 'Esan MFB';
            case '090190':
                return 'Mutual Benefits MFB';
            case '090191':
                return 'KCMB MFB';
            case '090192':
                return 'Midland MFB';
            case '090193':
                return 'Unical MFB';
            case '090194':
                return 'NIRSAL National microfinance bank';
            case '090195':
                return 'Grooming Microfinance bank';
            case '090196':
                return 'Pennywise Microfinance bank';
            case '090197':
                return 'ABU Microfinance bank';
            case '090198':
                return 'Renmoney microfinance bank';
            case '090201':
                return 'XPRESS PAYMENTS';
            case '090202':
                return 'ACCELEREX NETWORK';
            case '090205':
                return 'Newdawn Microfinance bank';
            case '090211':
                return 'ITEX INTEGRATED SERVICES LIMITED';
            case '090251':
                return 'UNN MFB';
            case '090252':
                return 'Yobe MFB';
            case '090258':
                return 'Imo Microfinance bank';
            case '090259':
                return 'Alekun Microfinance bank';
            case '090260':
                return 'Above Only Microfinance bank';
            case '090261':
                return 'QuickFund Microfinance bank';
            case '090262':
                return 'Stellas Microfinance bank';
            case '090263':
                return 'Navy Microfinance bank';
            case '090264':
                return 'Auchi Microfinance bank';
            case '090265':
                return 'Lovonus Microfinance bank';
            case '090266':
                return 'Uniben Microfinance bank';
            case '090267':
                return 'Kuda Microfinance Bank';
            case '090268':
                return 'Adeyemi College Staff Microfinance bank';
            case '090269':
                return 'Greenville Microfinance bank';
            case '090270':
                return 'AB Microfinance bank';
            case '090271':
                return 'Lavender Microfinance bank';
            case '090272':
                return 'Olabisi Onabanjo university Microfinance bank';
            case '090273':
                return 'Emeralds MFB';
            case '090274':
                return 'Prestige Microfinance bank';
            case '090275':
                return 'Meridian MFB';
            case '090276':
                return 'TRUSTFUND MICROFINANCE BANK';
            case '090277':
                return 'Alhayat MFB';
            case '090278':
                return 'Glory MFB';
            case '090279':
                return 'Ikire MFB';
            case '090280':
                return 'Megapraise Microfinance Bank';
            case '090281':
                return 'Finex MFB';
            case '090282':
                return 'Arise MFB';
            case '090283':
                return 'Nnew women MFB';
            case '090285':
                return 'First Option MFB';
            case '090286':
                return 'Safe Haven MFB';
            case '090287':
                return 'Assets Matrix MFB';
            case '090289':
                return 'Pillar MFB';
            case '090290':
                return 'FCT MFB';
            case '090291':
                return 'Hala MFB';
            case '090292':
                return 'Afekhafe MFB';
            case '090293':
                return 'BRETHREN MICROFINANCE BANK';
            case '090294':
                return 'Eagle Flight MFB';
            case '090295':
                return 'Omiye MFB';
            case '090296':
                return 'Polyuwanna MFB';
            case '090297':
                return 'Alert MFB';
            case '090298':
                return 'FederalPoly NasarawaMFB';
            case '090299':
                return 'Kontagora MFB';
            case '090303':
                return 'Purplemoney MFB';
            case '090304':
                return 'EVANGEL MFB';
            case '090305':
                return 'Sulsap MFB';
            case '090308':
                return 'Brightway MFB';
            case '090310':
                return 'EDFIN MFB';
            case '090315':
                return 'U AND C MFB';
            case '090316':
                return 'BAYERO MICROFINANCE BANK';
            case '090317':
                return 'PATRICK GOLD';
            case '090318':
                return 'FEDERAL UNIVERSITY DUTSE  MICROFINANCE BANK';
            case '090320':
                return 'KADPOLY MICROFINANCE BANK';
            case '090321':
                return 'MAYFAIR MFB';
            case '090322':
                return 'REPHIDIM MICROFINANCE BANK';
            case '090323':
                return 'MAINLAND MICROFINANCE BANK';
            case '090324':
                return 'IKENNE MFB';
            case '090325':
                return 'SPARKLE MICROFINANCE BANK';
            case '090326':
                return 'BALOGUN GAMBARI MFB';
            case '090327':
                return 'TRUST MFB';
            case '090328':
                return 'EYOWO MICROFINANCE BANK';
            case '090329':
                return 'NEPTUNE MICROFINANCE BANK';
            case '090331':
                return 'UNAAB MFB';
            case '090332':
                return 'EVERGREEN MICROFINANCE BANK';
            case '090333':
                return 'OCHE MFB';
            case '090336':
                return 'BIPC MICROFINANCE BANK';
            case '090360':
                return 'CASHCONNECT   MICROFINANCE BANK';
            case '090362':
                return 'MOLUSI MICROFINANCE BANK';
            case '090363':
                return 'Headway MFB';
            case '090364':
                return 'NUTURE MFB';
            case '090365':
                return 'Corestep MICROFINANCE BANK';
            case '090366':
                return 'Firmus MICROFINANCE BANK';
            case '090369':
                return 'SEEDVEST MICROFINANCE BANK';
            case '090370':
                return 'ILASAN MICROFINANCE BANK';
            case '090371':
                return 'AGOSASA MICROFINANCE BANK';
            case '090372':
                return 'LEGEND MICROFINANCE BANK';
            case '090373':
                return 'TF MICROFINANCE BANK';
            case '090374':
                return 'COASTLINE MICROFINANCE BANK';
            case '090376':
                return 'APPLE MICROFINANCE BANK';
            case '090377':
                return 'ISALEOYO MICROFINANCE BANK';
            case '090378':
                return 'NEW GOLDEN PASTURES MICROFINANCE BANK';
            case '090380':
                return 'Conpro Micofinance Bank';
            case '090383':
                return 'MANNY MICROFINANCE BANK';
            case '090385':
                return 'GTI Microfinance Bank';
            case '090386':
                return 'Interland MFB';
            case '090389':
                return 'EK-Reliable Microfinance Bank';
            case '090391':
                return 'Davodani Microfinance Bank';
            case '090392':
                return 'Mozfin Microfinance Bank';
            case '090393':
                return 'Bridgeway Microfinance Bank ';
            case '090394':
                return 'Amac Microfinance Bank';
            case '090395':
                return 'Borgu MFB';
            case '090396':
                return 'Oscotech MFB';
            case '090398':
                return 'Federal Polytechnic Nekede Microfinance Bank';
            case '090399':
                return 'Nwannegadi MFB';
            case '090400':
                return 'FINCA Microfinance Bank ';
            case '090401':
                return 'Shepherd Trust Microfinance Bank';
            case '090404':
                return 'Olowolagba Microfinance Bank';
            case '090405':
                return 'ROLEZ MICROFINANCE BANK';
            case '090406':
                return 'Business Support Microfinance Bank';
            case '090408':
                return 'GMB Microfinance Bank';
            case '090409':
                return 'FCMB BETA';
            case '090410':
                return 'Maritime Microfinance Bank';
            case '100001':
                return 'FET';
            case '100002':
                return 'Paga';
            case '100003':
                return 'Parkway-ReadyCash';
            case '100004':
                return 'Paycom';
            case '100005':
                return 'Cellulant';
            case '100006':
                return 'eTranzact';
            case '100007':
                return 'Stanbic IBTC @ease Wallet';
            case '100008':
                return 'Ecobank Xpress Account';
            case '100009':
                return 'GTMobile';
            case '100010':
                return 'TeasyMobile';
            case '100011':
                return 'Mkudi';
            case '100012':
                return 'VTNetworks';
            case '100013':
                return 'AccessMobile';
            case '100014':
                return 'FBNMobile';
            case '100015':
                return 'Kegow';
            case '100016':
                return 'FortisMobile';
            case '100017':
                return 'Hedonmark';
            case '100018':
                return 'ZenithMobile';
            case '100019':
                return 'Fidelity Mobile';
            case '100020':
                return 'MoneyBox';
            case '100021':
                return 'Eartholeum';
            case '100022':
                return 'GoMoney';
            case '100023':
                return 'TagPay';
            case '100024':
                return 'Imperial Homes Mortgage Bank';
            case '100025':
                return 'Zinternet - KongaPay';
            case '100026':
                return 'ONE FINANCE';
            case '100027':
                return 'Intellifin';
            case '100028':
                return 'AG MORTGAGE BANK PLC';
            case '100029':
                return 'Innovectives Kesh';
            case '100031':
                return 'FCMB Easy Account';
            case '100032':
                return 'Contec Global';
            case '100033':
                return 'PALMPAY';
            case '100034':
                return 'ZWallet';
            case '100035':
                return 'M36';
            case '100052':
                return 'BETA-ACCESS YELLO';
            case '110001':
                return 'PayAttitude Online';
            case '110002':
                return 'FLUTTERWAVE TECHNOLOGY SOLUTIONS LIMITED';
            case '110003':
                return 'INTERSWITCH LIMITED';
            case '110004':
                return 'FIRST APPLE LIMITED';
            case '110005':
                return '3LINE CARD MANAGEMENT LIMITED';
            case '110006':
                return 'PAYSTACK PAYMENTS LIMITED';
            case '110007':
                return 'TEAM APT';
            case '110008':
                return 'KADICK INTEGRATION LIMITED';
            case '110009':
                return 'Venture Garden Nigeria Limited';
            case '110011':
                return 'ARCA PAYMENTS COMPANY LIMITED';
            case '110012':
                return 'Cellulant PSSP';
            case '110013':
                return 'QR Payments';
            case '110014':
                return 'Cyberspace Limited';
            case '120001':
                return '9 payment service Bank';
            case '120002':
                return 'HopePSB';
            case '400001':
                return 'FSDH';
            case '999999':
                return 'NIP Virtual Bank';
            default:
                return 'Others';
        }
    }
}
