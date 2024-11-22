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
    
   
}