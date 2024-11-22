<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PDO;
use PDOException;

class UpdateStaffs extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'update:staffs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Use php command line update STR table';

    public function handle()
    {
      
        try {
            // PostgreSQL connection parameters
            $pgsql_host = "172.19.2.86";
            $pgsql_port = "5432";
            $pgsql_dbname = "tajbank";
            $pgsql_username = "postgres";
            $pgsql_password = "Tajbank123_";
        
            // Oracle connection parameters
            $oracle_host = '172.19.2.86';
            $oracle_user = 'tajbank';
            $oracle_pwd = 'Tajbank123_';
            $oracle_port = '1522';
            $oracle_service_name = 'xepdb1';
        
            // Connect to PostgreSQL database
            $pgsql_conn = new PDO("pgsql:host=$pgsql_host;port=$pgsql_port;dbname=$pgsql_dbname;user=$pgsql_username;password=$pgsql_password");
            $pgsql_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
            // Connect to Oracle database
            $oracle_conn = oci_connect($oracle_user, $oracle_pwd, "$oracle_host:$oracle_port/$oracle_service_name");
        
            // Retrieve data from PostgreSQL table
            $pgsql_query = "SELECT staff_name, department, email, staff_id FROM STAFFS";
            $pgsql_stmt = $pgsql_conn->query($pgsql_query);
            $pgsql_results = $pgsql_stmt->fetchAll(PDO::FETCH_ASSOC);
        
            // Iterate over PostgreSQL results and insert/update into Oracle table
            foreach ($pgsql_results as $row) {
                $staff_name = $row['staff_name'];
                $department = $row['department'];
                $email = $row['email'];
                $staff_id = $row['staff_id'];
        
                // Check if the email already exists in Oracle table
                $oracle_query = "SELECT COUNT(*) AS count FROM staffs WHERE EMAIL = :email";
                $oracle_stmt = oci_parse($oracle_conn, $oracle_query);
                oci_bind_by_name($oracle_stmt, ':email', $email);
                oci_execute($oracle_stmt);
                $oracle_row = oci_fetch_assoc($oracle_stmt);
                $count = $oracle_row['COUNT'];
        
                if ($count > 0) {
                    // Update existing record in Oracle table
                    $oracle_query = "UPDATE staffs SET STAFF_NAME = :staff_name, DEPARTMENT = :department, STAFF_ID = :staff_id WHERE EMAIL = :email";
                } else {
                    // Insert new record into Oracle table
                    $oracle_query = "INSERT INTO staffs (STAFF_NAME, DEPARTMENT, EMAIL, STAFF_ID) VALUES (:staff_name, :department, :email, :staff_id)";
                }
        
                $oracle_stmt = oci_parse($oracle_conn, $oracle_query);
                oci_bind_by_name($oracle_stmt, ':staff_name', $staff_name);
                oci_bind_by_name($oracle_stmt, ':department', $department);
                oci_bind_by_name($oracle_stmt, ':email', $email);
                oci_bind_by_name($oracle_stmt, ':staff_id', $staff_id);
                oci_execute($oracle_stmt);
            }
        
            // Close connections
            $pgsql_conn = null;
            oci_close($oracle_conn);
        
            echo "Data synchronized successfully!";
        } catch (PDOException $e) {
            echo "Error connecting to PostgreSQL: " . $e->getMessage();
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage();
        }
        
    }


}
