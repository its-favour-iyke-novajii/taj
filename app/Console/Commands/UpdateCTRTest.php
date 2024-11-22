<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateCTRTest extends Command
{
    protected $signature = 'ctr:update-with-tran-type {queryType}';
    protected $description = 'Connect to Oracle, fetch rows, and insert into PostgreSQL';

    public function handle()
    {
        $this->info("Starting the data transfer process.");
        
        $queryType = $this->argument('queryType');
        
        // Database credentials from environment variables
        $oracleHost = env('ORACLE_DB_HOST', '172.19.20.60');
        $oracleUser = env('ORACLE_DB_USER', 'novaji');
        $oraclePwd = env('ORACLE_DB_PASSWORD', 'novali123');
        $oraclePort = '1521';
        $oracleServiceName = 'tajrep';
        
        $oracleConnectionString = "(DESCRIPTION =(ADDRESS = (PROTOCOL = TCP)(HOST = $oracleHost)(PORT = $oraclePort))(CONNECT_DATA =(SERVER = DEDICATED)(SERVICE_NAME=$oracleServiceName)))";
        
        // Connect to Oracle database
        $oracleConn = oci_connect($oracleUser, $oraclePwd, $oracleConnectionString);
        if (!$oracleConn) {
            $error = oci_error();
            Log::error("Failed to connect to Oracle database: " . $error['message']);
            return 1;
        }

        // PostgreSQL connection configuration
        $postgresConn = pg_connect("host=127.0.0.1 port=5432 dbname=tajbank user=postgres password=Tajbank123_");
        if (!$postgresConn) {
            Log::error("Failed to connect to PostgreSQL database.");
            oci_close($oracleConn);
            return 1;
        }

        // SQL Queries for different query types
        if ($queryType === 'outflow') {
            $sqlQuery = $this->getOutflowSQL();
        } elseif ($queryType === 'inflow') {
            $sqlQuery = $this->getInflowSQL();
        } else {
            Log::error("Invalid query type: $queryType");
            oci_close($oracleConn);
            pg_close($postgresConn);
            return 1;
        }

        // Parse and execute Oracle SQL query
        $queryStmt = oci_parse($oracleConn, $sqlQuery);
        if (!oci_execute($queryStmt)) {
            $error = oci_error($queryStmt);
            Log::error("Oracle query failed: " . $error['message']);
            oci_close($oracleConn);
            pg_close($postgresConn);
            return 1;
        }

        // Prepare PostgreSQL insert query
        $insertQuery = "
        INSERT INTO ctr_transactions (
            branch_name, t_source_currency_code, t_trans_number, t_account_number, 
            t_source_account_number, t_authorized, t_dest_account_number, 
            t_source_account_name, t_source_person_first_name, t_source_person_last_name, 
            t_dest_person_first_name, t_dest_person_last_name, t_dest_account_name, 
            t_dest_currency_code, t_amount_local, t_source_foreign_amount, t_dest_foreign_amount, 
            t_source_institution_code, t_source_institution_name, t_dest_institution_code, 
            t_dest_institution_name, t_source_country, t_dest_exchange_rate, t_source_exchange_rate, 
            tran_type, transaction_description, t_date, t_value_date
        ) VALUES (
            $1, $2, $3, $4, $5, $6, $7, $8, 
            $9, $10, $11, $12, $13, $14, $15, $16, 
            $17, $18, $19, $20, $21, $22, $23, $24, 
            $25, $26, $27, $28
        );";

        $insertStmt = pg_prepare($postgresConn, "insert_query", $insertQuery);
        $insertCount = 0;

        // Loop through Oracle result set and insert into PostgreSQL
        while ($row = oci_fetch_assoc($queryStmt)) {
            // For debugging purposes, you can log the entire row to check its structure
            // Log::info("Row data: " . print_r($row, true));

            // Extract values and prepare them for insertion into PostgreSQL
            $values = array_values($row);
            
            // Ensure you have the correct number of values (34 columns)
            Log::info("Number of values to insert: " . count($values));

            // Insert into PostgreSQL
            $result = pg_execute($postgresConn, "insert_query", $values);
            if (!$result) {
                $error = pg_last_error($postgresConn);
                Log::error("PostgreSQL insert failed: " . $error);
            } else {
                $insertCount++;
                $this->info("Inserted row number: $insertCount");
            }
        }

        // Close the Oracle and PostgreSQL connections
        oci_free_statement($queryStmt);
        oci_close($oracleConn);
        pg_close($postgresConn);

        $this->info("Data transfer process completed with $insertCount rows inserted.");
        return 0;
    }

    private function getOutflowSQL()
    {
        return "SELECT
            (SELECT lib FROM tajprod.bkage WHERE age = (SELECT age FROM tajprod.bkcom WHERE trim(ncp) = trim(a.ncp1) AND ROWNUM = 1)) branch_name,
            
            a.dev1 t_source_currency_code,
            
            a.eve t_trans_number,
            
            trim(a.ncp1) t_account_number,
            
            trim(a.ncp1) t_source_account_number,
            
            (SELECT j.lib FROM tajprod.evuti j WHERE trim(j.cuti) = trim(a.utf) AND ROWNUM = 1) t_authorized,
            
            trim(a.ncp2) t_dest_account_number,
            
            trim(a.nom1) t_source_account_name,
            
            TRIM(SUBSTR(a.nom1, 1, INSTR(a.nom1, ' ') - 1)) AS t_source_person_first_name,
            
            TRIM(SUBSTR(a.nom1, INSTR(a.nom1, ' ') + 1)) AS t_source_person_last_name,
            
            TRIM(SUBSTR(a.nom2, 1, INSTR(a.nom2, ' ') - 1)) AS t_dest_person_first_name,
            
            TRIM(SUBSTR(a.nom2, INSTR(a.nom2, ' ') + 1)) AS t_dest_person_last_name,
            
            trim(a.nom2) t_dest_account_name,
            
            a.dev2 t_dest_currency_code,
            
            a.mnat t_amount_local,
            
            a.mht t_source_foreign_amount,
            
            a.mht t_dest_foreign_amount,
            
            '000026' t_source_institution_code,
            
            'Tajbank' t_source_institution_name,
            
            '000026' t_dest_institution_code,
            
            'Tajbank' t_dest_institution_name,
            
            (SELECT TRIM(NAT) FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli1) AND ROWNUM = 1) t_source_country,
            
            a.tcai2 t_dest_exchange_rate,
            
            a.tcai3 t_source_exchange_rate,
            
            CASE 
               WHEN trim(a.NAT) = 'RETDEV' THEN 'FOREIGN_WITHDRAWAL'
               WHEN trim(a.NAT) = 'RPCMON' THEN 'FOREIGN_WITHDRAWAL'
               WHEN trim(a.NAT) = 'RECMON' THEN 'FOREIGN_WITHDRAWAL'
               WHEN trim(a.NAT) = 'RETMON' THEN 'FOREIGN_WITHDRAWAL'
               WHEN trim(a.NAT) = 'RETESP' THEN 'LOCAL_WITHDRAWAL'
               WHEN TRIM(a.cli1) IS NOT NULL AND TRIM(a.cli2) IS NOT NULL THEN 'ACCOUNT_TO_ACCOUNT'
            ELSE a.NAT  -- Default to the value of NAT if no match found
               END AS Tran_Type,
            
            Decode(a.NAT, 'RETESP', a.NOMP, 'RCHBSP', a.lib3, 'VERESP', a.lib2, a.lib1) transaction_description,
            
            a.dsai t_date,
            
            a.dsai t_value_date
        FROM tajprod.bkeve a
        WHERE ((trim(a.dev) = '566' AND a.mnat >= 5000000) 
            OR (trim(a.dev) != '566' AND a.mht >= 10000)) 
            AND a.dsai >= trunc(sysdate) AND a.nat in ('RETDEV', 'RPCMON', 'RECMON', 'RETMON', 'RETESP' )";
    }

    private function getInflowSQL()
    {
        return "SELECT
            (SELECT lib FROM tajprod.bkage WHERE age = (SELECT age FROM tajprod.bkcom WHERE trim(ncp) = trim(a.ncp2) AND ROWNUM = 1)) branch_name,
            
            a.dev1 t_source_currency_code,
            
            a.eve t_trans_number,
            
            trim(a.ncp2) t_account_number,
            
            trim(a.ncp1) t_source_account_number,
            
            (SELECT j.lib FROM tajprod.evuti j WHERE trim(j.cuti) = trim(a.utf) AND rownum = 1) t_authorized,
            
            trim(a.ncp2) t_dest_account_number,
            
            trim(a.nom1) t_source_account_name,
            
            TRIM(SUBSTR(a.nom1, 1, INSTR(a.nom1, ' ') - 1)) AS t_source_person_first_name,
            
            TRIM(SUBSTR(a.nom1, INSTR(a.nom1, ' ') + 1)) AS t_source_person_last_name,
            
            TRIM(SUBSTR(a.nom2, 1, INSTR(a.nom2, ' ') - 1)) AS t_dest_person_first_name,
            
            TRIM(SUBSTR(a.nom2, INSTR(a.nom2, ' ') + 1)) AS t_dest_person_last_name,
            
            trim(a.nom2) t_dest_account_name,
            
            a.dev2 t_dest_currency_code,
            
            a.mnat t_amount_local,
            
            a.mht t_source_foreign_amount,
            
            a.mht t_dest_foreign_amount,
            
            '000026' t_source_institution_code,
            
            'Tajbank' t_source_institution_name,
            
            '000026' t_dest_institution_code,
            
            'Tajbank' t_dest_institution_name,
            
              (SELECT TRIM(NAT) FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli1) AND ROWNUM = 1) t_source_country,
            
            a.tcai2 t_dest_exchange_rate,
            
            a.tcai3 t_source_exchange_rate,
            
            
            CASE 
                WHEN trim(a.NAT) = 'VERESP' THEN 'LOCAL_DEPOSIT'
                WHEN trim(a.NAT) = 'VERDEV' THEN 'FOREIGN_DEPOSIT'
                WHEN trim(a.NAT) = 'VIRMAG' AND a.TYP = '110' AND a.DEVF != '566' THEN 'FX_INWARD'
                WHEN TRIM(a.cli1) IS NOT NULL AND TRIM(a.cli2) IS NOT NULL THEN 'ACCOUNT_TO_ACCOUNT'
            ELSE a.NAT 
               END AS Tran_Type,
            
            Decode(a.NAT, 'RETESP', a.NOMP, 'RCHBSP', a.lib3, 'VERESP', a.lib2, a.lib1) transaction_description,
            
            a.dsai t_date,
            
            a.dsai t_value_date
        FROM tajprod.bkeve a
            WHERE ((trim(a.dev) = '566' AND a.mnat >= 5000000) 
            OR (trim(a.dev) != '566' AND a.mht >= 10000))  
            AND a.dsai >= trunc(sysdate) AND a.nat in ('VERESP', 'VERDEV', 'VIRMAG')";
    }
}
