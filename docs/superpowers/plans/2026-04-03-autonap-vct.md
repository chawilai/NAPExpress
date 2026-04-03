# AutoNAP VCT Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extend AutoNAP to record VCT forms on NAP Plus with optional HIV lab request, using the same ThaiID login flow as RR.

**Architecture:** Add `form_type` routing to the existing pipeline (Controller → Job → Playwright script). The Playwright script gains two new functions (`fillAndSubmitVCT`, `fillAndSubmitLabRequest`) alongside the existing `fillAndSubmitRecord` for RR. Callback service adds `form_type` and `nap_lab_code` fields.

**Tech Stack:** Laravel 13 (PHP 8.4), Playwright (Node.js CJS), Ably WebSocket

**Spec:** `docs/superpowers/specs/2026-04-03-autonap-vct-design.md`

---

## File Map

| File | Action | Responsibility |
|------|--------|---------------|
| `app/Http/Controllers/Api/AutoNapJobController.php` | Modify | Add `form_type` validation, conditional `rr_form` requirement |
| `app/Jobs/ProcessAutoNapJob.php` | Modify | Add `form_type` param, pass to data file, handle VCT results |
| `app/Services/NapCallbackService.php` | Modify | Add `form_type`, `nap_lab_code`, `row_id` to payload |
| `automation/thaid_login_and_record.cjs` | Modify | Add VCT + Lab filling functions, route by `form_type` |
| `routes/api.php` | Modify | Add test callback endpoint |

---

## Task 1: Dummy Test Callback Endpoint

**Files:**
- Modify: `routes/api.php`

- [ ] **Step 1: Add test callback route**

```php
// routes/api.php
<?php

use App\Http\Controllers\Api\AutoNapJobController;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

Route::post('jobs', [AutoNapJobController::class, 'store']);

Route::post('test-callback', function (\Illuminate\Http\Request $request) {
    Log::info('Test callback received', $request->all());

    return response()->json([
        'status' => 'ok',
        'action' => 'logged',
        'received' => $request->all(),
    ]);
});
```

- [ ] **Step 2: Test the endpoint**

Run: `php artisan serve &` then:
```bash
curl -X POST http://localhost:8000/api/test-callback \
  -H "Content-Type: application/json" \
  -d '{"test": true, "nap_code": "V68-TEST"}'
```
Expected: `{"status":"ok","action":"logged","received":{"test":true,"nap_code":"V68-TEST"}}`

- [ ] **Step 3: Commit**

```bash
git add routes/api.php
git commit -m "feat: add dummy test-callback endpoint for AutoNAP testing"
```

---

## Task 2: Update NapCallbackService — Add `form_type`, `nap_lab_code`, `row_id`

**Files:**
- Modify: `app/Services/NapCallbackService.php:16-41`

- [ ] **Step 1: Update buildPayload to accept and include new fields**

In `app/Services/NapCallbackService.php`, replace the `buildPayload` method:

```php
public static function buildPayload(
    array $rowData,
    ?string $napCode,
    string $status,
    string $comment = '',
    ?string $napLabCode = null,
    string $formType = 'RR',
): array {
    $rrForm = $rowData['rr_form'] ?? [];
    $identification = $rowData['identification'] ?? [];
    $person = $rowData['person'] ?? [];
    $context = $rowData['context'] ?? [];
    $service = $rowData['service'] ?? [];

    // Support both nested (ReportingJob) and flat (ProcessAutoNapJob) item structures
    return [
        'form_type' => $formType,
        'source_id' => $service['source_id'] ?? $rowData['source_id'] ?? null,
        'source' => $context['source'] ?? $rowData['source'] ?? null,
        'uic' => $identification['uic'] ?? $rowData['uic'] ?? null,
        'id_card' => $identification['pid'] ?? $rrForm['pid'] ?? $rowData['id_card'] ?? null,
        'kp' => $person['kp'] ?? $rowData['kp'] ?? null,
        'fy' => $context['fy'] ?? $rowData['fy'] ?? null,
        'nap_code' => $napCode,
        'nap_lab_code' => $napLabCode,
        'nap_comment' => trim(($comment ?: '').' AutoNAP'),
        'nap_staff' => $rowData['cbs'] ?? 'AutoNAP',
        'status' => $status,
        'row_id' => $rowData['row_id'] ?? null,
    ];
}
```

