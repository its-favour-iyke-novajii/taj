<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class StrUpdate extends Command
{
    protected $signature = 'update-str-0';
    protected $description = 'Connect to Oracle, fetch rows, and execute SQL queries';

    public function handle()
    {
        // Oracle database connection parameters
        $host = '172.19.2.86';
        $user = 'tajbank';
        $pwd = 'Tajbank123_';
        $port = '1522';
        $serviceName = 'xepdb1';
        $connectionString = "(DESCRIPTION =(ADDRESS = (PROTOCOL = TCP)(HOST = $host)(PORT = $port))(CONNECT_DATA =(SERVER = DEDICATED)(SERVICE_NAME=$serviceName)))";

        // Connect to the Oracle database
        $Lconn = oci_connect($user, $pwd, "172.19.2.86:$port/xepdb1");

        if (!$Lconn) {
            $error = oci_error();
            $this->error("Failed to connect to Oracle database for loop iteration: " . $error['message']);
            oci_free_statement($stmt);
            oci_close($Lconn);
            // pg_close($postgresConn);
            return;
        }



        $host = '172.19.20.60';
        $user = 'novaji';
        $pwd = 'novali123';
        $port = '1521';
        $desc = "(DESCRIPTION =(ADDRESS = (PROTOCOL = TCP)(HOST = $host)(PORT = $port))(CONNECT_DATA =(SERVER = DEDICATED)(SERVICE_NAME=tajrep)))";
        $conn = oci_connect($user, $pwd, "172.19.20.60:$port/tajrep");


        // PostgreSQL database connection parameters
        $postgresHost = '127.0.0.1';
        $postgresPort ='5432';
        $postgresDatabase = 'tajbank';
        $postgresUser = 'postgres';
        $postgresPassword = 'Tajbank123_';
        

        // Connect to the PostgreSQL database
     //   $postgresConn = pg_connect("host=$postgresHost port=$postgresPort dbname=$postgresDatabase user=$postgresUser password=$postgresPassword");

       // if (!$postgresConn) {
         //   $this->error("Failed to connect to PostgreSQL database");
           // oci_close($conn);
            //return;
       // }

        // Insert data into the PostgreSQL table
       // $postgresTable = 'your_postgresql_table_name'; // Replace with your actual table name

        // Execute the specified query
        $query = "select * from str_rules where lower(status) = 'active' and trim(sql_query) is not null";
        $stmt = oci_parse($Lconn, $query);

        if (!$stmt) {
            $this->error("Failed to parse the SQL query");
            oci_close($Lconn);
           // pg_close($postgresConn);
            return;
        }

        $result = oci_execute($stmt);

        if (!$result) {
            $this->error("Failed to execute the SQL query");
            oci_free_statement($stmt);
            oci_close($Lconn);
          //  pg_close($postgresConn);
            return;
        }

        // Loop through the result set and execute SQL queries
        while ($row = oci_fetch_assoc($stmt)) {
            // Create a new Oracle connection for fetching data within the loop
         
            $conn = oci_connect('novaji', 'novali123', "172.19.20.60:1521/tajrep");


            if (!$conn) {
                $this->error("Failed to connect to Oracle database for loop iteration");
                oci_free_statement($stmt);
                oci_close($conn);
                pg_close($postgresConn);
                return;
            }

            $sqlQuery = $row['SQL_QUERY'];

            echo $sqlQuery;

            // Execute the SQL query using the new Oracle connection
            $queryStmt = oci_parse($conn, $sqlQuery);
            $queryResult = oci_execute($queryStmt);

            if ($queryResult) {
                // Process $queryResult as needed
                // ...

                // Close the loop Oracle connection
                oci_close($conn);

              //  echo $row['EVENT_NUMBER'];

                $insertData = [
                    // 'event_number' => $row['EVENT_NUMBER'],
                    'gl_code' => $row['GL_CODE'],
                    'account_branch_code1' => $row['ACCOUNT_BRANCH_CODE1'],
                    'account_currency_code1' => $row['ACCOUNT_CURRENCY_CODE1'],
                    'account_debited' => $row['ACCOUNT_DEBITED'],
                    'account_credited' => $row['ACCOUNT_CREDITED'],
                    'customer_name_debited' => $row['CUSTOMER_NAME_DEBIITED'],
                    'customer_name_credited' => $row['CUSTOMER_NAME_CREDITED'],
                    'net_amount_debited' => $row['NET_AMOUNT_DEBITED'],
                    'event_date' => $row['EVENT_DATE'],
                    'transaction_type' => $row['TRANSACTION_TYPE'],
                    'initiated_by' => $row['INITIATED_BY'],
                    'initiator' => $row['INITIATOR'],
                    'approver1' => $row['APPROVER1'],
                    'approver2' => $row['APPROVER2'],
                    'narration' => $row['NARRATION'],
                ];

              //  $insertSuccess = pg_insert($postgresConn, $postgresTable, $insertData);

            //    if ($insertSuccess) {
             //       $this->info("Successfully inserted data into PostgreSQL table");
            //    } else {
            //        $this->error("Failed to insert data into PostgreSQL table");
            //    }

                $this->info("Successfully executed SQL query from row {$row['ID']}");
            } else {
                $error = oci_error($queryStmt);
                $this->error("Error executing SQL query from row {$row['ID']}: {$error['message']}");

                // Close the loop Oracle connection
                oci_close($Lconn);
            }

            oci_free_statement($queryStmt);
        }

        oci_free_statement($stmt);
        oci_close($conn);
     //   pg_close($postgresConn);
    }
}


















