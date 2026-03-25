<?php

namespace App\Services;

use Carbon\Carbon;

class ReachRrMapper
{
    /**
     * KP → Risk Behavior checkbox indices.
     *
     * @return array<int>
     */
    public static function riskBehaviorIndices(string $kp): array
    {
        $map = [
            'MSM' => [1],
            'MSW' => [2],
            'FSW' => [2],
            'TG' => [0],
            'TGW' => [0],
            'TGM' => [0],
            'TGSW' => [0],
            'PWID' => [3],
            'MIGRANT' => [4],
            'PRISONER' => [5],
            'MALE' => [],
            'FEMALE' => [],
        ];

        return $map[strtoupper($kp)] ?? [];
    }

    /**
     * KP → Target Group checkbox indices.
     *
     * @return array<int>
     */
    public static function targetGroupIndices(string $kp): array
    {
        $map = [
            'MSM' => [0],
            'MSW' => [12],
            'FSW' => [15],
            'TG' => [3],
            'TGW' => [3],
            'TGM' => [6],
            'TGSW' => [9],
            'PWID' => [1],
            'MIGRANT' => [16],
            'PRISONER' => [13],
            'MALE' => [14],
            'FEMALE' => [14],
        ];

        return $map[strtoupper($kp)] ?? [14];
    }

    /**
     * Knowledge checkbox indices — PWID gets extra indices 3 (Harm Reduction) + 4 (HCV).
     *
     * @return array<int>
     */
    public static function knowledgeIndices(string $kp): array
    {
        $base = [0, 1, 2]; // HIV, STD, TB

        if (strtoupper($kp) === 'PWID') {
            return [...$base, 3, 4]; // + Harm Reduction, HCV
        }

        return $base;
    }

    /**
     * PPE checkbox indices — PWID gets extra index 3 (อุปกรณ์ฉีดยาปลอดเชื้อ).
     *
     * @return array<int>
     */
    public static function ppeIndices(string $kp): array
    {
        $base = [0, 2]; // ถุงยางชาย, สารหล่อลื่น

        if (strtoupper($kp) === 'PWID') {
            return [...$base, 3]; // + อุปกรณ์ฉีดยาปลอดเชื้อ
        }

        return $base;
    }

    /**
     * Map Thai occupation text to NAP occupation code (substring, case-insensitive).
     */
    public static function occupationCode(string $text): string
    {
        $text = mb_strtolower(trim($text));

        if ($text === '') {
            return '03';
        }

        $mappings = [
            '01' => ['นักเรียน', 'นักศึกษา', 'student'],
            '02' => ['ข้าราชการ', 'government'],
            '03' => ['รับจ้าง', 'แรงงาน', 'general'],
            '04' => ['พนักงานบริษัท', 'office'],
            '05' => ['ค้าขาย', 'self-employed', 'ธุรกิจ'],
            '06' => ['ว่างงาน', 'unemployed'],
        ];

        foreach ($mappings as $code => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($text, mb_strtolower($keyword))) {
                    return $code;
                }
            }
        }

        return '03'; // DEFAULT: รับจ้าง/แรงงาน
    }

    /**
     * Extract birthdate from UIC (last 6 chars = DDMMYY).
     */
    public static function uicToBirthdate(string $uic): ?string
    {
        if (mb_strlen($uic) < 6) {
            return null;
        }

        $last6 = substr($uic, -6);
        $day = substr($last6, 0, 2);
        $month = substr($last6, 2, 2);
        $yy = (int) substr($last6, 4, 2);

        $year = $yy > 70 ? 1900 + $yy : 2000 + $yy;

        return sprintf('%04d-%02d-%02d', $year, (int) $month, (int) $day);
    }

    /**
     * Convert CE date (YYYY-MM-DD) to Thai Buddhist Era (dd/mm/yyyy).
     */
    public static function toThaiDate(string $date): ?string
    {
        try {
            $carbon = Carbon::parse($date);

            return $carbon->format('d/m/').($carbon->year + 543);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Convert UIC to Thai birthdate string (dd/mm/yyyy Buddhist Era).
     */
    public static function uicToThaiBirthdate(string $uic): ?string
    {
        $birthdate = self::uicToBirthdate($uic);

        if (! $birthdate) {
            return null;
        }

        return self::toThaiDate($birthdate);
    }

    /**
     * Map access type value to NAP form selector.
     */
    public static function accessTypeSelector(string|int|null $value): string
    {
        $v = (int) $value;

        if ($v >= 1 && $v <= 3) {
            return "#access_type_{$v}";
        }

        return '#access_type_2'; // Default: นอก DIC
    }

    /**
     * Map forward service type and value to NAP form selector.
     */
    public static function forwardSelector(string $service, ?int $value): string
    {
        $v = $value ?? 3; // Default: ไม่ส่งต่อ

        return "#{$service}_forward_{$v}";
    }

    /**
     * Build complete form data array from an Excel row for Playwright.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function buildFormData(array $row): array
    {
        $kp = $row['kp'] ?? 'MALE';

        return [
            'pid' => $row['pid'],
            'risk_behavior_indices' => self::riskBehaviorIndices($kp),
            'target_group_indices' => self::targetGroupIndices($kp),
            'knowledge_indices' => self::knowledgeIndices($kp),
            'ppe_indices' => self::ppeIndices($kp),
            'occupation_code' => self::occupationCode($row['occupation'] ?? ''),
            'access_type_selector' => self::accessTypeSelector($row['access_type'] ?? null),
            'service_date_thai' => self::toThaiDate($row['service_date'] ?? ''),
            'birthdate_thai' => self::uicToThaiBirthdate($row['uic'] ?? ''),
            'condom_49' => (int) ($row['condom_49'] ?? 0),
            'condom_52' => self::condomWithDefault((int) ($row['condom_52'] ?? 0), 20),
            'condom_53' => (int) ($row['condom_53'] ?? 0),
            'condom_54' => self::condomWithDefault((int) ($row['condom_54'] ?? 0), 20),
            'condom_56' => self::condomWithDefault((int) ($row['condom_56'] ?? 0), 20),
            'female_condom' => (int) ($row['female_condom'] ?? 0),
            'lubricant' => self::condomWithDefault((int) ($row['lubricant'] ?? 0), 20),
            'next_hcode' => $row['next_hcode'] ?? '',
            'hiv_forward_selector' => self::forwardSelector('hiv', $row['hiv_forward'] ?? null),
            'sti_forward_selector' => self::forwardSelector('sti', $row['sti_forward'] ?? null),
            'tb_forward_selector' => self::forwardSelector('tb', $row['tb_forward'] ?? null),
        ];
    }

    /**
     * Apply default condom/lubricant amount when value is 0.
     */
    private static function condomWithDefault(int $value, int $default): int
    {
        return $value > 0 ? $value : $default;
    }
}
