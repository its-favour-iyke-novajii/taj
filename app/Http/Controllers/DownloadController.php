<?php
/*
namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;

class DownloadController extends BaseController
{
    public function initiateDownload()
    {
       
        $sql = file_get_contents("php://input");
        // Dispatch the download job here, as shown in the previous response.
        dispatch(new DownloadFile($sql, $filename));
        
        return response()->json(['message' => 'Download initiated.']);
    }
    
}
*/
 

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PDO;
use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class DownloadController extends BaseController
{
    private $host;
    private $username;
    private $password;

    public function __construct()
    {
        $this->host = '172.19.20.60:1521/tajrep';
        $this->username = 'novaji';
        $this->password = 'novali123';
    }

    public function initiateDownload()
    {
        try {
           // $sql = base64_decode(file_get_contents("php://input"));
            $filename = 'just';

            $sql = 'select * from tajprod.bkcli fetch first 20 rows only;'

            $conn = oci_connect($this->username, $this->password, $this->host);

            if (!$conn) {
              //  Log::error('Oracle connection failed.');
                return response()->json(['error' => 'Oracle connection failed.'], 500);
            }

            $stmt = oci_parse($conn, $sql);

            if (!$stmt) {
               // Log::error('Query preparation failed.');
                return response()->json(['error' => 'Query preparation failed.'], 500);
            }

            oci_execute($stmt);

            $result = array();
            while ($row = oci_fetch_assoc($stmt)) {
                $result[] = $row;
            }

            oci_free_statement($stmt);
            oci_close($conn);

            $csvData = '';

            if (!empty($result)) {
                // Create a CSV string from the Oracle result
                $csvData .= implode(',', array_keys($result[0])) . "\n";

                foreach ($result as $row) {
                    $csvData .= implode(',', array_map(function ($val) {
                        return sprintf("\"%s\"", trim($val));
                    }, $row)) . "\n";
                }
            }

            // Store the CSV data in a temporary file
            $tempFile = tempnam(sys_get_temp_dir(), 'download_');
            file_put_contents($tempFile, $csvData);

            // Use laravel-excel to convert the CSV to Excel and store it
            $export = new \App\Exports\ConvertToExcel($tempFile);
           // Storage::disk('public')->put($filename, Excel::store($export, $filename, 'public'));

            // Clean up the temporary CSV file
            unlink($tempFile);

           // Log::info("Downloaded and saved Excel file: " . $filename);

            return response()->json(['message' => 'Download successful.']); */
        } catch (\Exception $e) {
           // Log::error("Error downloading file: " . $filename . " - " . $e->getMessage());
            return response()->json(['error' => 'Error downloading file.'], 500);
        }
    }
}
