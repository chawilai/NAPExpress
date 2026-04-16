<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExcelTemplateGenerator
{
    public function generateRr(): string
    {
        $spreadsheet = new Spreadsheet;
        $this->buildDocSheet($spreadsheet->getActiveSheet(), $this->rrColumns());
        $this->buildDataSheet($spreadsheet->createSheet(), $this->rrColumns(), $this->rrSampleRows());

        return $this->save($spreadsheet, 'template_rr');
    }

    public function generateVct(): string
    {
        $spreadsheet = new Spreadsheet;
        $this->buildDocSheet($spreadsheet->getActiveSheet(), $this->vctColumns());
        $this->buildDataSheet($spreadsheet->createSheet(), $this->vctColumns(), $this->vctSampleRows());

        return $this->save($spreadsheet, 'template_vct');
    }

    /**
     * Build the documentation sheet.
     *
     * @param  array<int, array<string, string>>  $columns
     */
    private function buildDocSheet(Worksheet $sheet, array $columns): void
    {
        $sheet->setTitle('คู่มือ (Documentation)');

        // Title
        $sheet->setCellValue('A1', 'คู่มือการกรอกข้อมูล — AutoNAP CSV Template');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->setColor(new Color('0F6FDE'));
        $sheet->mergeCells('A1:G1');

        $sheet->setCellValue('A2', 'กรุณาอ่านคู่มือนี้ก่อนกรอกข้อมูลใน sheet "ข้อมูล (Data)" — ทุก column มีรายละเอียดด้านล่าง');
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setColor(new Color('666666'));
        $sheet->mergeCells('A2:G2');

        $sheet->setCellValue('A3', '⚠️ สำคัญ: บันทึกเป็น .xlsx (ไม่ใช่ .csv) — ถ้า save เป็น CSV จะเสีย sheet นี้');
        $sheet->getStyle('A3')->getFont()->setBold(true)->setColor(new Color('DC2626'));
        $sheet->mergeCells('A3:G3');

        // Headers row
        $headers = ['Column', 'ชนิดข้อมูล', 'จำเป็น', 'รูปแบบ / Format', 'คำอธิบาย', 'ค่าที่เป็นไปได้', 'ตัวอย่าง'];
        $headerRow = 5;

        foreach ($headers as $i => $h) {
            $col = chr(65 + $i);
            $sheet->setCellValue("{$col}{$headerRow}", $h);
        }

        $headerStyle = $sheet->getStyle("A{$headerRow}:G{$headerRow}");
        $headerStyle->getFont()->setBold(true)->setColor(new Color('FFFFFF'));
        $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0F6FDE');
        $headerStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Data rows
        $row = $headerRow + 1;

        foreach ($columns as $col) {
            $sheet->setCellValue("A{$row}", $col['name']);
            $sheet->setCellValue("B{$row}", $col['type']);
            $sheet->setCellValue("C{$row}", $col['required'] ? '✅ ใช่' : '⬜ ไม่');
            $sheet->setCellValue("D{$row}", $col['format']);
            $sheet->setCellValue("E{$row}", $col['description']);
            $sheet->setCellValue("F{$row}", $col['allowed']);
            $sheet->setCellValue("G{$row}", $col['example']);

            // Column name bold
            $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setName('Consolas');

            // Required column color
            if ($col['required']) {
                $sheet->getStyle("C{$row}")->getFont()->setColor(new Color('10B981'));
            }

            // Alternate row color
            if (($row - $headerRow) % 2 === 0) {
                $sheet->getStyle("A{$row}:G{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
            }

            $row++;
        }

        // Table border
        $lastRow = $row - 1;
        $sheet->getStyle("A{$headerRow}:G{$lastRow}")->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->setColor(new Color('E2E8F0'));

        // Column widths
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(14);
        $sheet->getColumnDimension('C')->setWidth(10);
        $sheet->getColumnDimension('D')->setWidth(25);
        $sheet->getColumnDimension('E')->setWidth(45);
        $sheet->getColumnDimension('F')->setWidth(50);
        $sheet->getColumnDimension('G')->setWidth(25);

        // Wrap text
        $sheet->getStyle("A{$headerRow}:G{$lastRow}")->getAlignment()
            ->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);

        // Freeze panes
        $sheet->freezePane('A'.($headerRow + 1));
    }

    /**
     * Build the data entry sheet with headers + sample rows.
     *
     * @param  array<int, array<string, string>>  $columns
     * @param  array<int, array<int, mixed>>  $sampleRows
     */
    private function buildDataSheet(Worksheet $sheet, array $columns, array $sampleRows): void
    {
        $sheet->setTitle('ข้อมูล (Data)');

        // Headers
        $headers = array_map(fn ($c) => $c['name'], $columns);

        foreach ($headers as $i => $h) {
            $col = chr(65 + $i);
            $sheet->setCellValue("{$col}1", $h);
        }

        $lastCol = chr(65 + count($headers) - 1);
        $headerStyle = $sheet->getStyle("A1:{$lastCol}1");
        $headerStyle->getFont()->setBold(true)->setColor(new Color('FFFFFF'));
        $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0F6FDE');

        // Sample rows
        foreach ($sampleRows as $rowIdx => $row) {
            $rowNum = $rowIdx + 2;

            foreach ($row as $colIdx => $value) {
                $col = chr(65 + $colIdx);

                // Force text format for ID-like columns to prevent Excel scientific notation
                if ($columns[$colIdx]['type'] === 'string (13 หลัก)' || $columns[$colIdx]['type'] === 'string (10 หลัก)') {
                    $sheet->setCellValueExplicit("{$col}{$rowNum}", $value, DataType::TYPE_STRING);
                } else {
                    $sheet->setCellValue("{$col}{$rowNum}", $value);
                }
            }

            // Light gray for sample rows
            $sheet->getStyle("A{$rowNum}:{$lastCol}{$rowNum}")->getFont()->setColor(new Color('9CA3AF'));
        }

        // Note row after samples
        $noteRow = count($sampleRows) + 3;
        $sheet->setCellValue("A{$noteRow}", '← ลบแถวตัวอย่างด้านบน แล้วเริ่มกรอกข้อมูลจริงตั้งแต่แถวที่ 2');
        $sheet->getStyle("A{$noteRow}")->getFont()->setItalic(true)->setColor(new Color('F59E0B'));
        $sheet->mergeCells("A{$noteRow}:{$lastCol}{$noteRow}");

        // Auto-width
        foreach (range('A', $lastCol) as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }

        // Freeze header
        $sheet->freezePane('A2');
    }

    private function save(Spreadsheet $spreadsheet, string $name): string
    {
        $path = storage_path("app/private/{$name}.xlsx");
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        return $path;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rrColumns(): array
    {
        return [
            ['name' => 'pid', 'type' => 'string (13 หลัก)', 'required' => true, 'format' => '13 หลัก (ตัวเลข)', 'description' => 'เลขบัตรประชาชน 13 หลัก', 'allowed' => 'ตัวเลข 13 หลัก เช่น 1234567890123', 'example' => '1234567890123'],
            ['name' => 'uic', 'type' => 'string', 'required' => true, 'format' => 'text', 'description' => 'UIC code ของผู้รับบริการที่ศูนย์ออกให้', 'allowed' => 'ตามรูปแบบของศูนย์ (6 ตัวท้ายเป็นวันเดือนปีเกิด DDMMYY)', 'example' => 'TESTMSM020785'],
            ['name' => 'kp', 'type' => 'enum', 'required' => true, 'format' => 'ตัวพิมพ์ใหญ่ eng', 'description' => 'Key Population — กลุ่มเป้าหมาย', 'allowed' => 'MSM, MSW, FSW, TG, TGW, TGM, TGSW, PWID, MIGRANT, PRISONER, MALE, FEMALE', 'example' => 'MSM'],
            ['name' => 'service_date', 'type' => 'date', 'required' => true, 'format' => 'YYYY-MM-DD (ค.ศ.)', 'description' => 'วันที่ให้บริการ — ระบบจะแปลงเป็น พ.ศ. ให้', 'allowed' => 'วันที่จริง เช่น 2026-04-15', 'example' => '2026-04-01'],
            ['name' => 'occupation', 'type' => 'string', 'required' => false, 'format' => 'text ไทย/eng', 'description' => 'อาชีพ — ระบบจะ auto-map เป็น code ให้', 'allowed' => 'นักเรียน, ข้าราชการ, รับจ้าง (default), พนักงานบริษัท, ค้าขาย, ว่างงาน', 'example' => 'รับจ้าง'],
            ['name' => 'access_type', 'type' => 'integer (1-3)', 'required' => false, 'format' => '1, 2, หรือ 3', 'description' => 'ช่องทางเข้าถึง', 'allowed' => '1=DIC (ในศูนย์), 2=นอก DIC (default), 3=Social media', 'example' => '2'],
            ['name' => 'condom_49', 'type' => 'integer', 'required' => false, 'format' => 'จำนวน (ตัวเลข)', 'description' => 'ถุงยาง ข้อ 49 — default 0', 'allowed' => '0 ขึ้นไป', 'example' => '10'],
            ['name' => 'condom_52', 'type' => 'integer', 'required' => false, 'format' => 'จำนวน', 'description' => 'ถุงยาง ข้อ 52 — default 20 ถ้าเป็น 0', 'allowed' => '0 ขึ้นไป (ถ้า 0 ระบบจะใช้ 20)', 'example' => '0'],
            ['name' => 'condom_53', 'type' => 'integer', 'required' => false, 'format' => 'จำนวน', 'description' => 'ถุงยาง ข้อ 53 — default 0', 'allowed' => '0 ขึ้นไป', 'example' => '0'],
            ['name' => 'condom_54', 'type' => 'integer', 'required' => false, 'format' => 'จำนวน', 'description' => 'ถุงยาง ข้อ 54 — default 20 ถ้าเป็น 0', 'allowed' => '0 ขึ้นไป (ถ้า 0 ระบบจะใช้ 20)', 'example' => '0'],
            ['name' => 'condom_56', 'type' => 'integer', 'required' => false, 'format' => 'จำนวน', 'description' => 'ถุงยาง ข้อ 56 — default 20 ถ้าเป็น 0', 'allowed' => '0 ขึ้นไป (ถ้า 0 ระบบจะใช้ 20)', 'example' => '0'],
            ['name' => 'female_condom', 'type' => 'integer', 'required' => false, 'format' => 'จำนวน', 'description' => 'ถุงยางผู้หญิง', 'allowed' => '0 ขึ้นไป', 'example' => '0'],
            ['name' => 'lubricant', 'type' => 'integer', 'required' => false, 'format' => 'จำนวน', 'description' => 'สารหล่อลื่น — default 20 ถ้าเป็น 0', 'allowed' => '0 ขึ้นไป (ถ้า 0 ระบบจะใช้ 20)', 'example' => '5'],
            ['name' => 'next_hcode', 'type' => 'string (5 หลัก)', 'required' => false, 'format' => '5 หลัก', 'description' => 'รหัสหน่วยบริการที่ส่งต่อ (hcode)', 'allowed' => 'รหัส 5 หลัก จากทะเบียน สปสช. เช่น 41936', 'example' => '41936'],
            ['name' => 'hiv_forward', 'type' => 'integer (1-3)', 'required' => false, 'format' => '1, 2, หรือ 3', 'description' => 'การส่งต่อตรวจ HIV', 'allowed' => '1=พาไปส่ง, 2=ไปเอง, 3=ไม่ส่งต่อ (default)', 'example' => '1'],
            ['name' => 'sti_forward', 'type' => 'integer (1-3)', 'required' => false, 'format' => '1, 2, หรือ 3', 'description' => 'การส่งต่อตรวจ STI', 'allowed' => '1=พาไปส่ง, 2=ไปเอง, 3=ไม่ส่งต่อ (default)', 'example' => '3'],
            ['name' => 'tb_forward', 'type' => 'integer (1-3)', 'required' => false, 'format' => '1, 2, หรือ 3', 'description' => 'การส่งต่อตรวจ TB', 'allowed' => '1=พาไปส่ง, 2=ไปเอง, 3=ไม่ส่งต่อ (default)', 'example' => '3'],
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function rrSampleRows(): array
    {
        return [
            ['1234567890123', 'TESTMSM020785', 'MSM', '2026-04-01', 'รับจ้าง', 2, 10, 20, 0, 20, 20, 0, 10, '41936', 1, 3, 3],
            ['1100800123456', 'REACH180295', 'MSW', '2026-04-02', 'ค้าขาย', 1, 0, 20, 0, 20, 20, 0, 5, '41936', 3, 3, 3],
            ['3100700456789', 'OUTR150893', 'TG', '2026-04-03', 'ว่างงาน', 2, 0, 20, 0, 20, 20, 5, 10, '41936', 1, 1, 3],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function vctColumns(): array
    {
        return [
            ['name' => 'source_id', 'type' => 'string', 'required' => false, 'format' => 'text', 'description' => 'ID อ้างอิงภายในศูนย์ (ถ้ามี)', 'allowed' => 'อะไรก็ได้ เช่น V001, REF-123', 'example' => 'V001'],
            ['name' => 'id_card', 'type' => 'string (13 หลัก)', 'required' => true, 'format' => '13 หลัก', 'description' => 'เลขบัตรประชาชน', 'allowed' => 'ตัวเลข 13 หลัก', 'example' => '1129902021341'],
            ['name' => 'uic', 'type' => 'string', 'required' => true, 'format' => 'text', 'description' => 'UIC code ของศูนย์', 'allowed' => 'ตามรูปแบบศูนย์', 'example' => 'อส190350'],
            ['name' => 'full_name', 'type' => 'string', 'required' => false, 'format' => 'text', 'description' => 'ชื่อ-นามสกุล (ไม่เก็บในระบบ)', 'allowed' => 'text ภาษาไทย/อังกฤษ', 'example' => 'สมชาย รักดี'],
            ['name' => 'phone', 'type' => 'string (10 หลัก)', 'required' => false, 'format' => '10 หลัก', 'description' => 'เบอร์โทรศัพท์', 'allowed' => 'ตัวเลข 10 หลัก', 'example' => '0812345678'],
            ['name' => 'kp', 'type' => 'enum', 'required' => true, 'format' => 'ตัวพิมพ์ใหญ่', 'description' => 'Key Population', 'allowed' => 'MSM, MSW, FSW, TG, TGW, TGM, TGSW, PWID, MIGRANT, PRISONER, MALE, FEMALE', 'example' => 'MSM'],
            ['name' => 'cbs', 'type' => 'string', 'required' => true, 'format' => 'text', 'description' => 'ชื่อผู้ให้คำปรึกษา (Counselor)', 'allowed' => 'ชื่อเจ้าหน้าที่', 'example' => 'สุรศักดิ์'],
            ['name' => 'service_date', 'type' => 'date', 'required' => true, 'format' => 'YYYY-MM-DD (ค.ศ.)', 'description' => 'วันที่ให้บริการ VCT', 'allowed' => 'วันที่จริง', 'example' => '2026-04-03'],
            ['name' => 'occupation', 'type' => 'string', 'required' => false, 'format' => 'text', 'description' => 'อาชีพ', 'allowed' => 'เหมือน RR (auto-map)', 'example' => 'พนักงานบริษัท'],
            ['name' => 'location', 'type' => 'enum', 'required' => false, 'format' => 'text', 'description' => 'สถานที่ให้บริการ', 'allowed' => 'DIC, Clinic, Outreach, Mobile', 'example' => 'DIC'],
            ['name' => 'request_lab', 'type' => 'boolean', 'required' => true, 'format' => 'true/false', 'description' => 'ส่งตรวจ lab หรือไม่', 'allowed' => 'true=ส่งตรวจ, false=ไม่ส่ง', 'example' => 'true'],
            ['name' => 'test_type', 'type' => 'enum', 'required' => false, 'format' => 'text', 'description' => 'ประเภทการตรวจ (ใช้เมื่อ request_lab=true)', 'allowed' => 'HIV, Syphilis, HBV, HCV', 'example' => 'HIV'],
            ['name' => 'specimen_type', 'type' => 'enum', 'required' => false, 'format' => 'text', 'description' => 'ประเภทตัวอย่าง', 'allowed' => 'blood, oral, serum', 'example' => 'blood'],
            ['name' => 'lab_code', 'type' => 'string', 'required' => false, 'format' => 'text', 'description' => 'Lab Request ID (ถ้ามี)', 'allowed' => 'รหัส ANTIHIV-xxxxx', 'example' => 'ANTIHIV-41692-6904-0001'],
            ['name' => 'provider_hcode', 'type' => 'string (5 หลัก)', 'required' => false, 'format' => '5 หลัก', 'description' => 'รหัสหน่วยบริการ', 'allowed' => 'hcode 5 หลัก', 'example' => '41936'],
            ['name' => 'test_date', 'type' => 'date', 'required' => false, 'format' => 'YYYY-MM-DD', 'description' => 'วันที่ทราบผล (ใส่เมื่อได้ผลแล้ว)', 'allowed' => 'วันที่จริง หรือว่างถ้ายังไม่มี', 'example' => '2026-04-04'],
            ['name' => 'lab_status', 'type' => 'integer (1-2)', 'required' => false, 'format' => '1 หรือ 2', 'description' => 'สถานะการตรวจ', 'allowed' => '1=ตรวจได้, 2=ตรวจไม่ได้', 'example' => '1'],
            ['name' => 'result', 'type' => 'integer (1-3)', 'required' => false, 'format' => '1, 2, หรือ 3', 'description' => 'ผลตรวจ HIV', 'allowed' => '1=Positive (ติดเชื้อ), 2=Negative (ไม่ติด), 3=Inconclusive (ไม่ชัด)', 'example' => '2'],
            ['name' => 'result_text', 'type' => 'string', 'required' => false, 'format' => 'text', 'description' => 'คำอธิบายผล (optional)', 'allowed' => 'Positive, Negative, Inconclusive', 'example' => 'Negative'],
            ['name' => 'remarks', 'type' => 'string', 'required' => false, 'format' => 'text', 'description' => 'หมายเหตุ / การดำเนินการต่อ', 'allowed' => 'ข้อความอะไรก็ได้', 'example' => 'ส่งผลให้ผู้รับบริการแล้ว'],
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function vctSampleRows(): array
    {
        return [
            ['V001', '1129902021341', 'อส190350', 'สมชาย รักดี', '0812345678', 'MSM', 'สุรศักดิ์', '2026-04-03', 'พนักงานบริษัท', 'DIC', 'true', 'HIV', 'blood', 'ANTIHIV-41692-6904-0001', '41936', '2026-04-04', 1, 2, 'Negative', 'ส่งผลให้ผู้รับบริการแล้ว'],
            ['V002', '1100800556789', 'MSM120592', 'นายเอ บีซี', '0898765432', 'MSM', 'สุรศักดิ์', '2026-04-03', 'รับจ้าง', 'Outreach', 'true', 'HIV', 'blood', 'ANTIHIV-41692-6904-0002', '41936', '2026-04-04', 1, 2, 'Negative', ''],
            ['V003', '3100700999888', 'TG050689', 'น้องแป้ง', '0611122233', 'TG', 'ฟ้า', '2026-04-04', 'ค้าขาย', 'Clinic', 'true', 'HIV', 'oral', 'ANTIHIV-41692-6904-0003', '41936', '2026-04-05', 1, 1, 'Positive', 'ส่งต่อ ART clinic'],
            ['V004', '1550700111222', 'FSW081190', '', '0677788899', 'FSW', 'พลอย', '2026-04-04', 'รับจ้าง', 'Outreach', 'false', '', '', '', '', '', '', '', '', 'ปฏิเสธ lab — counseling only'],
            ['V005', '1100600333444', 'PWID231084', '', '0822334455', 'PWID', 'ต่อ', '2026-04-05', 'ว่างงาน', 'DIC', 'true', 'HIV', 'blood', '', '41936', '', '', '', '', 'ส่งตรวจแล้ว รอผล'],
        ];
    }
}
