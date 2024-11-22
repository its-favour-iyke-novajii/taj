// app/Jobs/ProcessDownload.php

<?php

// app/Jobs/ProcessDownload.php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\StreamedExport;
use Illuminate\Support\Facades\Storage;
use Log;

class ProcessDownload implements ShouldQueue
{
    use Queueable, SerializesModels;

    protected $sql;
    protected $filename;

    public function __construct($sql, $filename)
    {
        $this->sql = $sql;
        $this->filename = $filename;
    }

    public function handle()
    {
        try {
            // Oracle DB connection parameters
            $host = '172.19.20.60:1521/tajrep';
            $username = 'novaji';
            $password = 'novali123';

            // Open a connection to Oracle DB
            $conn = oci_connect($username, $password, $host);
            if (!$conn) {
                throw new \Exception('Oracle connection failed.');
            }

            // Create a streaming export for Excel
            $export = new StreamedExport($this->sql, $this->filename);

            // Define where to store the Excel file
            $excelFilePath = 'downloads/' . $this->filename . '.xlsx';

            // Store the generated Excel file
            Excel::store($export, $excelFilePath, 'public');

            // Close Oracle DB connection
            oci_close($conn);
        } catch (\Exception $e) {
            Log::error("Error processing the download job: " . $e->getMessage());
        }
    }
}
