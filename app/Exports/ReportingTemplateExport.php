<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReportingTemplateExport implements FromArray, WithHeadings, WithStyles
{
    public function __construct(protected string $formType)
    {
    }

    public function array(): array
    {
        // Sample data for the user to follow
        return [
            [
                '1234567890123', // PID
                'Jane Doe',      // Name (Optional, for user reference)
                '2026-03-24',    // Date
                'Positive',      // Result/Status
                'Remark here'    // Notes
            ]
        ];
    }

    public function headings(): array
    {
        return [
            'PID (13 digits)',
            'Patient Name',
            'Service Date (YYYY-MM-DD)',
            'Result/Status',
            'Remarks'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
