<?php

namespace App\Services;

use Carbon\Carbon;

class ReachRrValidator
{
    private const VALID_KP_CODES = [
        'MSM', 'MSW', 'FSW', 'TG', 'TGW', 'TGM', 'TGSW',
        'PWID', 'MIGRANT', 'PRISONER', 'MALE', 'FEMALE',
    ];

    private const CONDOM_FIELDS = [
        'condom_49', 'condom_52', 'condom_53', 'condom_54', 'condom_56',
        'female_condom', 'lubricant',
    ];

    private const FORWARD_FIELDS = ['hiv_forward', 'sti_forward', 'tb_forward'];

    /**
     * Validate a single Excel row for Reach RR form.
     *
     * @param  array<string, mixed>  $row
     */
    public static function validateRow(array $row, int $rowNumber = 0): ValidationResult
    {
        $errors = [];

        // PID: required, exactly 13 digits
        $pid = trim((string) ($row['pid'] ?? ''));

        if ($pid === '') {
            $errors['pid'] = 'PID is required.';
        } elseif (! preg_match('/^\d{13}$/', $pid)) {
            $errors['pid'] = 'PID must be exactly 13 digits.';
        }

        // UIC: required, at least 6 chars for birthdate extraction
        $uic = trim((string) ($row['uic'] ?? ''));

        if ($uic === '') {
            $errors['uic'] = 'UIC is required.';
        } elseif (mb_strlen($uic) < 6) {
            $errors['uic'] = 'UIC must be at least 6 characters.';
        }

        // KP: required, must be in valid list
        $kp = trim((string) ($row['kp'] ?? ''));

        if ($kp === '') {
            $errors['kp'] = 'KP (Key Population) is required.';
        } elseif (! in_array(strtoupper($kp), self::VALID_KP_CODES, true)) {
            $errors['kp'] = 'Invalid KP code. Valid: '.implode(', ', self::VALID_KP_CODES);
        }

        // Service Date: required, valid date format
        $serviceDate = trim((string) ($row['service_date'] ?? ''));

        if ($serviceDate === '') {
            $errors['service_date'] = 'Service date is required.';
        } else {
            try {
                Carbon::parse($serviceDate);
            } catch (\Exception) {
                $errors['service_date'] = 'Service date must be a valid date (YYYY-MM-DD).';
            }
        }

        // Access Type: optional, but if provided must be 1-3
        $accessType = $row['access_type'] ?? null;

        if ($accessType !== null && $accessType !== '' && ! in_array((int) $accessType, [1, 2, 3], true)) {
            $errors['access_type'] = 'Access type must be 1 (DIC), 2 (นอก DIC), or 3 (Social Media).';
        }

        // Forward services: optional, but if provided must be 1-3
        foreach (self::FORWARD_FIELDS as $field) {
            $value = $row[$field] ?? null;

            if ($value !== null && $value !== '' && ! in_array((int) $value, [1, 2, 3], true)) {
                $errors[$field] = "{$field} must be 1, 2, or 3.";
            }
        }

        // Condom amounts: non-negative integers
        foreach (self::CONDOM_FIELDS as $field) {
            $value = $row[$field] ?? 0;

            if ((int) $value < 0) {
                $errors[$field] = "{$field} cannot be negative.";
            }
        }

        return new ValidationResult($rowNumber, $errors);
    }

    /**
     * Validate multiple rows. Row numbers start at 2 (Excel row after header).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, ValidationResult>
     */
    public static function validateRows(array $rows): array
    {
        $results = [];

        foreach ($rows as $index => $row) {
            $results[] = self::validateRow($row, $index + 2);
        }

        return $results;
    }
}