- [ ] **Step 2: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 3: Commit**

```bash
git add app/Services/NapCallbackService.php
git commit -m "feat: add form_type, nap_lab_code, row_id to callback payload"
```

---

## Task 3: Update AutoNapJobController — Accept `form_type`, conditional validation

**Files:**
- Modify: `app/Http/Controllers/Api/AutoNapJobController.php`

- [ ] **Step 1: Update validation and dispatch**

Replace the full `store` method in `app/Http/Controllers/Api/AutoNapJobController.php`:

```php
public function store(Request $request): JsonResponse
{
    Log::info('AutoNAP job request received', [
        'site' => $request->input('site'),
        'form_type' => $request->input('form_type', 'RR'),
        'items_count' => count($request->input('items', [])),
    ]);

    $formType = strtoupper($request->input('form_type', 'RR'));

    // Base validation rules
    $rules = [
        'site' => ['required', 'string'],
        'fy' => ['required', 'string'],
        'form_type' => ['nullable', 'string'],
        'method' => ['nullable', 'string'],
        'dry_run' => ['nullable', 'boolean'],
        'nap_username' => ['nullable', 'string'],
        'nap_password' => ['nullable', 'string'],
        'callback_url' => ['required', 'url'],
        'ably_channel' => ['nullable', 'string'],
        'items' => ['required', 'array', 'min:1'],
        'items.*.source_id' => ['required'],
        'items.*.source' => ['required', 'string'],
        'items.*.id_card' => ['required', 'string', 'size:13'],
    ];

    // RR requires rr_form; VCT requires service_date + kp
    if ($formType === 'VCT') {
        $rules['items.*.service_date'] = ['required', 'string'];
        $rules['items.*.kp'] = ['required', 'string'];
        $rules['items.*.cbs'] = ['required', 'string'];
    } else {
        $rules['items.*.rr_form'] = ['required', 'array'];
        $rules['items.*.rr_form.pid'] = ['required', 'string'];
        $rules['items.*.rr_form.rrttrDate'] = ['required', 'string'];
    }

    $validated = $request->validate($rules);

    // Normalize method
    $rawMethod = strtolower($validated['method'] ?? 'thaid');
    $method = match (true) {
        str_contains($rawMethod, 'direct'), str_contains($rawMethod, 'http') => 'DirectHTTP',
        str_contains($rawMethod, 'playwright') => 'ThaiID',
        default => 'ThaiID',
    };

    if ($method === 'DirectHTTP' && (empty($validated['nap_username']) || empty($validated['nap_password']))) {
        return response()->json([
            'status' => 'error',
            'message' => 'nap_username and nap_password required for DirectHTTP method',
        ], 422);
    }

    $jobId = 'autonap-'.bin2hex(random_bytes(8));

    // Use full items from request (not $validated which strips extra fields)
    $items = $request->input('items');

    ProcessAutoNapJob::dispatch(
        jobId: $jobId,
        site: $validated['site'],
        fy: $validated['fy'],
        credentials: [
            'username' => $validated['nap_username'] ?? '',
            'password' => $validated['nap_password'] ?? '',
        ],
        callbackUrl: $validated['callback_url'],
        ablyChannel: $validated['ably_channel'] ?? null,
        items: $items,
        method: $method,
        dryRun: (bool) ($validated['dry_run'] ?? false),
        formType: $formType,
    );

    return response()->json([
        'status' => 'ok',
        'job_id' => $jobId,
        'method' => $method,
        'form_type' => $formType,
        'total' => count($validated['items']),
        'message' => $method === 'ThaiID'
            ? 'Job dispatched. QR code will be sent via Ably — scan with ThaiD app.'
            : 'Job dispatched. Subscribe to Ably channel for progress.',
        'ably_channel' => $validated['ably_channel'],
    ]);
}
```

- [ ] **Step 2: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/Api/AutoNapJobController.php
git commit -m "feat: support form_type in AutoNAP job controller (VCT/RR)"
```

---

## Task 4: Update ProcessAutoNapJob — Add `formType`, pass to Playwright, handle VCT results

**Files:**
- Modify: `app/Jobs/ProcessAutoNapJob.php`

- [ ] **Step 1: Add formType parameter and update handleThaiIdFlow**

Replace full `app/Jobs/ProcessAutoNapJob.php`:

```php
<?php

namespace App\Jobs;

