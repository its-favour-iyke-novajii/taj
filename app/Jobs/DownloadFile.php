
<?php

namespace App\Jobs;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class DownloadFile
{
    private $sql;
    private $filename;

    public function __construct($sql, $filename)
    {
        $this->sql = $sql;
        $this->filename = $filename;
    }

    public function handle()
    {

        echo 'here';
        private $host = '172.19.20.60:1521/tajrep';
        private $username = 'novaji';
        private $password = 'novali123';

        try {
            $conn = oci_connect($username, $password, $host);

            if (!$conn) {
                Log::error('Oracle connection failed.');
                return;
            }

            $stmt = oci_parse($conn, $this->sql);

            if (!$stmt) {
                Log::error('Query preparation failed.');
                return;
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
            Storage::disk('public')->put($this->filename, Excel::store($export, $this->filename, 'public'));

            // Clean up the temporary CSV file
            unlink($tempFile);

            Log::info("Downloaded and saved Excel file: " . $this->filename);
        } catch (\Exception $e) {
            Log::error("Error downloading file: " . $this->filename . " - " . $e->getMessage());
        }
    }
}








