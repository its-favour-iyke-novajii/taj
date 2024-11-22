<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Throwable;

use PDO;
use PDOException;

//use GuzzleHttp\Client as HttpClient;

class UpdateStatistics extends Command
{
    /**
     * The console command name.
     *
     * @var string
     *             /usr/bin/php /var/www/html/taj/artisan ctr:update
     */
    protected $signature = 'statistics:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Use php command line update Statistics table';

    public function handle()
    {
     

        // Replace with your Oracle database connection details
        $tajbankTns = "(DESCRIPTION =
            (ADDRESS_LIST = (ADDRESS = (PROTOCOL = TCP)(HOST = 172.19.20.60)(PORT = 1521)))
            (CONNECT_DATA =
                (SERVICE_NAME = tajrep)
            )
        )";
        $localTns = "(DESCRIPTION =
            (ADDRESS_LIST = (ADDRESS = (PROTOCOL = TCP)(HOST = 172.19.2.86)(PORT = 1522)))
            (CONNECT_DATA =
                (SERVICE_NAME = xepdb1)
            )
        )";
        
        $Tusername = "novaji";
        $Tpassword = "novali123";

        $Lusername = "tajbank";
        $Lpassword = "Tajbank123_";
        
        try {
            // Establish a connection to the Tajbank database
            $tajbankConn = oci_connect($Tusername , $Tpassword, $tajbankTns);
            $localConn = oci_connect($Lusername, $Lpassword, $localTns);
        
            if (!$tajbankConn) {
                die("Connection to Tajbank database failed: " . oci_error());
            }
        
            // Query the "COMPLIANCE_STATISTICS" table to fetch all rows
            $query = "SELECT * FROM COMPLIANCE_STATISTICS WHERE STATUS='active'";
            $stmt = oci_parse($localConn, $query);
            oci_execute($stmt);
        
            while ($row = oci_fetch_assoc($stmt)) {
                // Establish a connection to the local database
               // $localConn = oci_connect($Lusername, $Lpassword, $localTns);
        
                if (!$localConn) {
                    die("Connection to Local database failed: " . oci_error());
                }
        
                // Execute the SQL query from the "SQL_QUERY" column
                $sqlQuery = $row['SQL_QUERY'];
                $resultStmt = oci_parse($tajbankConn, $sqlQuery);
                oci_execute($resultStmt);
                
                // Fetch the result
                $result = oci_fetch_assoc($resultStmt);
        
                if ($result) {
                    // Update the "VAL" column with the result
                    $id = $row['ID'];
                    $val = $result['VAL']; // Replace with the actual column name
                    $updateQuery = "UPDATE COMPLIANCE_STATISTICS SET VAL = :val, last_updated = SYSTIMESTAMP WHERE ID = :id and categ not in ('monitoring')";
                    $updateStmt = oci_parse($localConn, $updateQuery);
                    oci_bind_by_name($updateStmt, ":val", $val);
                    oci_bind_by_name($updateStmt, ":id", $id);
                    
                    if (oci_execute($updateStmt)) {
                        echo "Updated VAL for ID $id with $val\n";
                    } else {
                        echo "Failed to update VAL for ID $id\n";
                    }
                }
        
                // Close the connection to the local database
                oci_close($localConn);
            }
        
            oci_free_statement($stmt);
            oci_close($tajbankConn);
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage();
        }
        
    }

   




 

 





}