use App\Services\AblyProgressService;
use App\Services\NapCallbackService;
use App\Services\NapDirectHttpService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class ProcessAutoNapJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    /**
     * @param  array<string, string>  $credentials  NAP credentials (empty for ThaiID)
     * @param  array<int, array<string, mixed>>  $items  Each item contains form data
     */
    public function __construct(
        public string $jobId,
        public string $site,
        public string $fy,
        public array $credentials,
        public string $callbackUrl,
        public ?string $ablyChannel,
        public array $items,
        public string $method = 'ThaiID',
        public bool $dryRun = false,
        public string $formType = 'RR',
    ) {}

    public function handle(): void
    {
        match ($this->method) {
            'DirectHTTP' => $this->handleDirectHttpFlow(),
            default => $this->handleThaiIdFlow(),
        };
    }

    protected function handleThaiIdFlow(): void
    {
        $ablyKey = $this->getAblyKey();
        $total = count($this->items);

        $dataFile = storage_path("app/private/thaid_{$this->jobId}.json");
        file_put_contents($dataFile, json_encode([
            'ablyKey' => $ablyKey,
            'ablyChannel' => $this->ablyChannel,
            'formType' => $this->formType,
            'items' => $this->items,
            'dryRun' => $this->dryRun,
        ], JSON_UNESCAPED_UNICODE));

        $process = new Process([
            'node',
            base_path('automation/thaid_login_and_record.cjs'),
            '--jobId='.$this->jobId,
            '--dataFile='.$dataFile,
        ]);
        $process->setTimeout($this->timeout);

        Log::info("ThaiID: Starting {$this->formType} for job {$this->jobId}", [
            'total' => $total,
            'formType' => $this->formType,
            'dryRun' => $this->dryRun,
        ]);

        $process->run(function ($type, $buffer) {
            Log::info("ThaiID [{$type}]: {$buffer}");
        });

        $resultsFile = str_replace('.json', '_results.json', $dataFile);

        if (! file_exists($resultsFile)) {
            Log::error("ThaiID: No results file — script failed for job {$this->jobId}");
            $this->cleanup($dataFile);

            return;
        }

        $resultsData = json_decode(file_get_contents($resultsFile), true);
        $results = $resultsData['results'] ?? [];

        $success = 0;
        $failed = 0;

        foreach ($results as $index => $result) {
            $item = $this->items[$index] ?? [];
            $item['fy'] = $this->fy;

            $isSuccess = $result['success'] ?? false;
            $napCode = $result['nap_code'] ?? null;
            $napLabCode = $result['nap_lab_code'] ?? null;
            $error = $result['error'] ?? '';

            if ($isSuccess) {
                $success++;
            } else {
                $failed++;
            }

            NapCallbackService::send(
                NapCallbackService::buildPayload(
                    $item,
                    $napCode,
                    'success',
                    $error,
                    $napLabCode,
                    $this->formType,
                ),
                $this->callbackUrl,
            );
        }

        Log::info("AutoNAP {$this->formType} job completed: {$this->jobId}", compact('total', 'success', 'failed'));

        $this->cleanup($dataFile, $resultsFile);
    }

    protected function handleDirectHttpFlow(): void
    {
        $progress = $this->createProgress();
        $napService = new NapDirectHttpService;

        $napService->processJob(
            job: null,
            credentials: $this->credentials,
            callbackMode: 'realtime',
            progress: $progress,
            items: $this->items,
            callbackUrl: $this->callbackUrl,
        );
    }

    protected function getAblyKey(): string
    {
        $key = config('services.ably.key', '');

        if (empty($key)) {
            try {
                $key = \DB::connection('carematdb')->table('site_specific')->value('ably_key') ?? '';
            } catch (\Exception $e) {
                Log::warning('Could not get Ably key: '.$e->getMessage());
            }
        }

        return $key;
    }

    protected function createProgress(): ?AblyProgressService
    {
        if (! $this->ablyChannel) {
            return null;
        }

        $ablyKey = $this->getAblyKey();

        return ! empty($ablyKey) ? new AblyProgressService($ablyKey, $this->ablyChannel) : null;
    }

    protected function cleanup(string ...$files): void
    {
        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
}
```

- [ ] **Step 2: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 3: Commit**

```bash
git add app/Jobs/ProcessAutoNapJob.php
git commit -m "feat: pass formType through ProcessAutoNapJob to Playwright and callback"
```

---

## Task 5: Playwright — Add VCT form filling function

**Files:**
- Modify: `automation/thaid_login_and_record.cjs`

This is the largest task. We add VCT URLs, KP mapping, and `fillAndSubmitVCT()`.

- [ ] **Step 1: Add VCT constants after the existing NAP_URLS (line 22-25)**

After the existing `NAP_URLS` object, add VCT URLs and KP mapping. Replace the `NAP_URLS` block:

```javascript
const NAP_URLS = {
    createRR: 'https://dmis.nhso.go.th/NAPPLUS/rrttr/createRRTTR.do?actionName=load',
    createRRBase: 'https://dmis.nhso.go.th/NAPPLUS/rrttr/createRRTTR.do',
    createVCT: 'https://dmis.nhso.go.th/NAPPLUS/vct/createVCT.do?actionName=load',
    createVCTBase: 'https://dmis.nhso.go.th/NAPPLUS/vct/createVCT.do',
    createHivLab: 'https://dmis.nhso.go.th/NAPPLUS/hivLabRequest/createHivLabRequest.do?actionName=load',
    createHivLabBase: 'https://dmis.nhso.go.th/NAPPLUS/hivLabRequest/createHivLabRequest.do',
};

