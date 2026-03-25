<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReportingTemplateExport implements FromArray, WithHeadings, WithStyles
{
    public function __construct(protected string $formType) {}

    public function array(): array
    {
        return match ($this->formType) {
            'Reach RR' => [$this->reachRrSampleRow()],
            default => [$this->genericSampleRow()],
        };
    }

    public function headings(): array
    {
        return match ($this->formType) {
            'Reach RR' => $this->reachRrHeadings(),
            default => $this->genericHeadings(),
        };
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function reachRrHeadings(): array
    {
        return [
            'pid',
            'uic',
            'kp',
            'service_date',
            'occupation',
            'access_type',
            'condom_49',
            'condom_52',
            'condom_53',
            'condom_54',
            'condom_56',
            'female_condom',
            'lubricant',
            'next_hcode',
            'hiv_forward',
            'sti_forward',
            'tb_forward',
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private function reachRrSampleRow(): array
    {
        return [
            '1234567890123',  // pid
            'TESTUSER020785', // uic
            'MSM',            // kp
            '2025-07-02',     // service_date
            'รับจ้าง',          // occupation
            2,                // access_type (1=DIC, 2=นอกDIC, 3=Social)
            10,               // condom_49
            0,                // condom_52
            0,                // condom_53
            0,                // condom_54
            0,                // condom_56
            0,                // female_condom
            5,                // lubricant
            '41936',          // next_hcode
            1,                // hiv_forward (1=พาไป, 2=ไปเอง, 3=ไม่ส่งต่อ)
            3,                // sti_forward
            3,                // tb_forward
        ];
    }

    /**
     * @return array<int, string>
     */
    private function genericHeadings(): array
    {
        return [
            'PID (13 digits)',
            'Patient Name',
            'Service Date (YYYY-MM-DD)',
            'Result/Status',
            'Remarks',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function genericSampleRow(): array
    {
        return [
            '1234567890123',
            'Jane Doe',
            '2026-03-24',
            'Positive',
            'Remark here',
        ];
    }
}
