<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateFTR extends Command
{
    protected $signature = 'update-ftr {queryType}';
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

        // Define your SQL query
        $sqlQuery = "
            SELECT
                (SELECT TRIM(lib) FROM tajprod.bkage WHERE age = a.age) AS BRANCH_NAME,
                TRIM(a.age) AS BRANCH_CODE,
                ' ' AS CUSTOMER_TYPE,
                (SELECT TRIM(valmt) FROM tajprod.bkicli c WHERE cli = b.cli AND TRIM(valmt) IS NOT NULL AND ROWNUM = 1) AS BANK_VERIFICATION_NUMBER,
                (SELECT TRIM(nidf) FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli1)) AS TAX_IDENTIFICATION_NUMBER,
                '' AS CCI_NUMBER,
                CASE 
                    WHEN (SELECT TRIM(nidf) FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli1)) IS NOT NULL 
                    THEN TRIM((SELECT TRIM(nom) FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli1))) 
                    ELSE '' 
                END AS NAME_OF_ORGANISATION,
                (SELECT TRIM(pre) FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli1)) AS FIRSTNAME,
                (SELECT TRIM(midname) FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli1)) AS MIDDLENAME,
                CASE 
                    WHEN (SELECT TRIM(nidf) FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli1)) IS NULL 
                    THEN TRIM((SELECT TRIM(nom) FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli1)))
                    ELSE ' '
                END AS LASTNAME,
                (SELECT TRIM(NAT) FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli1)) AS NATIONALITY,
                 CASE 
                    WHEN (SELECT TRIM(nidf) FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli1)) IS NOT NULL 
                    THEN (SELECT drc FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli1)) 
                    ELSE (SELECT dna FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli1))
                END AS DATE_OF_BIRTH_OR_DATE_OF_INCORPORATION,
                (SELECT TRIM(MET) FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli1)) AS OCCUPATION_OR_LINE_OF_BUSINESS,
                (SELECT tid FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli1)) AS TYPE_OF_IDENTIFICATION,
                CASE 
                    WHEN (SELECT TRIM(nidf) FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli1)) IS NULL 
                    THEN TRIM((SELECT TRIM(nid) FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli1)))
                    ELSE TRIM((SELECT TRIM(nrc) FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli1)))
                END AS IDENTIFICATION_NO,
                (SELECT did FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli1)) AS DATE_OF_ISSUE,
                (SELECT TRIM(VID) FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli1)) AS EXPIRY_DATE,
                (SELECT oid FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli1)) AS ISSUE_AUTHORITY,
                '' AS CUSTOMER_ADDRESS_TYPE,
                (SELECT TRIM(adr1) || ',' || TRIM(adr2) || ',' || TRIM(adr3) FROM tajprod.bkadcli f WHERE TRIM(b.cli) = TRIM(f.cli) AND ROWNUM = 1) AS FULL_ADDRESS,
                (SELECT TRIM(ville) FROM tajprod.bkadcli f WHERE TRIM(b.cli) = TRIM(f.cli) AND ROWNUM = 1) AS CITY,
                (SELECT TRIM(adr3) FROM tajprod.bkadcli f WHERE TRIM(b.cli) = TRIM(f.cli) AND ROWNUM = 1) AS LOCAL_GOVERNMENT,
                (SELECT TRIM(dep) FROM tajprod.bkadcli f WHERE TRIM(b.cli) = TRIM(f.cli) AND ROWNUM = 1) AS STATE,
                (SELECT TRIM(num) FROM tajprod.bktelcli g WHERE TRIM(b.cli) = TRIM(g.cli) AND ROWNUM = 1) AS TELEPHONE,
                (SELECT TRIM(email) FROM tajprod.bkemacli g WHERE TRIM(b.cli) = TRIM(g.cli) AND ROWNUM = 1) AS EMAIL,
                b.inti AS ACCOUNT_TYPE,
                TRIM(a.nom1) AS ACCOUNT_NAME,
                TRIM(a.ncp1) AS ACCOUNT_NUMBER,
                TRIM(b.dou) AS DATE_OF_ACCOUNT_OPENING,
                '' AS LINKED_CONNECTED_ACCOUNTS,
                a.eve AS REFERENCE_NUMBER,
                TO_CHAR(a.dsai, 'YYYY-MM-DD') AS TRANSACTION_DATE,
                'OUTFLOW' AS TRANSACTION_TYPE,
                DECODE(NAT,'RETESP',NOMP,'RCHBSP',lib3,'VERESP',lib2,lib1) AS TRANSACTION_DETAILS,
                a.dev AS CURRENCY_TYPE,
                a.mht1 AS AMOUNT,
                CASE 
                    WHEN (TRIM(a.nat) IN ('VERDEV', 'RETDEV'))
                    THEN 'CASH'
                    ELSE 'TRANSFER' 
                END AS MODE_OF_TRANSACTION,
                DECODE(NAT,'RETESP',NOMP,'RCHBSP',lib3,'VERESP',lib2,lib1) AS PURPOSE_OF_TRANSACTION,
                DECODE(NAT,'RETESP',NOMP,'RCHBSP',lib3,'VERESP',lib2,lib1) AS SOURCE_ORIGIN_OF_FUNDS,
                TRIM(a.nom2) AS NAME_OF_BENEFICIARY,
                TRIM(a.ncp2) AS BENEFICIARY_ACCOUNT_NO,
                ' ' AS ADDRESS_OF_BENEFICIARY,
                TRIM(a.nom1) AS NAME_OF_SENDER,
                (SELECT TRIM(adr1) || ',' || TRIM(adr2) || ',' || TRIM(adr3) FROM tajprod.bkadcli f WHERE TRIM(b.cli) = TRIM(f.cli) AND ROWNUM = 1) AS ADDRESS_OF_SENDER,
                '' AS NAME_OF_CORRESPONDENCE_BANK,
                DECODE(NAT,'RETESP',NOMP,'RCHBSP',lib3,'VERESP',lib2,lib1) AS REMARKS      
            FROM
                tajprod.bkheve a
            JOIN tajprod.bkcom b ON a.ncp1 = b.ncp
            WHERE
                TRIM(a.cli1) IS NOT NULL AND
                a.dev != '566'
                AND a.dev IS NOT NULL
                AND a.mht1 >= 10000
                AND a.dsai >= TRUNC(SYSDATE) - 2 AND a.nat not in ('AGEVER', 'AGERET')
        ";
        
        
        if ($queryType === 'outflow') {
            $sqlQuery = "  SELECT
                (SELECT TRIM(lib) FROM tajprod.bkage WHERE age = b.age) AS BRANCH_NAME,
                TRIM(b.age) AS BRANCH_CODE,
                ' ' AS CUSTOMER_TYPE,
                (SELECT TRIM(valmt) FROM tajprod.bkicli c WHERE cli = b.cli AND TRIM(valmt) IS NOT NULL AND ROWNUM = 1) AS BANK_VERIFICATION_NUMBER,
                (SELECT TRIM(nidf) FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli1)) AS TAX_IDENTIFICATION_NUMBER,
                '' AS CCI_NUMBER,
                CASE 
                    WHEN (SELECT TRIM(nidf) FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli1)) IS NOT NULL 
                    THEN TRIM((SELECT TRIM(nom) FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli1))) 
                    ELSE '' 
                END AS NAME_OF_ORGANISATION,
                (SELECT TRIM(pre) FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli1)) AS FIRSTNAME,
                (SELECT TRIM(midname) FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli1)) AS MIDDLENAME,
                CASE 
                    WHEN (SELECT TRIM(nidf) FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli1)) IS NULL 
                    THEN TRIM((SELECT TRIM(nom) FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli1)))
                    ELSE ' '
                END AS LASTNAME,
                (SELECT TRIM(NAT) FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli1)) AS NATIONALITY,
                 CASE 
                    WHEN (SELECT TRIM(nidf) FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli1)) IS NOT NULL 
                    THEN (SELECT drc FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli1)) 
                    ELSE (SELECT dna FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli1))
                END AS DATE_OF_BIRTH_OR_DATE_OF_INCORPORATION,
                (SELECT TRIM(SMET) FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli1)) AS OCCUPATION_OR_LINE_OF_BUSINESS,
                (SELECT tid FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli1)) AS TYPE_OF_IDENTIFICATION,
                CASE 
                    WHEN (SELECT TRIM(nidf) FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli1)) IS NULL 
                    THEN TRIM((SELECT TRIM(nid) FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli1)))
                    ELSE TRIM((SELECT TRIM(nrc) FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli1)))
                END AS IDENTIFICATION_NO,
                (SELECT did FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli1)) AS DATE_OF_ISSUE,
                (SELECT TRIM(VID) FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli1)) AS EXPIRY_DATE,
                (SELECT oid FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli1)) AS ISSUE_AUTHORITY,
                '' AS CUSTOMER_ADDRESS_TYPE,
                (SELECT TRIM(adr1) || ',' || TRIM(adr2) || ',' || TRIM(adr3) FROM tajprod.bkadcli f WHERE TRIM(b.cli) = TRIM(f.cli) AND ROWNUM = 1) AS FULL_ADDRESS,
                (SELECT TRIM(ville) FROM tajprod.bkadcli f WHERE TRIM(b.cli) = TRIM(f.cli) AND ROWNUM = 1) AS CITY,
                (SELECT TRIM(adr3) FROM tajprod.bkadcli f WHERE TRIM(b.cli) = TRIM(f.cli) AND ROWNUM = 1) AS LOCAL_GOVERNMENT,
                (SELECT TRIM(dep) FROM tajprod.bkadcli f WHERE TRIM(b.cli) = TRIM(f.cli) AND ROWNUM = 1) AS STATE,
                (SELECT TRIM(num) FROM tajprod.bktelcli g WHERE TRIM(b.cli) = TRIM(g.cli) AND ROWNUM = 1) AS TELEPHONE,
                (SELECT TRIM(email) FROM tajprod.bkemacli g WHERE TRIM(b.cli) = TRIM(g.cli) AND ROWNUM = 1) AS EMAIL,
                b.inti AS ACCOUNT_TYPE,
                TRIM(a.nom1) AS ACCOUNT_NAME,
                TRIM(a.ncp1) AS ACCOUNT_NUMBER,
                TRIM(b.dou) AS DATE_OF_ACCOUNT_OPENING,
                '' AS LINKED_CONNECTED_ACCOUNTS,
                a.eve AS REFERENCE_NUMBER,
                TO_CHAR(a.dsai, 'YYYY-MM-DD') AS TRANSACTION_DATE,
                'OUTFLOW' AS TRANSACTION_TYPE,
                DECODE(NAT,'RETESP',NOMP,'RCHBSP',lib3,'VERESP',lib2,lib1) AS TRANSACTION_DETAILS,
                a.dev AS CURRENCY_TYPE,
                a.mht1 AS AMOUNT,
                CASE 
                    WHEN (TRIM(a.nat) IN ('VERDEV', 'RETDEV'))
                    THEN 'CASH'
                    ELSE 'TRANSFER' 
                END AS MODE_OF_TRANSACTION,
                DECODE(NAT,'RETESP',NOMP,'RCHBSP',lib3,'VERESP',lib2,lib1) AS PURPOSE_OF_TRANSACTION,
                DECODE(NAT,'RETESP',NOMP,'RCHBSP',lib3,'VERESP',lib2,lib1) AS SOURCE_ORIGIN_OF_FUNDS,
                TRIM(a.nom2) AS NAME_OF_BENEFICIARY,
                TRIM(a.ncp2) AS BENEFICIARY_ACCOUNT_NO,
                ' ' AS ADDRESS_OF_BENEFICIARY,
                TRIM(a.nom1) AS NAME_OF_SENDER,
                (SELECT TRIM(adr1) || ',' || TRIM(adr2) || ',' || TRIM(adr3) FROM tajprod.bkadcli f WHERE TRIM(b.cli) = TRIM(f.cli) AND ROWNUM = 1) AS ADDRESS_OF_SENDER,
                '' AS NAME_OF_CORRESPONDENCE_BANK,
                DECODE(NAT,'RETESP',NOMP,'RCHBSP',lib3,'VERESP',lib2,lib1) AS REMARKS      
            FROM
                tajprod.bkheve a
            JOIN tajprod.bkcom b ON a.ncp1 = b.ncp
            WHERE
                TRIM(a.cli1) IS NOT NULL AND
                a.dev != '566'
                AND a.dev IS NOT NULL
                AND a.mht1 >= 10000
                AND a.dsai >= TRUNC(SYSDATE) - 1 AND a.nat not in ('AGEVER', 'AGERET')"; 
        } elseif ($queryType === 'inflow') {
            $sqlQuery = "SELECT
                (SELECT TRIM(lib) FROM tajprod.bkage WHERE age = b.age) AS BRANCH_NAME,
                TRIM(b.age) AS BRANCH_CODE,
                ' ' AS CUSTOMER_TYPE,
                (SELECT TRIM(valmt) FROM tajprod.bkicli c WHERE cli = b.cli AND TRIM(valmt) IS NOT NULL AND ROWNUM = 1) AS BANK_VERIFICATION_NUMBER,
                (SELECT TRIM(nidf) FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli2)) AS TAX_IDENTIFICATION_NUMBER,
                '' AS CCI_NUMBER,
                CASE 
                    WHEN (SELECT TRIM(nidf) FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli2)) IS NOT NULL 
                    THEN TRIM((SELECT TRIM(nom) FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli2))) 
                    ELSE '' 
                END AS NAME_OF_ORGANISATION,
                (SELECT TRIM(pre) FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli2)) AS FIRSTNAME,
                (SELECT TRIM(midname) FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli2)) AS MIDDLENAME,
                CASE 
                    WHEN (SELECT TRIM(nidf) FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli2)) IS NULL 
                    THEN TRIM((SELECT TRIM(nom) FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli2)))
                    ELSE ' '
                END AS LASTNAME,
                (SELECT TRIM(NAT) FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli2)) AS NATIONALITY,
                 CASE 
                    WHEN (SELECT TRIM(nidf) FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli2)) IS NOT NULL 
                    THEN (SELECT drc FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli2)) 
                    ELSE (SELECT dna FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli2))
                END AS DATE_OF_BIRTH_OR_DATE_OF_INCORPORATION,
                (SELECT TRIM(SMET) FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli2)) AS OCCUPATION_OR_LINE_OF_BUSINESS,
                (SELECT tid FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli2)) AS TYPE_OF_IDENTIFICATION,
                CASE 
                    WHEN (SELECT TRIM(nidf) FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli2)) IS NULL 
                    THEN TRIM((SELECT TRIM(nid) FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli2)))
                    ELSE TRIM((SELECT TRIM(nrc) FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli2)))
                END AS IDENTIFICATION_NO,
                (SELECT did FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli2)) AS DATE_OF_ISSUE,
                (SELECT TRIM(VID) FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli2)) AS EXPIRY_DATE,
                (SELECT oid FROM tajprod.bkcli WHERE TRIM(cli) = TRIM(a.cli2)) AS ISSUE_AUTHORITY,
                '' AS CUSTOMER_ADDRESS_TYPE,
                (SELECT TRIM(adr1) || ',' || TRIM(adr2) || ',' || TRIM(adr3) FROM tajprod.bkadcli f WHERE TRIM(b.cli) = TRIM(f.cli) AND ROWNUM = 1) AS FULL_ADDRESS,
                (SELECT TRIM(ville) FROM tajprod.bkadcli f WHERE TRIM(b.cli) = TRIM(f.cli) AND ROWNUM = 1) AS CITY,
                (SELECT TRIM(adr3) FROM tajprod.bkadcli f WHERE TRIM(b.cli) = TRIM(f.cli) AND ROWNUM = 1) AS LOCAL_GOVERNMENT,
                (SELECT TRIM(dep) FROM tajprod.bkadcli f WHERE TRIM(b.cli) = TRIM(f.cli) AND ROWNUM = 1) AS STATE,
                (SELECT TRIM(num) FROM tajprod.bktelcli g WHERE TRIM(b.cli) = TRIM(g.cli) AND ROWNUM = 1) AS TELEPHONE,
                (SELECT TRIM(email) FROM tajprod.bkemacli g WHERE TRIM(b.cli) = TRIM(g.cli) AND ROWNUM = 1) AS EMAIL,
                b.inti AS ACCOUNT_TYPE,
                TRIM(a.nom2) AS ACCOUNT_NAME,
                TRIM(a.ncp2) AS ACCOUNT_NUMBER,
                TRIM(b.dou) AS DATE_OF_ACCOUNT_OPENING,
                '' AS LINKED_CONNECTED_ACCOUNTS,
                a.eve AS REFERENCE_NUMBER,
                TO_CHAR(a.dsai, 'YYYY-MM-DD') AS TRANSACTION_DATE,
                'INFLOW' AS TRANSACTION_TYPE,
                DECODE(NAT,'RETESP',NOMP,'RCHBSP',lib3,'VERESP',lib2,lib1) AS TRANSACTION_DETAILS,
                a.dev AS CURRENCY_TYPE,
                a.mht2 AS AMOUNT,
                CASE 
                    WHEN (TRIM(a.nat) IN ('VERDEV', 'RETDEV'))
                    THEN 'CASH'
                    ELSE 'TRANSFER' 
                END AS MODE_OF_TRANSACTION,
                DECODE(NAT,'RETESP',NOMP,'RCHBSP',lib3,'VERESP',lib2,lib1) AS PURPOSE_OF_TRANSACTION,
                DECODE(NAT,'RETESP',NOMP,'RCHBSP',lib3,'VERESP',lib2,lib1) AS SOURCE_ORIGIN_OF_FUNDS,
                TRIM(a.nom2) AS NAME_OF_BENEFICIARY,
                TRIM(a.ncp2) AS BENEFICIARY_ACCOUNT_NO,
                (SELECT TRIM(adr1) || ',' || TRIM(adr2) || ',' || TRIM(adr3) FROM tajprod.bkadcli f WHERE TRIM(b.cli) = TRIM(f.cli) AND ROWNUM = 1) AS ADDRESS_OF_BENEFICIARY,
                TRIM(a.nom2) AS NAME_OF_SENDER,
                '' AS ADDRESS_OF_SENDER,
                '' AS NAME_OF_CORRESPONDENCE_BANK,
                DECODE(NAT,'RETESP',NOMP,'RCHBSP',lib3,'VERESP',lib2,lib1) AS REMARKS      
            FROM
                tajprod.bkheve a
            JOIN tajprod.bkcom b ON a.ncp2 = b.ncp
            WHERE
                TRIM(a.cli2) IS NOT NULL AND
                a.dev != '566'
                AND a.dev IS NOT NULL
                AND a.mht2 >= 10000
                AND a.dsai >= TRUNC(SYSDATE) - 1 AND a.nat not in ('AGEVER', 'AGERET')"; 
        } else {
            Log::error("Invalid query type: $queryType");
            oci_close($oracleConn);
            pg_close($postgresConn);
            return 1;
        }
        
        
        

        $queryStmt = oci_parse($oracleConn, $sqlQuery);
        if (!oci_execute($queryStmt)) {
            $error = oci_error($queryStmt);
            Log::error("Oracle query failed: " . $error['message']);
            oci_close($oracleConn);
            pg_close($postgresConn);
            return 1;
        }

        $insertQuery = "
           INSERT INTO FTR (
                branch_name, branch_code, customer_type, bank_verification_number, tax_identification_number, cci_number, 
                name_of_organisation, firstname, middlename, lastname, nationality, 
                date_of_birth_or_date_of_incorporation, occupation_or_line_of_business, 
                type_of_identification, identification_no, date_of_issue, expiry_date, 
                issuing_authority, customer_address_type, full_address, city, 
                local_government, state, telephone, e_mail, account_type, account_name, 
                account_number, date_of_account_opening, linked_connected_accounts, reference_number, transaction_date, 
                transaction_type, transaction_details, currency_type, amount, mode_of_transaction, 
                purpose_of_transaction, source_origin_of_funds, name_of_beneficiary, 
                beneficiary_account_no, address_of_beneficiary, name_of_sender, 
                address_of_sender, name_of_correspondence_bank, remarks
            ) VALUES (
                $1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14,
                $15, $16, $17, $18, $19, $20, $21, $22, $23, $24, $25, $26,
                $27, $28, $29, $30, $31, $32, $33, $34, $35, $36, $37, $38, 
                $39, $40, $41, $42, $43, $44, $45, $46
            )
        ";

        $insertStmt = pg_prepare($postgresConn, "insert_query", $insertQuery);
        $insertCount = 0;

        while ($row = oci_fetch_assoc($queryStmt)) {
            // Log the row data for debugging
            Log::info("Row data: ", $row);
            
            // Check how many values are passed
            $values = array_values($row);
            Log::info("Number of values to insert: " . count($values));
            
            $result = pg_execute($postgresConn, "insert_query", $values);
            if (!$result) {
                $error = pg_last_error($postgresConn);
                Log::error("PostgreSQL insert failed: " . $error);
            } else {
                $insertCount++;
                $this->info("Inserted row number: $insertCount");
            }
        }

        // Close the connections
        oci_free_statement($queryStmt);
        oci_close($oracleConn);
        pg_close($postgresConn);

        $this->info("Data transfer process completed with $insertCount rows inserted.");
        return 0;
    }
}
