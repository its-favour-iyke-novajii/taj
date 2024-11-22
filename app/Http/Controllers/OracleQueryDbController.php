<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PDO;
use Laravel\Lumen\Routing\Controller as BaseController;

class OracleQueryDbController extends BaseController
{
 
    private $host = '172.19.20.60:1521/tajrep';
    private $username = 'novaji';
    private $password = 'novali123';

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

            //return 'downloading..';

            $conn = oci_connect($this->username, $this->password, $this->host);

            if (!$conn) {
                $error = oci_error();
                die('Connection failed: ' . $error['message']);
            }

            // $sql = "SELECT * FROM tajprod.bkcli FETCH FIRST 4000 ROWS ONLY";
            // $sql = $request->header('q'); 
            //$sql = file_get_contents("php://input");

            /*
            var_dump($_GET['q']);
            var_dump($_GET['f']);
            return;
            */
            //$sql = 'select * from tajprod.bkage';
           // $file_name = base64_decode($_GET['f']) . ".csv";
           /* 
           $input = file_get_contents("php://input");
           echo $input; 
           */
            $sql = base64_decode(file_get_contents("php://input"));
            //echo $sql;
            //var_dump($file_name);
            //var_dump($sql);
            //return;
            
            $stmt = oci_parse($conn, $sql);
            if (!$stmt) {
                $error = oci_error($conn);
                die('Query preparation failed: ' . $error['message']);
            }

            $result = array();
            oci_execute($stmt);
            while ($row = oci_fetch_assoc($stmt)) {
                $result[] = $row;
                //             unset($row);
//var_dump($row);
            }

            oci_free_statement($stmt);
            oci_close($conn);
            //header("Content-Type: application/vnd.ms-excel");
            //header("Content-Type: text/csv");
            //header("Content-Disposition: attachment; filename=\"$file_name\"");

            $this->export_file($result);

        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }
    public function query(Request $request)
    {

        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        // 5 minx maximum execution time
        ini_set('max_execution_time', '300');
        set_time_limit(300);
        error_reporting(E_ALL);
        try {
            $host = '172.19.20.60';
            $port = '1521';
            $sid = 'tajrep';
            $username = 'novaji';
            $password = 'novali123';
            #$conn = oci_connect('novaji', 'novali123', '172.19.20.60:1521/tajrep');
            $conn = oci_connect($this->username, $this->password, $this->host);

            if (!$conn) {
                $error = oci_error();
                die('Connection failed: ' . $error['message']);
            }

            // $sql = "SELECT * FROM tajprod.bkcli FETCH FIRST 4000 ROWS ONLY";
            // $sql = $request->header('sql'); 
            $sql = file_get_contents("php://input");
            //return $sql;
            $stmt = oci_parse($conn, $sql);
            if (!$stmt) {
                $error = oci_error($conn);
                die('Query preparation failed: ' . $error['message']);
            }

            $result = array();
            oci_execute($stmt);
            while ($row = oci_fetch_assoc($stmt)) {
                $result[] = $row;
                //             unset($row);
//var_dump($row);
            }

            oci_free_statement($stmt);
            oci_close($conn);

            return response()->json($result);
        } catch (\Exception $e) {
            // Handle the exception or error
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}