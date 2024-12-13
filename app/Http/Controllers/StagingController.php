<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PDO;
use Exception;

class StagingController extends Controller
{
    public function migrateData()
    {
        try {
            // PostgreSQL connection parameters
            $pgHost = '127.0.0.1';
            $pgPort = '5432';
            $pgDatabase = 'tajbank';
            $pgUsername = 'postgres';
            $pgPassword = 'Tajbank123_';

            // Oracle connection parameters
            $oracleHost = '172.19.2.86';
            $oraclePort = '1522';
            $oracleServiceName = 'xepdb1';
            $oracleUsername = 'tajbank';
            $oraclePassword = 'Tajbank123_';

            // Connect to PostgreSQL
            $pgConnection = pg_connect("host=$pgHost port=$pgPort dbname=$pgDatabase user=$pgUsername password=$pgPassword");

            if (!$pgConnection) {
                die('Failed to connect to PostgreSQL database');
            }

            $inputData = file_get_contents("php://input");

            $requestData = json_decode($inputData, true);

            $start_date = $requestData['start_date'] ?? null;
            $end_date = $requestData['end_date'] ?? null;
            $trantype = $requestData['trantype'] ?? null;

            //$jobId = date('YmdHi');
            $jobId = $requestData['jobId'];
            $l_user_id = $requestData['g_user_id'];
            

            if (empty($start_date) || empty($end_date)) {
                return response()->json(['error' => 'start_date and end_date are required'], 400);
            }
            
            $whereClauses = [];
            $whereClauses[] = "t_date >= '$start_date'";
            $whereClauses[] = "t_date <= '$end_date'";

            if ($trantype !== 'ALL') {
                $whereClauses[] = "tran_type = '$trantype'";
            }

            $whereCondition = implode(' AND ', $whereClauses);
    
            // Fetch data from PostgreSQL
            $pgQuery = "SELECT * FROM ctr_transactions";

            if (!empty($whereCondition)) {
                $pgQuery .= " WHERE $whereCondition";
            }

            $pgResult = pg_query($pgConnection, $pgQuery);

            // Connect to Oracle
            $oracleConnection = oci_connect($oracleUsername, $oraclePassword, "(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=$oracleHost)(PORT=$oraclePort))(CONNECT_DATA=(SERVICE_NAME=$oracleServiceName)))");

            if (!$oracleConnection) {
                die('Failed to connect to Oracle database');
            }

            // Insert data into Oracle and measure execution time
            $startTime = microtime(true);
            $totalRowsInserted = 0;
            $totalRowsSkipped = 0;

            while ($row = pg_fetch_assoc($pgResult)) {
                unset($row['created_at']);
                unset($row['id']);

                $row['job_id'] = $jobId;

                try {
                    $this->insertRowIntoOracle($oracleConnection, $row);
                    $totalRowsInserted++;
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'ORA-00001') === false) {
                        return response()->json(['error' => $e->getMessage()], 500);
                    }
                    $totalRowsSkipped++;
                }
            }

            $endTime = microtime(true);
            $executionTime = number_format($endTime - $startTime, 2); 

            pg_close($pgConnection);
           // oci_close($oracleConnection);

            $jobId = date('YmdHi');

