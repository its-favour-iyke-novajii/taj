<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PDO;

class UpdateNIP extends Command
{
    protected $signature = 'ctr:update-nip';
    protected $description = 'Use php command line to update CTR table';

    public $p_conn; // Postgres database connection

    public function __construct()
    {
        parent::__construct();
        $this->p_conn = $this->db_connectPostgres();
    }

    public function handle()
    {
        set_time_limit(0);
        echo 'Running CTR Update for NIP..' . PHP_EOL;

        // all transactions last 2 hours
        /*
         Get Forex transactions above 10,000
         and Local Transaction above N5m (individual) or N10m (Corporate)
        */

  

        echo 'Processing NIP Buffers..' . PHP_EOL;

        try {
            $this->update_Outward_buffer();
            $this->update_Inward_buffer();
            echo 'Done!' . PHP_EOL;
        } catch (\Throwable $e) {
            echo 'Error: ' . $e->getMessage() . PHP_EOL;
        }
    }

    private function update_Outward_buffer()
    {
        $p_conn = $this->db_connectPostgres();

        $conn = oci_connect('novaji', 'novali123', '172.19.20.60:1521/tajrep');

        $local_limit = 5000000;
        $foreign_limit = 10000;

        echo 'Processing Outward Buffers..' . PHP_EOL;
        
        //$outward = "SELECT * FROM NIP_OUTWARD_BUFFER WHERE STATUS = 'S'"; 

        //$outward = "SELECT * FROM NIP_OUTWARD_BUFFER a where STATUS='S' AND amount::NUMERIC >= $local_limit AND posteddate >= NOW() - INTERVAL '2 hours'";
        
        $outward = "SELECT * FROM NIP_OUTWARD_BUFFER a where STATUS='S' AND amount::NUMERIC >= $local_limit AND posteddate >= CURRENT_DATE - INTERVAL '1 day'";

        $stmt = $this->p_conn->prepare($outward);
        $stmt->execute();

        $cnt = 0;
        $records = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cnt = $cnt + 1;
            echo 'RowID: ' . $row['tranid'] . PHP_EOL;
          //  echo 'Posted Date: ' . $row['POSTEDDATE'] . PHP_EOL;
            echo 'Amount: ' . $row['amount'] . PHP_EOL;
            echo 'Account Number: ' . $row['tajsourceaccountno'] . PHP_EOL;

            // The rest of the processing logic goes here
            // .
         
            $tran_type = 'OUTWARD_BUFFER';
            $transmode_code = 'A';
            $funds_code = 'L';
            $data = $this->customer_details($conn, $row['tajsourceaccountno'], '566');

            $bank_name = $this->getBankName($p_conn, $row['destinationinstitutioncode']);
            $currency_name = $this->getCurrency($conn, '566');

            // var_dump($tran_type,$data,$currency_name); exit;
            //var_dump($currency_name);
           
            if (!empty($data) && !empty($currency_name)) {
                $posted = explode(' ', $row['posteddate']);
                $dob = explode(' ', $data['DOB']);
                $dev = explode(' ', $data['ID_DELIVERY_DATE']);
                $reg = explode(' ', $data['REG_DATE']);
                // echo date('m/d/Y H:i:s',strtotime($posted[0])); exit;


            $bind = [
                'T_ACCOUNT_NUMBER' => $row['tajsourceaccountno'],
                'T_TRANS_NUMBER' => $row['tranid'],
                'T_LOCATION' => 'HEAD OFFICE',
                'TRANSACTION_DESCRIPTION' => $row['narration'],
                'T_DATE' => date('d/m/Y H:i:s', strtotime($posted[0])),
                'T_TELLER' => 'SYSTEM',
                'T_AUTHORIZED' => 'SYSTEM',
                'T_LATE_DEPOSIT' => 0,
                'T_DATE_POSTING' => date('d/m/Y H:i:s', strtotime($posted[0])),
                'T_VALUE_DATE' => date('d/m/Y H:i:s', strtotime($posted[0])),
                'T_TRANSMODE_CODE' => $transmode_code,
                'T_AMOUNT_LOCAL' => $row['amount'],
                'T_SOURCE_CLIENT_TYPE' => '',
                'T_SOURCE_TYPE' => '',
                'T_SOURCE_FUNDS_CODE' => $funds_code,
                'T_SOURCE_CURRENCY_CODE' => $currency_name['CURRENCY_NAME'],
                'T_SOURCE_FOREIGN_AMOUNT' => '',
                'T_SOURCE_EXCHANGE_RATE' => '',
                'T_SOURCE_COUNTRY' => 'NG',
                'T_SOURCE_INSTITUTION_CODE' => '000026',
                'T_SOURCE_INSTITUTION_NAME' => 'TAJ BANK',
                'T_SOURCE_ACCOUNT_NUMBER' => $row['tajsourceaccountno'],
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
                'T_DEST_INSTITUTION_CODE' => $row['destinationinstitutioncode'],
                'T_DEST_INSTITUTION_NAME' => $bank_name,
                'T_DEST_ACCOUNT_NUMBER' => $row['interbankdestinationaccountno'],
                'T_DEST_ACCOUNT_NAME' => $row['beneficiaryaccountname'],
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
                'ROW_REF' => $row['row_id'],
                'BRANCH_NAME' => $row['BRANCH_CODE'],
             
            ];


            $this->insert_ctr($cnt, $records, $bind, 'nip');
            

            echo '-----End-----------' . PHP_EOL;
        }
    }
    }



    private function update_Inward_buffer()
{
    $p_conn = $this->db_connectPostgres();

    $conn = oci_connect('novaji', 'novali123', '172.19.20.60:1521/tajrep');

    $local_limit = 5000000;
    $foreign_limit = 10000;

    echo 'Processing Inward Buffers..' . PHP_EOL;
    //$inward = "SELECT * FROM NIP_INWARD_BUFFER WHERE STATUS = 'S'"; 
    $inward = "SELECT * FROM NIP_INWARD_BUFFER WHERE status = 'S' AND amount::NUMERIC >= $local_limit AND recorddate >= NOW() - INTERVAL '2 hours'";

    $stmt = $this->p_conn->prepare($inward);
    $stmt->execute();

    $cnt = 0;
    $records = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $cnt = $cnt + 1;
        echo 'Row ID: '.$row['sopratranid'].PHP_EOL;
        echo 'Record Date: '.$row['recorddate'].PHP_EOL;
        echo 'Amount: '.$row['amount'].PHP_EOL;
        echo 'Account No: '.$row['destinationaccountno'].PHP_EOL;

        $tran_type = 'INWARD_BUFFER';
        $transmode_code = 'C';
        $funds_code = 'B';
        $data = $this->customer_details($conn, $row['destinationaccountno'], '566');
        $currency_name = $this->getCurrency($conn, '566');
        $bank_name = $this->getBankName($p_conn, $row['senderbankcode']);

        // var_dump($tran_type,$data,$currency_name); exit;
        //var_dump($currency_name);
       
        if (!empty($data) && !empty($currency_name)) {
            echo 'Preparing data bindings..'.PHP_EOL;
            $posted1 = explode(' ', $row['recorddate']);
            $dob1 = explode(' ', $data['DOB']);
            $dev1 = explode(' ', $data['ID_DELIVERY_DATE']);
            $reg1 = explode(' ', $data['REG_DATE']);

            $bind = [
                'T_ACCOUNT_NUMBER' => $row['destinationaccountno'],
                'T_TRANS_NUMBER' => $row['sopratranid'],
                'T_LOCATION' => 'HEAD OFFICE',
                'TRANSACTION_DESCRIPTION' => $row['narration'],
                'T_DATE' => date('d/m/Y H:i:s', strtotime($posted1[0])),
                'T_TELLER' => 'SYSTEM',
                'T_AUTHORIZED' => 'SYSTEM',
                'T_LATE_DEPOSIT' => 0,
                'T_DATE_POSTING' => date('d/m/Y H:i:s', strtotime($posted1[0])),
                'T_VALUE_DATE' => date('d/m/Y H:i:s', strtotime($posted1[0])),
                'T_TRANSMODE_CODE' => $transmode_code,
                'T_AMOUNT_LOCAL' => $row['amount'],
                'T_SOURCE_CLIENT_TYPE' => '',
                'T_SOURCE_TYPE' => '',
                'T_SOURCE_FUNDS_CODE' => $funds_code,
                'T_SOURCE_CURRENCY_CODE' => $currency_name['CURRENCY_NAME'],
                'T_SOURCE_FOREIGN_AMOUNT' => '',
                'T_SOURCE_EXCHANGE_RATE' => '',
                'T_SOURCE_COUNTRY' => 'NG',
                'T_SOURCE_INSTITUTION_CODE' => $row['senderbankcode'],
                'T_SOURCE_INSTITUTION_NAME' => $bank_name,
                'T_SOURCE_ACCOUNT_NUMBER' => $row['originatoraccountno'],
                'T_SOURCE_ACCOUNT_NAME' => $row['originatorname'],
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
                'T_DEST_INSTITUTION_CODE' => $row['destinationbankcode'],
                'T_DEST_INSTITUTION_NAME' => 'TAJ BANK',
                'T_DEST_ACCOUNT_NUMBER' => $row['destinationaccountno'],
                'T_DEST_ACCOUNT_NAME' => $row['accountname'],
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
                'ROW_REF' => $row['sopratranid'],
                'BRANCH_NAME' => $row['BRANCH_CODE'],
                
            ];

            $this->insert_ctr($cnt, $records, $bind, 'nip');

            echo '-----End-----------' . PHP_EOL;
        }
    }
}



    private function insert_ctr($cnt, $records, $bind, $tag = null)
{
    try {


        echo 'here';

        $this->p_conn = $this->db_connectPostgres();

        // The commit threshold value (e.g., 200)
        $commitThreshold = 200;

        // Insert into local database or use web service
        echo 'Inserting..' . $bind['TRAN_TYPE'] . PHP_EOL;

        // Prepare the INSERT statement
        $sql =  "INSERT INTO ctr_transactions (
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
    ROW_REF,
    BRANCH_NAME
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
    :ROW_REF,
    :BRANCH_NAME
)";

        // Now you can call the pg_db_execute function with the $p_conn object and binding data
        $this->pg_db_execute($this->p_conn, $sql, $bind);

        // Commit after every 200 inserts, free up logs, and lock resources
        if ($cnt % $commitThreshold === 0) {
            $this->p_conn->commit();
            echo 'Threshold reached.. committed' . PHP_EOL;
        }

        $records = $records + 1;

        echo 'Inserted..' . $bind['TRAN_TYPE'] . PHP_EOL;
        echo $bind['TRAN_TYPE'] .' Status: Inserted.. ' . $records . ' successfully' . PHP_EOL;

    } catch (\Throwable $e) {
        echo 'Loop ' . $cnt . ' Did not insert any record - Error: ' . $e->getMessage() . PHP_EOL;
    }
}

    // ... (The rest of the functions remain the same)

    private function db_connectPostgres()
    {
        $dsn = "pgsql:host=127.0.0.1;dbname=tajbank";
        $username = "postgres";
        $password = "Tajbank123_";

        $p_conn = new PDO($dsn, $username, $password);
        $p_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $p_conn;
    }

    // ... (The rest of the functions remain the same)


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
        try {
            $sql = "SELECT 
             b.pre FIRSTNAME
            ,b.nom LASTNAME
            ,b.sext SEX
            ,b.dna DOB
            ,b.viln TOWN
            ,b.depn STATE
            ,b.nrc RC_NUM
            ,b.nidf TAX_ID
            ,b.dou REG_DATE
            ,b.drc COMPANY_REG_DATE
            ,b.nid ID_NUMBER
            ,b.vid ID_EXPIRY_DATE
            ,b.did ID_DELIVERY_DATE
            ,b.nomrest FULLNAME
            ,g.lib1 TITLE
            ,b.nat NATIONALITY_CODE
            ,trim(c.dev) ACCOUNT_CURRENCY_CODE
            ,c.dou ACCOUNT_CREATED_DATE
            ,c.sin ACCOUNT_BALANCE
            ,f.adr1 || ' ' || f.adr2 || ' ' || f.adr3 ADDRESS
            ,d.valmt BVN
            ,e.num PHONE_NUMBER
            ,c.age BRANCH_CODE
            FROM tajprod.bkcli b, tajprod.bkcom c, tajprod.bkicli d, tajprod.bktelcli e, tajprod.bkadcli f , tajprod.bknom g
            WHERE (trim(b.cli)=trim(c.cli) and trim(c.dev) = trim('$currency') and trim(c.ncp) = trim('$account_num'))
                and trim(b.cli)=trim(d.cli) 
                and trim(b.cli)=trim(e.cli) 
                and trim(b.cli)=trim(f.cli) 
                and (trim(g.cacc)=trim(b.lib) and trim(ctab)='036') fetch first 1 rows only";
    
            $data = $this->db_query_one($conn, $sql);
            
            if (!empty($data)) {
               // var_dump($data);
                return $data;
            } else {
                // Handle the case when no data is found for the given account_num and currency.
                return null;
            }
        } catch (Exception $e) {
            // Handle any exceptions that occur during the execution of the SQL query.
            // You can log the error, return a specific error message, or take appropriate action.
            // For now, let's just log the error and return null.
            error_log("Error executing customer_details function: " . $e->getMessage());
            return null;
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
    try {
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
    } catch (Exception $e) {
        // Handle the exception (e.g., log it, display an error message, etc.)
        // For example, you can log the error and return null to indicate an error occurred.
        error_log('Error in db_query_one: ' . $e->getMessage());
        return null;
    }
}



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

        // Execute the prepared statement
        $data = $stmt->execute();

        // Close the cursor and the prepared statement
        $stmt->closeCursor();

        return $data;
    }
}
