<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class updateAgentsTransactions extends Command
{
    protected $signature = 'update-agents-transactions';
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

        $query = "select  a.agsa as branch_code, b.cli as customer_number, b.cha as gl_code,
            (SELECT TRIM(cacc) FROM tajprod.bknom WHERE ctab = 2 AND mnt2 = a.dev) as currency,
            a.dsai as transaction_date, a.hsai as transaction_time, a.eve as tran_id, a.dou as value_date, a.ncp1 as account_number, trim(b.inti) as account_type, trim(a.nom1) as customer_name, trim(a.ncp2) as account_number_credited, a.nom2 as account_name_credited,
            a.mon1 as transaction_amount, to_char(a.sol1-a.mht1,'9,999,999,999.99') as current_balance, a.eta as transaction_status, a.uti as initiator, a.utf as authorizer_i, a.uta as authorizer_ii,
            a.sen1 as tran_type, a.nat as transaction_nature,
            case when nat='VERESP' THEN a.lib2 when nat='RCHBSP' then a.lib3 else a.lib1 end as description
        from tajprod.bkeve a, tajprod.bkcom b
        where a.mon1 >= 500000 and a.ncp1 = b.ncp and a.age1 = b.age AND (trim(b.cha) in ('407016', '202008', '405090', '201004', '204062', '405019', '202001', '103812', '209238', '204056', '204046', '201005', '103810', '204074', '405083')) and a.dev1 = b.dev 
        and a.eta in ('VA','VF','FO') and a.nat != 'AGEVIR' and a.dou >= trunc(sysdate)";
        

        $oracleStatement = oci_parse($conn, $query);
        oci_execute($oracleStatement);

 // ... (previous code)

while ($result = oci_fetch_assoc($oracleStatement)) {
    // Clean up DESCRIPTION value by removing non-alphanumeric characters and trailing spaces
    $cleanedDescription = preg_replace('/[^a-zA-Z0-9\s]/', '', $result['DESCRIPTION']);
    $cleanedDescription = trim($cleanedDescription);

   // echo $result['ACCOUNT_TYPE'];
   // echo $result['CURRENT_BALANCE'];

               // Insert data into PostgreSQL table using parameterized query
               $pgsqlInsertQuery = "INSERT INTO agent_tnx_above_500k (
                branch_code, customer_number, gl_code, currency, transaction_date, transaction_time, tran_id,
                value_date, account_number, account_type, customer_name, account_number_credited, account_name_credited,
                transaction_amount, current_balance, transaction_status, initiator, authorizer_i, authorizer_ii,
                tran_type, transaction_nature, description
            ) VALUES (
                $1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14, $15, $16, $17, $18, $19, $20, $21, $22
            ) ON CONFLICT (tran_id) DO NOTHING";

            // Prepare parameters array
            $params = [
                $result['BRANCH_CODE'], $result['CUSTOMER_NUMBER'], $result['GL_CODE'],
                $result['CURRENCY'], $result['TRANSACTION_DATE'], $result['TRANSACTION_TIME'],
                $result['TRAN_ID'], $result['VALUE_DATE'], $result['ACCOUNT_NUMBER'],
                $result['ACCOUNT_TYPE'], $result['CUSTOMER_NAME'], $result['ACCOUNT_NUMBER_CREDITED'],
                $result['ACCOUNT_NAME_CREDITED'], $result['TRANSACTION_AMOUNT'], $result['CURRENT_BALANCE'],
                $result['TRANSACTION_STATUS'], $result['INITIATOR'], $result['AUTHORIZER_I'],
                $result['AUTHORIZER_II'], $result['TRAN_TYPE'], $result['TRANSACTION_NATURE'],
                $cleanedDescription
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