// VCT: KP string → vct_target_group_status checkbox index
const VCT_KP_MAP = {
    'MSM': 0,
    'PWID': 1,
    'ANC': 2,
    'TGW': 3,
    'PWUD': 4,
    'คลอดจากแม่ติดเชื้อเอชไอวี': 5,
    'TGM': 6,
    'Partner of KP': 7,
    'บุคลากรทางการแพทย์': 8,
    'TGSW': 9,
    'Partner of PLHIV': 10,
    'nPEP': 11,
    'MSW': 12,
    'Prisoners': 13,
    'General Population': 14,
    'FSW': 15,
    'Migrant': 16,
    'สามี/คู่ของหญิงตั้งครรภ์': 17,
};
```

- [ ] **Step 2: Add `fillAndSubmitVCT` function after `fillAndSubmitRecord` (after line 448)**

```javascript
// ============================================================
// Step 2b: Fill and submit VCT form
// ============================================================

async function fillAndSubmitVCT(page, item, dryRun = false) {
    const serviceDate = item.service_date;
    const pid = item.id_card;
    const kp = item.kp;
    const uic = item.uic || '';
    const kpIndex = VCT_KP_MAP[kp];

    if (kpIndex === undefined) {
        throw new Error(`Unknown KP: ${kp} — ไม่พบใน VCT_KP_MAP`);
    }

    // Navigate to VCT create page
    await page.goto(NAP_URLS.createVCT, { waitUntil: 'domcontentloaded', timeout: 15000 });
    await page.waitForLoadState('networkidle').catch(() => {});

    if (page.url().includes('iam.nhso.go.th')) {
        throw new Error('Session expired — redirected to login');
    }

    // Fill search: date + PID
    await page.fill('input[name="vct_date"]', serviceDate);
    await page.fill('input[name="pid"]', pid);
    await page.click('input#cmdSearch');
    await page.waitForLoadState('networkidle').catch(() => {});
    await delay(1500);

    // Check if we're on the form page (URL should be createVCT.do without actionName)
    const currentUrl = page.url();
    if (currentUrl.includes('actionName=load')) {
        // Still on search page — check for error
        const errText = await page.evaluate(() => {
            const td = document.querySelector('table.alert td.text');
            return td ? td.textContent.trim() : null;
        });
        throw new Error(errText || 'ไม่สามารถเข้าฟอร์ม VCT ได้');
    }

    // Fill VCT form via DOM
    await page.evaluate((data) => {
        const clickCheckbox = (id) => {
            const el = document.getElementById(id);
            if (el) {
                el.checked = true;
                el.dispatchEvent(new Event('change', { bubbles: true }));
                el.dispatchEvent(new Event('click', { bubbles: true }));
            }
        };

        const clickRadio = (name, value) => {
            const el = document.querySelector(`input[name="${name}"][value="${value}"]`);
            if (el) el.click();
        };

        // 1. ช่องทางมารับบริการ: RRTTR (index 7)
        clickCheckbox('vct_receive_from_status_7');

        // 2. UIC (if from RRTTR)
        if (data.uic) {
            const uicInput = document.getElementById('rrtr_uic');
            if (uicInput) uicInput.value = data.uic;
        }

        // 3. ประเมินความเสี่ยง: มีพฤติกรรมเสี่ยง
        clickRadio('risk_flag', 'Y');

        // 4. กลุ่มผู้มารับบริการ (KP)
        clickCheckbox(`vct_target_group_status_${data.kpIndex}`);

        // 5. ปัจจัยเสี่ยง: มีเพศสัมพันธ์โดยไม่ป้องกัน (always)
        clickCheckbox('vct_risk_factor_status_0');
        // + ใช้เข็มฉีดยา if PWID
        if (data.kp === 'PWID') {
            clickCheckbox('vct_risk_factor_status_3');
        }

        // 6. Pre-test: ทำ
        clickRadio('pre_test_status', 'Y');

        // 7. Pre-test type: PICT (1)
        clickRadio('pre_test_type', '1');

        // 8. Pre-test method: รายบุคคล (2)
        clickRadio('pre_test_method', '2');

        // 9. Post-test: ทำ
        clickRadio('post_test_status', 'Y');

        // 10. Couple counseling: ไม่มีคู่ (3)
        clickRadio('post_test_couple_result_status', '3');

        // 11. STI: ไม่ส่งต่อ (2)
        clickRadio('post_test_sti', '2');

        // 12. เมทาโดน: ไม่ได้รับ (2)
        clickRadio('post_test_methadone', '2');

        // 13. ถุงยาง: รับ
        const condomY = document.querySelector('input[name="condom_receive_y"]');
        if (condomY) condomY.click();
    }, { kp, kpIndex, uic });

    // Fill pre_test_date and post_test_date (need Playwright fill for date inputs)
    await page.fill('#pre_test_date', serviceDate).catch(() => {});
    await page.fill('#post_test_date', serviceDate).catch(() => {});

    // Fill condom amounts
    await page.fill('#condom_amount_53', '10').catch(() => {});
    await page.fill('#lubricant_amount', '10').catch(() => {});

    if (dryRun) {
        await page.screenshot({ path: `automation/screenshots/dryrun_vct_${pid}.png`, fullPage: true });
        return { dryRun: true, vct_code: 'DRY_RUN' };
    }

    // Submit: click บันทึก
    await page.click('input#cmdPreview');
    await page.waitForLoadState('networkidle', { timeout: 30000 }).catch(() => {});
    await delay(2000);

    // Confirm: click ตกลง
    const confirmBtn = await page.$('input#cmdPreview');
    if (confirmBtn && await confirmBtn.isVisible()) {
        await confirmBtn.click();
        await page.waitForLoadState('networkidle', { timeout: 30000 }).catch(() => {});
        await delay(2000);
    }

    // Extract VCT ID
    const vctCode = await page.evaluate(() => {
        const tds = [...document.querySelectorAll('td')];
        const labelTd = tds.find(td => td.textContent.includes('VCT ID'));
        if (labelTd) {
            const nextTd = labelTd.nextElementSibling;
            return nextTd ? nextTd.textContent.trim() : null;
        }
        // Fallback: look for V-pattern code
        const text = document.body.innerText;
        const match = text.match(/V\d{2}-\d+-\d+/);
        return match ? match[0] : null;
    });

    return vctCode;
}
```

- [ ] **Step 3: Commit**

```bash
git add automation/thaid_login_and_record.cjs
git commit -m "feat: add fillAndSubmitVCT function with KP mapping and form defaults"
```

---

## Task 6: Playwright — Add HIV Lab Request function

**Files:**
- Modify: `automation/thaid_login_and_record.cjs`

- [ ] **Step 1: Add `fillAndSubmitLabRequest` function after `fillAndSubmitVCT`**

```javascript
// ============================================================
// Step 2c: Fill and submit HIV Lab Request
// ============================================================

