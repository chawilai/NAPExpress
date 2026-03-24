<?php

namespace App\Services;

use App\Exports\ReportingTemplateExport;
use Maatwebsite\Excel\Facades\Excel;

class ExcelTemplateService
{
    /**
     * Generate an Excel template for a specific form type.
     */
    public function generateTemplate(string $formType)
    {
        $filename = 'template_' . strtolower(str_replace(' ', '_', $formType)) . '.xlsx';
        
        return Excel::download(new ReportingTemplateExport($formType), $filename);
    }
}
