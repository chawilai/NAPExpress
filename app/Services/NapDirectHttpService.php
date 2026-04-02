<?php

namespace App\Services;

use App\Models\ReportingJob;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NapDirectHttpService
{
    private const BASE_URL = 'https://dmis.nhso.go.th/NAPPLUS';

    private const RRTTR_URL = self::BASE_URL.'/rrttr/createRRTTR.do';

    /** Static NAP form reference data — constant for every submission. */
    private const RISK_BEHAVIORS = [
        ['value' => '1', 'name' => 'TG'],
        ['value' => '2', 'name' => 'MSM'],
        ['value' => '3', 'name' => 'SW'],
        ['value' => '4', 'name' => 'PWID'],
        ['value' => '5', 'name' => 'Migrant'],
        ['value' => '6', 'name' => 'Prisoner'],
    ];

    private const TARGET_GROUPS = [
        ['value' => '3', 'name' => 'MSM', 'master' => 'ROW_1_COL_1'],
        ['value' => '1', 'name' => 'PWID', 'master' => 'ROW_1_COL_2'],
        ['value' => '16', 'name' => 'ANC', 'master' => 'ROW_1_COL_3'],
        ['value' => '4', 'name' => 'TGW', 'master' => 'ROW_2_COL_1'],
        ['value' => '2', 'name' => 'PWUD', 'master' => 'ROW_2_COL_2'],
        ['value' => '17', 'name' => 'คลอดจากแม่ติดเชื้อเอชไอวี', 'master' => 'ROW_2_COL_3'],
        ['value' => '5', 'name' => 'TGM', 'master' => 'ROW_3_COL_1'],
        ['value' => '11', 'name' => 'Partner of KP', 'master' => 'ROW_3_COL_2'],
        ['value' => '14', 'name' => 'บุคลากรทางการแพทย์ (Health Personnel)', 'master' => 'ROW_3_COL_3'],
        ['value' => '10', 'name' => 'TGSW', 'master' => 'ROW_4_COL_1'],
        ['value' => '12', 'name' => 'Partner of PLHIV', 'master' => 'ROW_4_COL_2'],
        ['value' => '15', 'name' => 'nPEP', 'master' => 'ROW_4_COL_3'],
        ['value' => '8', 'name' => 'MSW', 'master' => 'ROW_5_COL_1'],
        ['value' => '7', 'name' => 'Prisoners', 'master' => 'ROW_5_COL_2'],
        ['value' => '13', 'name' => 'General Population', 'master' => 'ROW_5_COL_3'],
        ['value' => '9', 'name' => 'FSW', 'master' => 'ROW_6_COL_1'],
        ['value' => '6', 'name' => 'Migrant', 'master' => 'ROW_6_COL_2'],
        ['value' => '18', 'name' => 'สามี/คู่ของหญิงตั้งครรภ์', 'master' => 'ROW_6_COL_3'],
    ];

    private const KNOWLEDGE = [
        ['value' => '1', 'name' => 'ให้ความรู้เรื่อง เอชไอวี'],
        ['value' => '2', 'name' => 'ให้ความรู้เรื่อง โรคติดต่อทางเพศสัมพันธ์'],
        ['value' => '3', 'name' => 'ให้ความรู้เรื่อง วัณโรค'],
        ['value' => '4', 'name' => 'การลดอันตรายจากการใช้ยา'],
        ['value' => '5', 'name' => 'ให้ความรู้เรื่อง ไวรัสตับอักเสบซี'],
    ];

    private const PLACES = [
        ['value' => '1', 'name' => 'ให้ข้อมูลสถานที่ เอชไอวี'],
        ['value' => '2', 'name' => 'ให้ข้อมูลสถานที่ โรคติดต่อทางเพศสัมพันธ์'],
        ['value' => '3', 'name' => 'ให้ข้อมูลสถานที่ วัณโรค'],
        ['value' => '4', 'name' => 'ให้ข้อมูลสถานที่ การรับยา เมทาโดน'],
        ['value' => '5', 'name' => 'ให้ข้อมูลสถานที่ ไวรัสตับอักเสบซี (HCV)'],
    ];

    private const PPES = [
        ['value' => '1', 'name' => 'ถุงยางอนามัย'],
        ['value' => '2', 'name' => 'ถุงยางอนามัยผู้หญิง'],
        ['value' => '3', 'name' => 'สารหล่อลื่น'],
        ['value' => '4', 'name' => 'อุปกรณ์ฉีดยาปลอดเชื้อ'],
        ['value' => '5', 'name' => 'หน้ากากอนามัย'],
    ];

    private const OCCUPATIONS = [
        '01' => 'ไม่มี/ว่างงาน', '02' => 'เกษตรกร', '03' => 'รับจ้างทั่วไป',
        '04' => 'ช่างฝีมือ', '05' => 'เจ้าของกิจการ / ธุรกิจ', '06' => 'ข้าราชการทหาร',
        '07' => 'นักวิทยาศาสตร์และนักเทคนิก', '08' => 'บุคลากรด้านสาธารณสุข',
        '09' => 'นักวิชาชีพ/นักวิชาการ', '10' => 'ข้าราชการพลเรือนทั่วไป',
        '11' => 'พนักงานรัฐวิสาหกิจ', '12' => 'นักบวช/งานด้านศาสนา', '13' => 'อื่น ๆ',
        '14' => 'ข้าราชการตำรวจ', '15' => 'พนักงาน/ลูกจ้างบริษัท', '16' => 'ค้าขาย',
        '17' => 'กรรมกร, ผู้ใช้แรงงาน', '18' => 'ลูกจ้างโรงงาน', '19' => 'ขับรถรับจ้าง',
        '20' => 'นักเรียน/นักศึกษา', '21' => 'รับจ้างทำประมง', '22' => 'ขายบริการทางเพศ',
        '23' => 'นักแสดง นักร้อง นักดนตรี', '24' => 'พนักงานเสริฟท์ ทำงานบาร์',
        '25' => 'เสริมสวย', '26' => 'แม่บ้าน / งานบ้าน', '27' => 'ผู้ต้องขัง',
        '28' => 'เด็กต่ำกว่าวัยเรียน', '29' => 'ไม่ระบุอาชีพ',
    ];

    private const PAY_BY = [
        '1' => 'NHSO', '2' => 'Global Fund', '3' => 'PEPFAR',
        '4' => 'งบท้องถิ่น', '5' => 'งบแผ่นดิน',
    ];

    /**
     * Get occupation Thai name from code.
     */
    public static function occupationName(string $code): string
    {
        return self::OCCUPATIONS[$code] ?? '';
    }

    /**
     * Get pay_by name from code.
     */
    public static function payByName(string $code): string
    {
        return self::PAY_BY[$code] ?? '';
    }

    /**
     * Extract RR code from NAP success HTML.
     * Uses the LAST match — the confirm page may contain previous RR codes earlier in the HTML.
     */
    public static function extractRrCode(string $html): ?string
    {
        if (preg_match_all('/RR-\d{4}-\d+/', $html, $matches)) {
            return end($matches[0]);
        }

        return null;
    }

    /**
     * Extract error message from NAP HTML.
     */
    public static function extractError(string $html): ?string
    {
        if (preg_match('/<table class="alert">.*?<td class="text">(.*?)<\/td>/s', $html, $matches)) {
            return trim(strip_tags($matches[1]));
        }

        return null;
    }

    /**
     * Build the full POST body for the "preview" step (step 4).
     *
     * @param  array<string, mixed>  $rrForm  Data from CAREMAT API rr_form
     * @return array<string, string>
     */
    public static function buildPreviewBody(array $rrForm): array
    {
        $body = ['actionName' => 'preview'];

        // Risk Behaviors — all 6 static entries + checked status
        foreach (self::RISK_BEHAVIORS as $i => $rb) {
            $body["rrttr_risk_behavior_{$i}"] = $rb['value'];
            $body["rrttr_risk_behavior_name_{$i}"] = $rb['name'];

            if (in_array($i, $rrForm['risk_behavior_indices'] ?? [])) {
                $body["rrttr_risk_behavior_status_{$i}"] = 'Y';
            }
        }

        // Target Groups — all 18 static entries + checked status
        foreach (self::TARGET_GROUPS as $i => $tg) {
            $body["rrttr_master_value_{$i}"] = $tg['master'];
            $body["rrttr_target_group_{$i}"] = $tg['value'];
            $body["rrttr_target_group_name_{$i}"] = $tg['name'];

            if (in_array($i, $rrForm['target_group_indices'] ?? [])) {
                $body["rrttr_target_group_status_{$i}"] = 'Y';
            }
        }

        // Target group summary name
        $checkedTargetNames = [];

        foreach ($rrForm['target_group_indices'] ?? [] as $idx) {
            $checkedTargetNames[] = self::TARGET_GROUPS[$idx]['name'] ?? '';
        }
        $body['target_group'] = implode(',', $checkedTargetNames);

        // Partner with
        $body['partner_with'] = (string) ($rrForm['partner_with'] ?? '');
        $body['partner_with_name'] = '';

        // Access type
        $body['access_type'] = (string) ($rrForm['access_type'] ?? '2');

        // Social media
        $body['social_media'] = (string) ($rrForm['social_media'] ?? '');
        $body['social_media_name'] = '';

        // Pay by — default NHSO (1)
        $payBy = (string) ($rrForm['pay_by'] ?? '1');
        $body['pay_by'] = $payBy;
        $body['pay_by_name'] = self::payByName($payBy);

        // Address
        $body['ref_addr'] = (string) ($rrForm['address']['addr'] ?? '');
        $body['ref_province'] = (string) ($rrForm['address']['province'] ?? '');
        $body['ref_amphur'] = (string) ($rrForm['address']['amphur'] ?? '');
        $body['ref_tumbon'] = (string) ($rrForm['address']['tumbon'] ?? '');
        $body['ref_prov_name'] = '';
        $body['ref_amph_name'] = '';
        $body['ref_tumb_name'] = '';
        $body['ref_postal'] = (string) ($rrForm['address']['postal'] ?? '');
        $body['ref_tel'] = (string) ($rrForm['ref_tel'] ?? '');
        $body['ref_email'] = (string) ($rrForm['ref_email'] ?? '');

        // Occupation
        $occ = (string) ($rrForm['occupation'] ?? '');
        $body['occupation'] = $occ;
        $body['occupation_name'] = self::occupationName($occ);

        // SW work type — set to "นอกสถานบริการ" (2) when SW-related groups are selected
        $riskIndices = $rrForm['risk_behavior_indices'] ?? [];
        $targetIndices = $rrForm['target_group_indices'] ?? [];
        $isSw = in_array(2, $riskIndices)                // SW risk behavior
            || ! empty(array_intersect([9, 12, 15], $targetIndices)); // TGSW, MSW, FSW

        if ($isSw) {
            $body['sw_type'] = '2'; // นอกสถานบริการ
        }

        // Volunteer name (auto-filled by NAP from session)
        $body['volunteer_name'] = (string) ($rrForm['volunteer_name'] ?? '');

        // Knowledge — always check all 5 (ให้ความรู้ครบทุกข้อ)
        foreach (self::KNOWLEDGE as $i => $k) {
            $body["rrttr_knowledge_{$i}"] = $k['value'];
            $body["rrttr_knowledge_name_{$i}"] = $k['name'];
            $body["rrttr_knowledge_status_{$i}"] = 'Y';
        }

        // Place — all 5 static entries + checked status
        foreach (self::PLACES as $i => $p) {
            $body["rrttr_place_{$i}"] = $p['value'];
            $body["rrttr_place_name_{$i}"] = $p['name'];

            if (in_array($i, $rrForm['place_indices'] ?? [])) {
                $body["rrttr_place_status_{$i}"] = 'Y';
            }
        }

        // PPE — all 5 static entries + checked status
        foreach (self::PPES as $i => $ppe) {
            $body["rrttr_ppe_{$i}"] = $ppe['value'];
            $body["rrttr_ppe_name_{$i}"] = $ppe['name'];

            if (in_array($i, $rrForm['ppe_indices'] ?? [])) {
                $body["rrttr_ppe_status_{$i}"] = 'Y';
            }
        }

        // Condom amounts
        $condom = $rrForm['condom'] ?? [];

        foreach (['49', '52', '53', '54', '56'] as $size) {
            $amount = (int) ($condom[$size] ?? 0);
            $body["rrttr_condom_amount_{$size}"] = $amount > 0 ? (string) $amount : '';
        }
        $body['rrttr_female_condom_amount'] = ($rrForm['female_condom'] ?? 0) > 0 ? (string) $rrForm['female_condom'] : '';
        $body['rrttr_lubricant_amount'] = ($rrForm['lubricant'] ?? 0) > 0 ? (string) $rrForm['lubricant'] : '';

        // Healthcare referral
        $body['next_hcode'] = (string) ($rrForm['next_hcode'] ?? '');
        $body['next_hname'] = '';
        $body['next_hid'] = 'null';
        $body['next_place'] = '';

        // Forward services
        $forwards = $rrForm['forwards'] ?? [];
        $body['hiv_forward'] = (string) ($forwards['hiv'] ?? '2');
        $body['sti_forward'] = (string) ($forwards['sti'] ?? '2');
        $body['tb_forward'] = (string) ($forwards['tb'] ?? '2');
        $body['hcv_forward'] = (string) ($forwards['hcv'] ?? '3');
        $body['methadone_forward'] = (string) ($forwards['methadone'] ?? '3');

        return $body;
    }

    /**
     * Build a human-readable summary of rr_form data for dry run reports.
     *
     * @param  array<string, mixed>  $rrForm
     * @return array<string, mixed>
     */
    public static function summarizeRrForm(array $rrForm): array
    {
        $riskNames = [];

        foreach ($rrForm['risk_behavior_indices'] ?? [] as $idx) {
            $riskNames[] = self::RISK_BEHAVIORS[$idx]['name'] ?? "#{$idx}";
        }

        $targetNames = [];

        foreach ($rrForm['target_group_indices'] ?? [] as $idx) {
            $targetNames[] = self::TARGET_GROUPS[$idx]['name'] ?? "#{$idx}";
        }

        $knowledgeNames = [];

        foreach ($rrForm['knowledge_indices'] ?? [] as $idx) {
            $knowledgeNames[] = self::KNOWLEDGE[$idx]['name'] ?? "#{$idx}";
        }

        $ppeNames = [];

        foreach ($rrForm['ppe_indices'] ?? [] as $idx) {
            $ppeNames[] = self::PPES[$idx]['name'] ?? "#{$idx}";
        }

        $condom = $rrForm['condom'] ?? [];
        $condomParts = [];

        foreach (['49', '52', '53', '54', '56'] as $size) {
            $amount = (int) ($condom[$size] ?? 0);

            if ($amount > 0) {
                $condomParts[] = "ขนาด {$size}: {$amount}";
            }
        }

        if (($rrForm['female_condom'] ?? 0) > 0) {
            $condomParts[] = "หญิง: {$rrForm['female_condom']}";
        }

        if (($rrForm['lubricant'] ?? 0) > 0) {
            $condomParts[] = "สารหล่อลื่น: {$rrForm['lubricant']}";
        }

        $forwards = $rrForm['forwards'] ?? [];
        $forwardMap = ['1' => 'ส่งต่อ', '2' => 'ไม่ส่งต่อ', '3' => 'ไม่เกี่ยวข้อง'];
        $forwardParts = [];

        foreach (['hiv' => 'HIV', 'sti' => 'STI', 'tb' => 'TB', 'hcv' => 'HCV', 'methadone' => 'Methadone'] as $key => $label) {
            $val = (string) ($forwards[$key] ?? '');

            if ($val === '1') {
                $forwardParts[] = $label;
            }
        }

        return [
            'date' => $rrForm['rrttrDate'] ?? '',
            'risk_behaviors' => implode(', ', $riskNames) ?: '-',
            'target_groups' => implode(', ', $targetNames) ?: '-',
            'occupation' => self::occupationName((string) ($rrForm['occupation'] ?? '')),
            'pay_by' => self::payByName((string) ($rrForm['pay_by'] ?? '')),
            'knowledge' => implode(', ', $knowledgeNames) ?: '-',
            'ppe' => implode(', ', $ppeNames) ?: '-',
            'condom' => implode(', ', $condomParts) ?: '-',
            'forwards' => $forwardParts ? implode(', ', $forwardParts) : 'ไม่มีส่งต่อ',
            'next_hcode' => $rrForm['next_hcode'] ?? '-',
        ];
    }

    /**
     * Create a Guzzle HTTP client with shared cookie jar for session persistence.
     *
     * @param  array<int, array<string, mixed>>|null  $playwrightCookies  Cookies from Playwright browser
     */
    private function createGuzzleClient(?array $playwrightCookies = null): Client
    {
        $jar = new CookieJar;

        // Import cookies from Playwright (ThaiID login)
        if ($playwrightCookies) {
            foreach ($playwrightCookies as $cookie) {
                $jar->setCookie(new SetCookie([
                    'Name' => $cookie['name'],
                    'Value' => $cookie['value'],
                    'Domain' => $cookie['domain'] ?? '',
                    'Path' => $cookie['path'] ?? '/',
                    'Secure' => $cookie['secure'] ?? false,
                    'HttpOnly' => $cookie['httpOnly'] ?? false,
                ]));
            }
        }

        return new Client([
            'cookies' => $jar,
            'verify' => false,
            'timeout' => 30,
            'connect_timeout' => 10,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ],
            'allow_redirects' => true,
        ]);
    }

    /**
     * POST form data via Guzzle.
     *
     * @param  array<string, string>  $formParams
     */
    private function postForm(Client $client, string $url, array $formParams): string
    {
        $response = $client->post($url, ['form_params' => $formParams]);

        return (string) $response->getBody();
    }

    /**
     * Create a Guzzle client from Playwright cookies for reuse across multiple records.
     *
     * @param  array<int, array<string, mixed>>  $cookies  Playwright cookies
     */
    public function createClientFromCookies(array $cookies): Client
    {
        return $this->createGuzzleClient($cookies);
    }

    /**
     * Submit using pre-authenticated cookies (from ThaiID Playwright login).
     * Skips login step — goes straight to form submission (steps 2-5).
     *
     * @param  array<int, array<string, mixed>>  $cookies  Playwright cookies
     * @param  array<string, mixed>  $rrForm
     * @param  bool  $dryRun  Stop after preview step (don't actually save)
     * @return array{success: bool, nap_code: ?string, error: ?string}
     */
    public function submitWithCookies(array $cookies, array $rrForm, bool $dryRun = false): array
    {
        $client = $this->createGuzzleClient($cookies);

        return $this->submitWithClient($client, $rrForm, $dryRun);
    }

    /**
     * Submit a single record using an existing authenticated Guzzle client.
     * Reusing the same client preserves the NAP session (cookie jar) across records.
     *
     * @param  array<string, mixed>  $rrForm
     * @param  bool  $dryRun  Stop after preview step (don't actually save)
     * @return array{success: bool, nap_code: ?string, error: ?string}
     */
    public function submitWithClient(Client $client, array $rrForm, bool $dryRun = false): array
    {
        try {
            // Skip step 1 (login) — cookies already authenticated

            // Step 2: Search
            $searchBody = $this->postForm($client, self::RRTTR_URL, [
                'actionName' => 'search',
                'gr_type' => '0',
                'rrttrDate' => $rrForm['rrttrDate'],
                'pid' => $rrForm['pid'],
                'rrttrDateAnonym' => '',
                'uic' => '',
            ]);

            if ($error = self::extractError($searchBody)) {
                return ['success' => false, 'nap_code' => null, 'error' => $error];
            }

            // Check if redirected to login (session expired)
            if (str_contains($searchBody, 'login.jsp') || str_contains($searchBody, 'iam.nhso.go.th')) {
                return ['success' => false, 'nap_code' => null, 'error' => 'Session expired — ต้อง login ใหม่'];
            }

            // Step 3: Input
            $inputBody = $this->postForm($client, self::RRTTR_URL, [
                'actionName' => 'input',
                'gotoLog' => 'N',
                'pid' => $rrForm['pid'],
                'rrttrDate' => $rrForm['rrttrDate'],
                'confirm_right' => 'null',
            ]);

            if ($error = self::extractError($inputBody)) {
                return ['success' => false, 'nap_code' => null, 'error' => $error];
            }

            // Step 4: Preview
            $previewBody = self::buildPreviewBody($rrForm);
            $previewResult = $this->postForm($client, self::RRTTR_URL, $previewBody);

            if ($error = self::extractError($previewResult)) {
                return ['success' => false, 'nap_code' => null, 'error' => $error];
            }

            // Dry run: stop after preview — don't actually save
            if ($dryRun) {
                return ['success' => true, 'nap_code' => null, 'error' => null];
            }

            // Step 5: Confirm
            $confirmBody = $this->postForm($client, self::RRTTR_URL, [
                'actionName' => 'confirm',
            ]);

            // Log confirm page for debugging RR code extraction
            Log::debug('NAP confirm response', [
                'pid' => $rrForm['pid'],
                'html_length' => strlen($confirmBody),
                'html_snippet' => substr($confirmBody, 0, 1000),
            ]);

            $rrCode = self::extractRrCode($confirmBody);

            if ($rrCode) {
                Log::info('NAP confirm RR code extracted', ['rr_code' => $rrCode, 'pid' => $rrForm['pid']]);

                return ['success' => true, 'nap_code' => $rrCode, 'error' => null];
            }

            $error = self::extractError($confirmBody);
            Log::warning('NAP confirm: no RR code found', ['pid' => $rrForm['pid'], 'html_snippet' => substr($confirmBody, 0, 500)]);

            return ['success' => false, 'nap_code' => null, 'error' => $error ?? 'ไม่พบรหัส RR ในผลลัพธ์'];
        } catch (\Exception $e) {
            Log::error('NapDirectHttp (cookies) error: '.$e->getMessage());

            return ['success' => false, 'nap_code' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Submit a single Reach RR record via Direct HTTP POST.
     * Uses a shared cookie jar so the NAP session persists across all 5 steps.
     *
     * @param  array<string, mixed>  $rrForm  From CAREMAT API rr_form
     * @return array{success: bool, nap_code: ?string, error: ?string}
     */
    public function submitRecord(array $credentials, array $rrForm): array
    {
        $client = $this->createGuzzleClient();

        try {
            // Step 1: Login
            $loginBody = $this->postForm($client, self::BASE_URL.'/login.do', [
                'actionName' => 'login',
                'user_name' => $credentials['username'],
                'password' => $credentials['password'],
            ]);

            if (str_contains($loginBody, 'login.jsp') || str_contains($loginBody, 'ไม่ถูกต้อง')) {
                return ['success' => false, 'nap_code' => null, 'error' => 'Login failed'];
            }

            // Step 2: Search
            $searchBody = $this->postForm($client, self::RRTTR_URL, [
                'actionName' => 'search',
                'gr_type' => '0',
                'rrttrDate' => $rrForm['rrttrDate'],
                'pid' => $rrForm['pid'],
                'rrttrDateAnonym' => '',
                'uic' => '',
            ]);

            if ($error = self::extractError($searchBody)) {
                return ['success' => false, 'nap_code' => null, 'error' => $error];
            }

            // Step 3: Input (confirm person)
            $inputBody = $this->postForm($client, self::RRTTR_URL, [
                'actionName' => 'input',
                'gotoLog' => 'N',
                'pid' => $rrForm['pid'],
                'rrttrDate' => $rrForm['rrttrDate'],
                'confirm_right' => 'null',
            ]);

            if ($error = self::extractError($inputBody)) {
                return ['success' => false, 'nap_code' => null, 'error' => $error];
            }

            // Step 4: Preview (submit all form data)
            $previewBody = self::buildPreviewBody($rrForm);
            $previewResult = $this->postForm($client, self::RRTTR_URL, $previewBody);

            if ($error = self::extractError($previewResult)) {
                return ['success' => false, 'nap_code' => null, 'error' => $error];
            }

            // Step 5: Confirm (final save)
            $confirmBody = $this->postForm($client, self::RRTTR_URL, [
                'actionName' => 'confirm',
            ]);

            $rrCode = self::extractRrCode($confirmBody);

            if ($rrCode) {
                return ['success' => true, 'nap_code' => $rrCode, 'error' => null];
            }

            $error = self::extractError($confirmBody);

            return ['success' => false, 'nap_code' => null, 'error' => $error ?? 'ไม่พบรหัส RR ในผลลัพธ์'];
        } catch (\Exception $e) {
            Log::error('NapDirectHttp error: '.$e->getMessage());

            return ['success' => false, 'nap_code' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process a full reporting job via Direct HTTP POST.
     *
     * @param  string  $callbackMode  'realtime' = send per record, 'batch' = send all at end, 'both' = both
     */
    public function processJob(
        ReportingJob $job,
        array $credentials,
        string $callbackMode = 'realtime',
        ?AblyProgressService $progress = null,
    ): void {
        $job->update(['status' => 'processing']);

        $rows = $job->jobRows()->where('status', 'pending')->orderBy('row_number')->get();
        $callbackUrl = NapCallbackService::defaultUrl();
        $total = $rows->count();
        $success = 0;
        $failed = 0;
        $batchItems = [];
        $jobId = $job->id;

        // Phase 1: Start
        $progress?->jobStart($jobId, $total, $job->organization->name ?? 'AutoNAP');
        $progress?->connecting($jobId);
        $progress?->loginStart($jobId);

        // Login is done inside submitRecord per-record, but we show it as a single phase
        $progress?->loginSuccess($jobId);
        $progress?->preparing($jobId, $total);

        // Phase 2: Process each record
        foreach ($rows as $index => $row) {
            $rrForm = $row->row_data['rr_form'] ?? $row->row_data;
            $uic = $row->row_data['identification']['uic'] ?? '';
            $i = $index + 1;

            // Sub-step events per record
            $progress?->recordProcessing($jobId, $i, $total, $row->pid_masked, $uic);
            $progress?->recordSearching($jobId, $i, $total);

            $result = $this->submitRecord($credentials, $rrForm);

            // These events fire after the actual work, but with delays they look natural
            $progress?->recordFilling($jobId, $i, $total);
            $progress?->recordSubmitting($jobId, $i, $total);

            $rowStatus = $result['success'] ? 'success' : 'failed';
            $row->update([
                'status' => $rowStatus,
                'nap_response_code' => $result['nap_code'],
                'error_message' => $result['error'],
            ]);

            $result['success'] ? $success++ : $failed++;

            $job->update([
                'counts' => [
                    'total' => $job->counts['total'],
                    'success' => $success,
                    'failed' => $failed,
                ],
            ]);

            // Result event
            if ($result['success']) {
                $progress?->recordSuccess($jobId, $i, $total, $result['nap_code'], $uic);
            } else {
                $progress?->recordFailed($jobId, $i, $total, $result['error'] ?? '', $uic);
            }

            $payload = NapCallbackService::buildPayload(
                $row->row_data,
                $result['nap_code'],
                $rowStatus,
                $result['error'] ?? '',
            );

            if (in_array($callbackMode, ['realtime', 'both'])) {
                NapCallbackService::send($payload, $callbackUrl);
            }

            if (in_array($callbackMode, ['batch', 'both'])) {
                $batchItems[] = $payload;
            }
        }

        // Phase 3: Complete
        $job->update(['status' => 'completed']);

        $progress?->summarizing($jobId);
        $progress?->jobComplete($jobId, $total, $success, $failed);

        // Batch: send all results at once
        if (! empty($batchItems)) {
            NapCallbackService::sendBatch($batchItems, $callbackUrl);
        }
    }
}