async function fillAndSubmitLabRequest(page, item, dryRun = false) {
    const pid = item.id_card;
    const labDate = item.hiv_labreq_date || item.service_date;

    // Navigate to HIV Lab Request
    await page.goto(NAP_URLS.createHivLab, { waitUntil: 'domcontentloaded', timeout: 15000 });
    await page.waitForLoadState('networkidle').catch(() => {});

    if (page.url().includes('iam.nhso.go.th')) {
        throw new Error('Session expired — redirected to login (lab request)');
    }

    // Select ANTIHIV
    await page.selectOption('#hiv_lab_type', '1');
    await delay(500);

    // Fill PID + date
    await page.fill('input[name="pid"]', pid);
    await page.fill('input[name="hiv_labreq_date"]', labDate);

    // Click "เพิ่มข้อมูลการส่งตรวจ HIV"
    await page.click('input#cmdSearch');
    await page.waitForLoadState('networkidle').catch(() => {});
    await delay(1500);

    if (dryRun) {
        await page.screenshot({ path: `automation/screenshots/dryrun_lab_${pid}.png`, fullPage: true });
        return 'DRY_RUN_LAB';
    }

    // Click บันทึก
    await page.click('input#cmdPreview');
    await page.waitForLoadState('networkidle').catch(() => {});
    await delay(2000);

    // Check for "ใช้สิทธิเกิน 2 ครั้ง" confirmation
    const needsConfirm = await page.isVisible('input[name="confirmPid"]').catch(() => false);
    if (needsConfirm) {
        await page.fill('input[name="confirmPid"]', pid);
        await page.click('input[name="btnSubmit"]');
        await page.waitForLoadState('networkidle').catch(() => {});
        await delay(2000);
    }

    // Extract lab code
    const labCode = await page.evaluate(() => {
        const tds = [...document.querySelectorAll('td')];
        const labelTd = tds.find(td => td.textContent.includes('เลขที่ใบส่งตรวจทางห้องปฏิบัติการ'));
        if (labelTd) {
            const nextTd = labelTd.nextElementSibling;
            return nextTd ? nextTd.textContent.trim() : null;
        }
        // Fallback: look for ANTIHIV-pattern
        const text = document.body.innerText;
        const match = text.match(/ANTIHIV-[\d-]+/);
        return match ? match[0] : null;
    });

    return labCode;
}
```

- [ ] **Step 2: Commit**

```bash
git add automation/thaid_login_and_record.cjs
git commit -m "feat: add fillAndSubmitLabRequest function for HIV ANTIHIV request"
```

---

## Task 7: Playwright — Route main loop by `formType`

**Files:**
- Modify: `automation/thaid_login_and_record.cjs`

- [ ] **Step 1: Update the main `run()` function to read `formType` and route accordingly**

In the `run()` function, after `const { ablyKey, ablyChannel, items, callbackUrl, dryRun } = jobData;` (line 457), add `formType`:

```javascript
const { ablyKey, ablyChannel, items, callbackUrl, dryRun, formType } = jobData;
```

Then replace the record processing loop (the `for` loop starting at line 549) with:

```javascript
        // Process records
        for (let i = 0; i < (items || []).length; i++) {
            const item = items[i];
            const uic = item.uic || '';
            const pidMasked = 'xxxx' + (item.id_card || '').slice(-4);
            const isVCT = (formType || 'RR').toUpperCase() === 'VCT';
            const formLabel = isVCT ? 'VCT' : 'RR';

            await ably?.publish('job:record:processing', {
                jobId, index: i + 1, total, pidMasked, uic,
                message: `📄 กำลังบันทึก ${formLabel} (${i + 1}/${total}) | ${uic} | PID: ${pidMasked}`,
            }, 300);

            await ably?.publish('job:record:searching', {
                jobId, index: i + 1, total,
                message: `🔍 กำลังค้นหาข้อมูลบุคคล... (${i + 1}/${total})`,
            }, 800);

            try {
                await ably?.publish('job:record:filling', {
                    jobId, index: i + 1, total,
                    message: `✏️ กำลังกรอกข้อมูล ${formLabel}... (${i + 1}/${total})`,
                }, 500);

                if (!isDryRun) {
                    await ably?.publish('job:record:submitting', {
                        jobId, index: i + 1, total,
                        message: `💾 กำลังบันทึก ${formLabel}... (${i + 1}/${total})`,
                    }, 500);
                }

                if (isVCT) {
                    // === VCT Flow ===
                    const vctResult = await fillAndSubmitVCT(page, item, isDryRun);

                    let vctCode = null;
                    let labCode = null;

                    if (isDryRun && vctResult?.dryRun) {
                        vctCode = 'DRY_RUN';
                    } else {
                        vctCode = vctResult;
                    }

                    // Request Lab if needed
                    if (vctCode && !isDryRun && item.request_lab) {
                        try {
                            await ably?.publish('job:record:filling', {
                                jobId, index: i + 1, total,
                                message: `🔬 กำลังบันทึก Request Lab HIV... (${i + 1}/${total})`,
                            }, 500);
                            labCode = await fillAndSubmitLabRequest(page, item, false);
                            log(jobId, `  Record ${i + 1}: Lab = ${labCode}`);
                        } catch (labErr) {
                            log(jobId, `  Record ${i + 1}: Lab error = ${labErr.message}`);
                            // VCT succeeded but lab failed — still report VCT success
                        }
                    } else if (isDryRun && item.request_lab) {
                        labCode = await fillAndSubmitLabRequest(page, item, true);
                    }

                    if (vctCode) {
                        log(jobId, `  Record ${i + 1}: VCT=${vctCode} Lab=${labCode || 'N/A'}`);
                        results.push({
                            id_card: item.id_card, success: true,
                            nap_code: vctCode, nap_lab_code: labCode,
                            error: null,
                        });
                        await ably?.publish('job:record:success', {
                            jobId, index: i + 1, total, napCode: vctCode, labCode, uic,
                            message: `✅ VCT สำเร็จ (${i + 1}/${total}) | ${vctCode}${labCode ? ' | Lab: ' + labCode : ''}`,
                        }, 300);
                    } else {
                        results.push({
                            id_card: item.id_card, success: false,
                            nap_code: null, nap_lab_code: null,
                            error: 'ไม่พบรหัส VCT',
                        });
                        await ably?.publish('job:record:failed', {
                            jobId, index: i + 1, total, error: 'ไม่พบรหัส VCT', uic,
                            message: `❌ ล้มเหลว (${i + 1}/${total}) | ไม่พบรหัส VCT`,
                        }, 300);
                    }
                } else {
                    // === RR Flow (existing) ===
                    const rrForm = item.rr_form;

                    const rrCode = await fillAndSubmitRecord(page, rrForm, isDryRun);

                    if (isDryRun && rrCode?.dryRun) {
                        const report = rrCode.report;
                        log(jobId, `  Record ${i + 1}: DRY RUN — form filled`);

                        const lines = [
                            `📋 รายงาน DRY RUN (${i + 1}/${total}) | ${uic} | PID: ${pidMasked}`,
                            ``,
                            `พฤติกรรมเสี่ยง: ${report.risk_behaviors.map(r => r.name).join(', ') || '-'}`,
                            `กลุ่มเป้าหมาย: ${report.target_groups.map(r => r.name).join(', ') || '-'}`,
                            `ช่องทางเข้าถึง: ${report.access_type === '1' ? 'ใน DIC' : report.access_type === '2' ? 'นอก DIC' : report.access_type === '3' ? 'Social Media' : '-'}`,
                            `อาชีพ: ${report.occupation?.text || '-'} (${report.occupation?.value || '-'})`,
                            `แหล่งเงิน: ${report.pay_by?.text || '-'}`,
                            `ความรู้: ${report.knowledge.map(r => r.name).join(', ') || '-'}`,
                            `สถานที่: ${report.place.map(r => r.name).join(', ') || '-'}`,
                            `PPE: ${report.ppe.map(r => r.name).join(', ') || '-'}`,
                            `ถุงยาง: 49=${report.condom_49||0} 52=${report.condom_52||0} 53=${report.condom_53||0} 54=${report.condom_54||0} 56=${report.condom_56||0}`,
                            `ถุงยางหญิง: ${report.female_condom || 0} | สารหล่อลื่น: ${report.lubricant || 0}`,
                            `หน่วยบริการ: ${report.next_hcode || '-'}`,
                            `โทร: ${report.ref_tel || '-'}`,
                            `ส่งต่อ: HIV=${report.hiv_forward||'-'} STI=${report.sti_forward||'-'} TB=${report.tb_forward||'-'} HCV=${report.hcv_forward||'-'} Methadone=${report.methadone_forward||'-'}`,
                        ];

                        console.log('\n' + lines.join('\n') + '\n');

                        results.push({
                            id_card: item.id_card, uic, success: true,
                            nap_code: 'DRY_RUN', nap_lab_code: null,
                            error: null, report,
                        });

                        await ably?.publish('job:record:report', {
                            jobId, index: i + 1, total, uic, pidMasked,
                            report, message: lines.join('\n'),
                        }, 500);
                    } else if (rrCode) {
                        log(jobId, `  Record ${i + 1}: ${rrCode}`);
                        results.push({ id_card: item.id_card, success: true, nap_code: rrCode, nap_lab_code: null, error: null });
                        await ably?.publish('job:record:success', {
                            jobId, index: i + 1, total, napCode: rrCode, uic,
                            message: `✅ สำเร็จ (${i + 1}/${total}) | ${rrCode}`,
                        }, 300);
                    } else {
                        results.push({ id_card: item.id_card, success: false, nap_code: null, nap_lab_code: null, error: 'ไม่พบรหัส RR' });
                        await ably?.publish('job:record:failed', {
                            jobId, index: i + 1, total, error: 'ไม่พบรหัส RR', uic,
                            message: `❌ ล้มเหลว (${i + 1}/${total}) | ไม่พบรหัส RR`,
                        }, 300);
                    }
                }
            } catch (err) {
                log(jobId, `  Record ${i + 1} error: ${err.message}`);
                results.push({ id_card: item.id_card, success: false, nap_code: null, nap_lab_code: null, error: err.message });
                await ably?.publish('job:record:failed', {
                    jobId, index: i + 1, total, error: err.message, uic,
                    message: `❌ ล้มเหลว (${i + 1}/${total}) | ${err.message}`,
                }, 300);
            }
        }
