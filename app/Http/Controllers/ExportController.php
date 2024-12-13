<?php

namespace App\Http\Controllers;

use App\Jobs\ExportTransactionsToExcel;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    public function export($queryType)
    {
        // Dispatch the job
        ExportTransactionsToExcel::dispatch($queryType);

        return response()->json(['message' => 'Export started in the background!']);
    }
}
