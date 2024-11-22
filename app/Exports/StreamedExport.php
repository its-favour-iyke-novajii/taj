// app/Exports/StreamedExport.php
<?php
// app/Exports/StreamedExport.php
// app/Exports/StreamedExport.php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class StreamedExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithTitle
{
    protected $sql;
    protected $filename;
    protected $headings = [];

    public function __construct($sql, $filename)
    {
        $this->sql = $sql;
        $this->filename = $filename;
    }

    public function query()
    {
        // Oracle DB connection parameters
        $host = '172.19.20.60:1521/tajrep';
        $username = 'novaji';
        $password = 'novali123';

        // Open a connection to Oracle DB
        $conn = oci_connect($username, $password, $host);
        if (!$conn) {
            throw new \Exception('Oracle connection failed.');
        }

        // Prepare the SQL query
        $stmt = oci_parse($conn, $this->sql);
        oci_execute($stmt);

        // Fetch data
        $data = [];
        $this->headings = [];  // Clear headings array

        // Fetch the first row to get the keys (columns)
        if ($row = oci_fetch_assoc($stmt)) {
            $this->headings = array_keys($row);  // Set headings from the first row
            $data[] = $row; // Add the first row to data
        }

        // Fetch the remaining rows
        while ($row = oci_fetch_assoc($stmt)) {
            $data[] = $row;
        }

        // Close Oracle DB connection
        oci_free_statement($stmt);
        oci_close($conn);

        // Return data to be exported
        return collect($data);
    }

    public function headings(): array
    {
        // Return the dynamic headings (keys from the first row)
        return $this->headings;
    }

    public function map($row): array
    {
        // Map the Oracle data to the Excel rows
        return array_values($row); // Map each row by its values
    }

    public function title(): string
    {
        return $this->filename;
    }
}
