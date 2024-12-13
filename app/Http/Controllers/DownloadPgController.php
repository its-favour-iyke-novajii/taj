<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PDO;
use Laravel\Lumen\Routing\Controller as BaseController;

class DownloadPgController extends BaseController
{


    private function export_file($records)
    {
        $heading = false;
        /*
         if(!empty($records))
                  foreach($records as $row) {
                    if(!$heading) {
                      // display field/column names as a first row
                      echo implode("\t", array_keys($row)) . "\n";
                      $heading = true;
                    }
                    echo implode("\t", array_values($row)) . "\n";
                }
                */
        
        if (!empty($records))
            foreach ($records as $row) {
                if (!$heading) {
                    // display field/column names as a first row
                    echo implode(",", array_keys($row)) . "\n";
                    $heading = true;
                }
                $values = array_values($row);
                $str = implode(',', array_map(function ($val) {
                    // 000001 add quote
                    if (!preg_match("/[a-z]/i", $val) && substr($val, 0, 1) == '0' && strlen(trim($val))>1) {
                        //return sprintf("\"%s\"", trim(str_replace(",","&comma;",str_replace("\n","",$val))));
                        return sprintf("'%s", trim(str_replace("\n","",$val)));
                    }
                    //return str_replace("\n","",trim(str_replace(",","|",$val)));
                    return sprintf("\"%s\"", trim($val));
                }, $values)) . "\n";
                echo $str;
                //echo implode(",", array_values($row)) . "\n";
            }
            
        exit;
    }


    public function download(Request $request)
    {
        try {
            // Your PostgreSQL database credentials
            $host = '127.0.0.1';
            $port = '5432';
            $database = 'tajbank';
            $username = 'postgres';
            $password = 'Tajbank123_';
    
            // Connect to PostgreSQL
            $conn = pg_connect("host=$host port=$port dbname=$database user=$username password=$password");
            if (!$conn) {
                die('Connection failed');
            }
    
            // Fetch the SQL query from the request headers or input data (uncomment and handle appropriately)
            // $sql = $request->header('q');
            // $sql = file_get_contents("php://input");
            // $sql = base64_decode(file_get_contents("php://input"));
    
            // Example SQL query for demonstration purposes
                        //echo $sql;
            
            $sql = base64_decode(file_get_contents("php://input"));
    
            $result = pg_query($conn, $sql);
            if (!$result) {
                die('Query execution failed');
            }
    
            // Fetch data and build the result array
            $data = array();
            while ($row = pg_fetch_assoc($result)) {
                $data[] = $row;
            }
    
            // Close the database connection
            pg_free_result($result);
            pg_close($conn);
    
            // Export the data in CSV format (You need to define this function)
            $this->export_file($data);
    
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }
    
    
    private function export_stream_file($sql)
{
    // Set proper headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="data.csv"');

    // Open output stream
    $output = fopen('php://output', 'w');

    // PostgreSQL connection details
    $host = '127.0.0.1';
    $port = '5432';
    $database = 'tajbank';
    $username = 'postgres';
    $password = 'Tajbank123_';

    // Establish connection to PostgreSQL database
    $conn = pg_connect("host=$host port=$port dbname=$database user=$username password=$password");

    if (!$conn) {
        die('Connection failed');
    }

    // Start the cursor for streaming data
    $cursor_name = 'my_cursor';
    $result = pg_query($conn, "DECLARE $cursor_name CURSOR FOR $sql");

    if (!$result) {
        die('Failed to declare cursor: ' . pg_last_error($conn));
    }

    // Output column headers as the first row
    $columnNames = pg_fetch_assoc(pg_query($conn, "FETCH 1 FROM $cursor_name"));
    if ($columnNames) {
        fputcsv($output, array_keys($columnNames)); // Write headers to CSV
    }

    // Process data in chunks and write to CSV
    while ($row = pg_fetch_assoc(pg_query($conn, "FETCH 1000 FROM $cursor_name"))) {
        // Ensure data is properly formatted before writing to CSV
        $this->sanitizeRow($row); // Optional: Customize this method to clean/escape data if needed
        fputcsv($output, $row); // Write each row to CSV
    }

    // Close the cursor and the database connection
    pg_query($conn, "CLOSE $cursor_name");
    pg_close($conn);

    // Close the output stream
    fclose($output);

    exit;
}

/**
 * Optional: Sanitize each row before writing to CSV.
 * You can add custom logic here to clean or escape data.
 */
private function sanitizeRow(&$row)
{
    // Example: Escape newlines or commas within data if necessary
    array_walk($row, function (&$value) {
        // Remove newlines and commas, or add custom escaping logic
        $value = str_replace(["\n", "\r", ","], ["", "", " "], $value);
    });
}

    
   
}