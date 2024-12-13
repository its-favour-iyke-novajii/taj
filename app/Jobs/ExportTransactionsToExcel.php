<?php

namespace App\Jobs;

use Carbon\Carbon;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use DB;
use Log;  // Log facade for logging job execution
use Illuminate\Support\Facades\Storage;

class ExportTransactions implements ShouldQueue
{
    use SerializesModels;

    public $queryType;

    /**
     * Create a new job instance.
     *
     * @param string $queryType
     */
    public function __construct($queryType)
    {
        $this->queryType = $queryType;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Log the start of the job
        Log::info("Starting export job for query type: {$this->queryType}");

        // Prepare the SQL query based on the query type
        $sqlQuery = $this->getSQLQuery($this->queryType);

        // Fetch data from the database using the prepared query
        try {
            $data = DB::select($sqlQuery);
            Log::info("Data retrieved successfully for {$this->queryType}. Record count: " . count($data));
        } catch (\Exception $e) {
            Log::error("Error fetching data for {$this->queryType}: " . $e->getMessage());
            return;
        }

        // Define the filename for the CSV export
        $fileName = 'transactions_' . $this->queryType . '_' . time() . '.csv';
        
        // Define the file path for storage
        $filePath = 'exports/' . $fileName;

        // Use CSV export logic
        try {
            // Open the file for writing
            $file = fopen(storage_path('app/' . $filePath), 'w');

            // If data is not empty, write the column headers
            if (count($data) > 0) {
                // Get the column names dynamically from the first row of data
                $columns = array_keys((array) $data[0]);
                fputcsv($file, $columns); // Write headers
            }

            // Write the data rows
            foreach ($data as $row) {
                fputcsv($file, (array) $row);  // Convert object to array and write
            }

            fclose($file);  // Close the file after writing

            Log::info("Exported data successfully to: {$filePath}");
        } catch (\Exception $e) {
            Log::error("Error exporting data to CSV: " . $e->getMessage());
            return;
        }

        // Optionally, you can send an email or notification here to inform the user
        Log::info("Export job completed for query type: {$this->queryType}");
    }

    /**
     * Return the appropriate SQL query based on query type.
     *
     * @param string $queryType
     * @return string
     */
    private function getSQLQuery($queryType)
    {
        if ($queryType == 'outflow') {
            return "SELECT * FROM ctr_transactions WHERE tran_type = 'outflow'";
        } elseif ($queryType == 'inflow') {
            return "SELECT * FROM ctr_transactions WHERE tran_type = 'inflow'";
        }

        // Default query in case queryType doesn't match known values
        return "SELECT * FROM ctr_transactions";
    }
}
