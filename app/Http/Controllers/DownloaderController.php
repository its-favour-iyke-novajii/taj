<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Jobs\DownloadFile; // Import the DownloadFile Job
use Laravel\Lumen\Routing\Controller as BaseController;

class DownloaderController extends BaseController
{
    public function initiateDownload(Request $request)
    {
        try {
            // Enable error reporting for debugging
            ini_set('display_errors', 1);
            ini_set('display_startup_errors', 1);
            error_reporting(E_ALL); // Report all errors

            // Retrieve the SQL query from the request body
            // Directly fetch the base64-encoded 'sql' from the request
            $sql = base64_decode($request->input('sql')); // Decode the 'sql' parameter
            $filename = 'report_' . time(); // Generate a unique filename based on timestamp

            // Dispatch the job to handle the file export in the background
            dispatch(new DownloadFile($sql, $filename));

            // Return a JSON response indicating the download has been initiated
            return response()->json([
                'message' => 'Download initiated. The file will be available soon.',
                'filename' => $filename,
            ]);
        } catch (\Exception $e) {
            // Return error response in case of an exception
            return response()->json(['error' => 'Error initiating download: ' . $e->getMessage()], 500);
        }
    }
}
