<?php
namespace App\Http\Controllers;

//use Illuminate\Http\Request;
//use App\Jobs\ProcessDownload;
//use Illuminate\Support\Facades\Log;

use Illuminate\Http\Request;
use PDO;
use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class DownloaderController extends BaseController
{
    public function initiateDownload(Request $request)
    {
        try {
            // Log incoming request for debugging
            Log::info('Initiate Download Request:', $request->all());

            // Validate incoming request (ensure SQL query and filename are provided)
            $validated = $request->validate([
                'sql' => 'required|string',
                'filename' => 'required|string',
            ]);

            // Log validation result for debugging
            Log::info('Validated Request:', $validated);

            // Extract SQL query and filename from the request
            $sql = $validated['sql'];
            $filename = $validated['filename'];

            // Dispatch the job to handle the download in the background
            dispatch(new DownloadFile($sql, $filename));

            // Return response indicating download is in progress
            return response()->json([
                'message' => 'Download started. It will be available soon.',
                'filename' => $filename,
            ]);
        } catch (\Exception $e) {
            // Log the error for debugging
            Log::error('Error initiating download: ' . $e->getMessage());

            return response()->json(['error' => 'Error initiating download.'], 500);
        }
    }

    // Endpoint to download the generated file
    public function downloadFile($filename)
    {
        $filePath = 'downloads/' . $filename . '.xlsx';

        // Check if the file exists in storage
        if (Storage::exists($filePath)) {
            // Return the file for download
            return response()->download(storage_path('app/public/' . $filePath));
        }

        // Return error if file does not exist
        return response()->json(['error' => 'File not found.'], 404);
    }
}