 //==============================FILE THE CTR==================================
try{
    // Execute the procedures based on p_type
    $pType = $requestData['trantype']; // Assuming this contains the value of P106_REPORT_TYPE

    // Construct and execute the PL/SQL block
  /*  $plsqlBlock = " 
        DECLARE
            l_user_id NUMBER := $l_user_id;
            p_type VARCHAR2(100) := '$pType';
        BEGIN
            CASE '$pType'
                WHEN 'INWARD_BUFFER' THEN
                    ctr_mgt.inward('$start_date', '$end_date', $l_user_id, 0);
                WHEN 'OUTWARD_BUFFER' THEN
                    ctr_mgt.outward('$start_date', '$end_date', $l_user_id, 0);
                WHEN 'ACCOUNT_TO_ACCOUNT' THEN
                    ctr_mgt.account_account('$start_date', '$end_date', $l_user_id, 0);
                WHEN 'FX_INWARD' THEN
                    ctr_mgt.fx_inward('$start_date', '$end_date', $l_user_id, 0);
                WHEN 'LOCAL_WITHDRAWAL' THEN
                    ctr_mgt.local_withdrawal('$start_date', '$end_date', $l_user_id, 0);
                WHEN 'LOCAL_DEPOSIT' THEN
                    ctr_mgt.local_deposit('$start_date', '$end_date', $l_user_id, 0);
                WHEN 'FOREIGN_DEPOSIT' THEN
                    ctr_mgt.foreign_deposit('$start_date', '$end_date', $l_user_id, 0);
                WHEN 'FOREIGN_WITHDRAWAL' THEN
                    ctr_mgt.foreign_withdrawal('$start_date', '$end_date', $l_user_id, 0);
                ELSE
                    -- Handle the default case if needed
                    NULL;
            END CASE;
        END;
    "; */
    
        // Construct and execute the PL/SQL block
    $plsqlBlock = " 
        DECLARE
            l_user_id NUMBER := $l_user_id;
            p_type VARCHAR2(100) := '$pType';
        BEGIN
        
                    ctr_mgt.inward('$start_date', '$end_date', $l_user_id, 0);
                    ctr_mgt.outward('$start_date', '$end_date', $l_user_id, 0);
                    ctr_mgt.account_account('$start_date', '$end_date', $l_user_id, 0);
                    ctr_mgt.fx_inward('$start_date', '$end_date', $l_user_id, 0);
                    ctr_mgt.local_withdrawal('$start_date', '$end_date', $l_user_id, 0);
                    ctr_mgt.local_deposit('$start_date', '$end_date', $l_user_id, 0);
                    ctr_mgt.foreign_deposit('$start_date', '$end_date', $l_user_id, 0);
                    ctr_mgt.foreign_withdrawal('$start_date', '$end_date', $l_user_id, 0);
        END;
    ";


   // echo $plsqlBlock;

    $plsqlStatement = oci_parse($oracleConnection, $plsqlBlock);

   // oci_bind_by_name($plsqlStatement, ':g_user_id', $l_user_id);
    //oci_bind_by_name($plsqlStatement, ''$start_date'', $start_date);
    //oci_bind_by_name($plsqlStatement, ':P_END_DATE', $end_date);

    if (!oci_execute($plsqlStatement, OCI_NO_AUTO_COMMIT)) {
        oci_rollback($oracleConnection); // Rollback the transaction
        oci_free_statement($plsqlStatement);
        echo "Failed to execute PL/SQL block: " . oci_error($plsqlStatement)['message'] . "\n";
    }

    oci_commit($oracleConnection); // Commit the successful execution

    oci_free_statement($plsqlStatement);

    oci_close($oracleConnection);

} catch (\Exception $e) {
    return response()->json(['error' => $e->getMessage()], 500);
}
///===========================================================================

            // Prepare the JSON response
            $response = [
                'job_id' => $jobId,
                'total_rows_inserted' => $totalRowsInserted,
                'total_rows_skipped' => $totalRowsSkipped,
                'total_time_taken' => $executionTime . ' secs',
                'message' => 'Data migrated successfully',
            ];

            return response()->json($response);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function insertRowIntoOracle($connection, $data)
    {
        // Prepare columns and bind variables for insertion
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        // Construct and execute the insert statement
        $insertSql = "INSERT INTO CTR_TRANSACTIONS ($columns) VALUES ($placeholders)";
        $insertStatement = oci_parse($connection, $insertSql);

        foreach ($data as $column => $value) {
            oci_bind_by_name($insertStatement, ':' . $column, $data[$column]);
        }

        if (!oci_execute($insertStatement, OCI_NO_AUTO_COMMIT)) {
            oci_rollback($connection); // Rollback the transaction
            oci_free_statement($insertStatement);
            echo "Failed to insert row into Oracle database: " . oci_error($insertStatement)['message'] . "\n";
        }

        oci_commit($connection); // Commit the successful insertion

        oci_free_statement($insertStatement);
    }
}
