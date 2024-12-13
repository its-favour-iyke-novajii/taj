<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TransactionsExport implements FromCollection, WithHeadings
{
    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        return collect($this->data);
    }

    public function headings(): array
    {
        return [
            'Transaction Number',
            'Location',
            'Description',
            'Date',
            'Teller',
            'Authorized',
            'Late Deposit',
            'Posting Date',
            'Value Date',
            'Trans Mode Code',
            'Amount Local',
            'Source Client Type',
            'Source Type',
            'Source Funds Code',
            'Source Currency Code',
            'Source Foreign Amount',
            'Source Exchange Rate',
            'Source Country',
            'Source Institution Code',
            'Source Institution Name',
            'Source Account Number',
            'Source Account Name',
            'Source Person First Name',
            'Source Person Last Name',
            'Source Entity Name',
            'Dest Client Type',
            'Dest Type',
            'Dest Funds Code',
            'Dest Currency Code',
            'Dest Foreign Amount',
            'Dest Exchange Rate',
            'Dest Country',
            'Dest Institution Code',
            'Dest Institution Name',
            'Dest Account Number',
            'Dest Account Name',
            'Dest Person First Name',
            'Dest Person Last Name',
            'Dest Entity Name',
            'Transaction Type',
        ];
    }
}
