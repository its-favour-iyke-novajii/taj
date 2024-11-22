<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class calloverTransactions extends Command
{
    protected $signature = 'update-callover';
    protected $description = 'Execute Oracle query and insert into PostgreSQL table';

    public function handle()
    {
        
        $host = '172.19.20.60';
        $user = 'novaji';
        $pwd = 'novali123';
        $port = '1521';
        $serviceName = 'tajrep';

        $conn = oci_connect($user, $pwd, "172.19.20.60:$port/$serviceName");

        if (!$conn) {
            $e = oci_error();
            trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
            return;
        }

        $pgsqlHost = '127.0.0.1';
        $pgsqlPort = '5432';
        $pgsqlDatabase = 'tajbank';
        $pgsqlUsername = 'postgres';
        $pgsqlPassword = 'Tajbank123_';

        $pgsqlConnection = pg_connect("host=$pgsqlHost port=$pgsqlPort dbname=$pgsqlDatabase user=$pgsqlUsername password=$pgsqlPassword");

        if (!$pgsqlConnection) {
            die("Error in connection: " . pg_last_error());
        }

        $query = " SELECT
        rowid as row_id,
        trim(agsa) as seizure_agency_code,
        (SELECT lib FROM tajprod.bkage WHERE age = a.agsa) as seizure_agency_name,
        trim(age) as agency_code,
        (SELECT lib FROM tajprod.bkage WHERE age = a.age) as agency_name,
        trim(ope) as transaction_code,
        trim(eve) as event_number,
        trim(age1) as account_branch_code1,
        trim(dev1) as account_currency_code1,
        trim(ncp1) as account_debited,
        trim(ncp2) as account_credited,
        trim(clc1) as control_key_credited,
        trim(clc2) as control_key_debited,
        trim(cli1) as customer_code_debited,
        trim(cli2) as customer_code_credited,
        trim(nom1) as customer_name_debited,
        trim(nom2) as customer_name_credited,
        trim(ges1) as account_mgr_debited,
        trim(ges2) as account_mgr_credited,
        trim(sen1) as direction_of_account1,
        trim(sen2) as direction_of_account2,
        mht1 as nominal_amount_debited,
        mht2 as nominal_amount_credited,
        mon1 as net_amount_debited,
        mon2 as net_amount_credited,
        exo1 as debit_acct_commission_exempted,
        exo2 as credit_acct_commission_exempted,
        sol1 as debited_account_balance,
        sol2 as credited_account_balance,
        trim(dev) as tran_currency_code,
        mnat as lcy_value,
        trim(nat) as nature_of_transaction,
        dou as creation_date,
        dco as accounting_date,
        eta as transaction_status,
        trim(uti) as initiated_by,
        (SELECT lib FROM tajprod.evuti WHERE cuti=a.uti AND rownum=1) as initiator,
        (SELECT lib FROM tajprod.evuti WHERE cuti=a.uta AND rownum=1) as approver1,
        (SELECT lib FROM tajprod.evuti WHERE cuti=a.utf AND rownum=1) as approver2,
        CASE
            WHEN NAT = 'RETESP' THEN NOMP
            WHEN NAT = 'RCHBSP' THEN lib3
            WHEN NAT = 'VERESP' THEN lib2
            ELSE lib1
        END as narration,
        lib1 as narration1,
        lib2 as narration2,
        orig as origin_of_transaction,
        dech as due_date,
        csp1 as specific_code,
        exof as waive_commission,
        img as image_present,
        dsai as entry_date,
        hsai as entry_time,
        manda as representative
    FROM
        tajprod.bkheve a
    WHERE
        trim(uti)  in  (select trim(cuti) from tajprod.evuti) and dsai > sysdate - 30";
        

        $oracleStatement = oci_parse($conn, $query);
        oci_execute($oracleStatement);

 // ... (previous code)

while ($result = oci_fetch_assoc($oracleStatement)) {
    // Clean up DESCRIPTION value by removing non-alphanumeric characters and trailing spaces
    //$cleanedDescription = preg_replace('/[^a-zA-Z0-9\s]/', '', $result['DESCRIPTION']);
    //$cleanedDescription = trim($cleanedDescription);

   // echo $result['ACCOUNT_TYPE'];
   // echo $result['CURRENT_BALANCE'];

   var_dump($result);

               // Insert data into PostgreSQL table using parameterized query
               $pgsqlInsertQuery = "INSERT INTO am_callover_transactions (
                Seizure_Agency_Code, Seizure_Agency_Name,Agency_Code, Agency_Name,Transaction_Code,Event_Number,Account_Branch_Code1,Account_Currency_Code1,Account_Debited,Account_Credited,
                Control_Key_Credited,Control_Key_Debited,Customer_Code_Debited,Customer_Code_Credited,Customer_Name_Debited,Customer_Name_Credited,Account_Mgr_Debited,Account_Mgr_Credited,Direction_of_account1,Direction_of_account2,
                Nominal_Amount_debited,Nominal_Amount_credited, Net_Amount_Debited, Net_Amount_Credited, Debit_Acct_Commission_Exempted,Credit_Acct_Commission_Exempted,Debited_Account_Balance,Credited_Account_Balance,Tran_Currency_Code,
                LCY_Value,Nature_of_Transaction, Creation_Date, Accounting_Date, Transaction_Status, Initiated_By, INITIATOR, APPROVER1, APPROVER2, Narration, Narration1, Narration2, Origin_of_Transaction, Due_date, Specific_Code,
                Waive_Commission,Image_Present,Entry_Date,Entry_Time,Representative
                        ) VALUES (
                            $1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14, $15, $16, $17, $18, $19, $20,$21, $22, $23, $24, $25, $26, $27, $28, $29, $30, $31, $32, $33, $34, $35, $36, $37, $38, $39, $40,
                            $41, $42, $43, $44, $45, $46, $47, $48, $49
                        )";
            
                        // Prepare parameters array
                        $params = [
                            $result['SEIZURE_AGENCY_CODE'], $result['SEIZURE_AGENCY_NAME'],
                            $result['AGENCY_CODE'], $result['AGENCY_NAME'], $result['TRANSACTION_CODE'],
                            $result['EVENT_NUMBER'], $result['ACCOUNT_BRANCH_CODE1'], $result['ACCOUNT_CURRENCY_CODE1'],
                            $result['ACCOUNT_DEBITED'], $result['ACCOUNT_CREDITED'], $result['CONTROL_KEY_CREDITED'],
                            $result['CONTROL_KEY_DEBITED'], $result['CUSTOMER_CODE_DEBITED'], $result['CUSTOMER_CODE_CREDITED'],
                            $result['CUSTOMER_NAME_DEBITED'], $result['CUSTOMER_NAME_CREDITED'], $result['ACCOUNT_MGR_DEBITED'],
                            $result['ACCOUNT_MGR_CREDITED'], $result['DIRECTION_OF_ACCOUNT1'], $result['DIRECTION_OF_ACCOUNT2'],
                            $result['NOMINAL_AMOUNT_DEBITED'], $result['NOMINAL_AMOUNT_CREDITED'], $result['NET_AMOUNT_DEBITED'],
                            $result['NET_AMOUNT_CREDITED'], $result['DEBIT_ACCT_COMMISSION_EXEMPTED'],
                            $result['CREDIT_ACCT_COMMISSION_EXEMPTED'], $result['DEBITED_ACCOUNT_BALANCE'],
                            $result['CREDITED_ACCOUNT_BALANCE'], $result['TRAN_CURRENCY_CODE'], $result['LCY_VALUE'],
                            $result['NATURE_OF_TRANSACTION'], $result['CREATION_DATE'], $result['ACCOUNTING_DATE'],
                            $result['TRANSACTION_STATUS'], $result['INITIATED_BY'], $result['INITIATOR'],
                            $result['APPROVER1'], $result['APPROVER2'], $result['NARRATION'], $result['NARRATION1'],
                            $result['NARRATION2'], $result['ORIGIN_OF_TRANSACTION'], $result['DUE_DATE'],
                            $result['SPECIFIC_CODE'], $result['WAIVE_COMMISSION'], $result['IMAGE_PRESENT'],
                            $result['ENTRY_DATE'], $result['ENTRY_TIME'], $result['REPRESENTATIVE']
                        ];

            echo $pgsqlInsertQuery;

            // Execute the parameterized query
            pg_query_params($pgsqlConnection, $pgsqlInsertQuery, $params);



}

// ... (remaining code)


        oci_free_statement($oracleStatement);
        oci_close($conn);

        pg_close($pgsqlConnection);

        $this->info('Query executed and results inserted into PostgreSQL table successfully.');
    }
}