```

- [ ] **Step 2: Also update the login navigation URL to use the correct form type**

In `loginViaThaiId`, the initial `page.goto` navigates to `NAP_URLS.createRR`. For VCT, it should navigate to `NAP_URLS.createVCT`. Update the `run()` function to pass `formType` into login, or change `loginViaThaiId` to accept a start URL:

Before the `loginViaThaiId` call (line 492), update to:

```javascript
        const startUrl = isVCT ? NAP_URLS.createVCT : NAP_URLS.createRR;
        const loginOk = await loginViaThaiId(loginPage, ably, jobId, startUrl);
```

And update the `loginViaThaiId` function signature (line 88) to accept startUrl:

```javascript
async function loginViaThaiId(page, ably, jobId, startUrl = NAP_URLS.createRR) {
    log(jobId, 'Navigating to NAP Plus (will redirect to SSO)...');
    await ably?.publish('job:start', {
        jobId,
        message: '🔐 เริ่มระบบ AutoNAP — กำลังเปิดหน้า Login',
    }, 500);

    await page.goto(startUrl, { waitUntil: 'networkidle', timeout: 30000 });
```

Also add `const isVCT = (formType || 'RR').toUpperCase() === 'VCT';` near the top of `run()`, after reading `formType` from jobData.

- [ ] **Step 3: Commit**

```bash
git add automation/thaid_login_and_record.cjs
git commit -m "feat: route Playwright main loop by formType (VCT/RR) with lab request support"
```

---

## Task 8: Integration Test — Send VCT job to test-callback

**Files:** None (manual test via curl)

- [ ] **Step 1: Start the queue worker**

```bash
php artisan queue:work --once
```

- [ ] **Step 2: Send a VCT dry-run job**

```bash
curl -X POST http://localhost:8000/api/jobs \
  -H "Content-Type: application/json" \
  -d '{
    "site": "test",
    "fy": "2026",
    "form_type": "VCT",
    "dry_run": true,
    "callback_url": "http://localhost:8000/api/test-callback",
    "items": [
      {
        "source_id": "99999",
        "source": "clinic",
        "id_card": "1234567890123",
        "kp": "MSM",
        "cbs": "ทดสอบ ระบบ",
        "uic": "ทด001",
        "service_date": "03/04/2569",
        "request_lab": true,
        "row_id": "VCT_clinic_99999_1234567890123"
      }
    ]
  }'
```

Expected response:
```json
{
  "status": "ok",
  "job_id": "autonap-...",
  "method": "ThaiID",
  "form_type": "VCT",
  "total": 1
}
```

- [ ] **Step 3: Check logs for callback and Playwright output**

```bash
tail -f storage/logs/laravel.log | grep -E "AutoNAP|ThaiID|callback"
```

- [ ] **Step 4: Commit all changes**

```bash
git add -A
git commit -m "feat: complete AutoNAP VCT implementation with lab request support"
```
