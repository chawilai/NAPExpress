# AutoNAP — Project Structure & Flow Documentation

AutoNAP คือระบบบันทึกข้อมูล NAP Plus อัตโนมัติ ผ่าน Playwright browser automation
รองรับการบันทึกแบบฟอร์ม **RR (RRTTR)** และ **VCT** พร้อมส่งผลกลับ CAREMAT แบบ real-time

---

## สารบัญ

1. [ภาพรวมสถาปัตยกรรม](#1-ภาพรวมสถาปัตยกรรม)
2. [Flow การทำงานทั้งหมด](#2-flow-การทำงานทั้งหมด)
3. [ขั้นตอนที่ 1 — รับ Request](#3-ขั้นตอนที่-1--รับ-request)
4. [ขั้นตอนที่ 2 — Queue Job](#4-ขั้นตอนที่-2--queue-job)
5. [ขั้นตอนที่ 3 — Playwright Login (Headed Browser)](#5-ขั้นตอนที่-3--playwright-login-headed-browser)
6. [ขั้นตอนที่ 4 — บันทึกข้อมูล (Headless Browser)](#6-ขั้นตอนที่-4--บันทึกข้อมูล-headless-browser)
7. [ขั้นตอนที่ 5 — Callback & Report](#7-ขั้นตอนที่-5--callback--report)
8. [โครงสร้างไฟล์](#8-โครงสร้างไฟล์)
9. [Playwright Script — รายละเอียด](#9-playwright-script--รายละเอียด)
10. [Ably Real-time Events](#10-ably-real-time-events)
10.1. [Dashboard — หน้าติดตามงาน](#101-dashboard--หน้าติดตามงาน)
11. [การ Deploy](#11-การ-deploy)
12. [Timeout & Retry Configuration](#12-timeout--retry-configuration)
13. [Troubleshooting](#13-troubleshooting)

---

## 1. ภาพรวมสถาปัตยกรรม

```
CAREMAT (PHP)                    NAPExpress (Laravel)                 NAP Plus (สปสช.)
─────────────                    ────────────────────                 ────────────────
                POST /api/jobs
nhsoForReach.php ──────────────► AutoNapJobController
                                       │
                                       ▼
                                 Queue (database)
                                       │
                                       ▼
                                 ProcessAutoNapJob
                                       │
                                       ▼
                                 Playwright (Node.js)
                                 ┌──────────────────┐
                                 │ Phase 1: HEADED   │──► ThaiD QR Login
                                 │ (ต้อง Xvfb)      │    (iam.nhso.go.th)
                                 └────────┬─────────┘
                                          │ cookies
                                 ┌────────▼─────────┐
                                 │ Phase 2: HEADLESS │──► กรอก form + submit
                                 │ (ไม่ต้อง display) │    (dmis.nhso.go.th)
                                 └────────┬─────────┘
                                          │
                          ┌───────────────┼───────────────┐
                          ▼               ▼               ▼
                    Ably (real-time)   Callback        Email Report
                    CAREMAT UI        CAREMAT API      Admin inbox
```

### ทำไมต้องใช้ 2 Browser?

| Browser | Mode | ทำไม |
|---------|------|------|
| **Headed** (Phase 1) | headless=false | GDCC (ไฟร์วอลล์ สปสช.) บล็อก headless บน `iam.nhso.go.th` ต้องใช้ headed + Xvfb |
| **Headless** (Phase 2) | headless=true | `dmis.nhso.go.th` ไม่บล็อก headless — ใช้ headless เร็วกว่า + ไม่ต้อง display |

### Cookie Transfer

```
Headed Browser                     Headless Browser
    │                                   │
    │ loginContext.cookies()            │
    │ ──────────────────► cookies[]     │
    │                       │           │
    │                       │  addCookies(cookies)
    │                       └──────────►│
    │                                   │ ← ใช้ session เดียวกัน
    ▼ close()                           ▼ ← ไม่ต้อง login ใหม่
```

---

## 2. Flow การทำงานทั้งหมด

```
CAREMAT ──POST /api/jobs──► Controller ──validate──► ตรวจ lock ──► dispatch job
                                                         │
                                                    ถ้ามี job ซ้ำ
                                                    → 429 reject
                                                         │
Queue Worker หยิบ job                                    ▼
         │                                         Cache::put lock
         ▼
ProcessAutoNapJob::handle()
         │
         ├── ThaiID ──► สร้าง data file ──► node thaid_login_and_record.cjs
         │                                        │
         │              ┌─────────────────────────┘
         │              │
         │         Phase 1: Login (headed)
         │              │── navigate ไป NAP → redirect SSO → ThaiD
         │              │── จับ QR code → ส่ง Ably → user สแกน
         │              │── รอ redirect กลับ NAP (60 วินาที)
         │              │── extract cookies → ปิด headed browser
         │              │
         │         Phase 2: Record (headless)
         │              │── inject cookies → เปิด form page
         │              │── extract NAP display name (สำหรับ email)
         │              │── วน loop ทีละ record:
         │              │     ├── RR: กรอก form → submit → ได้ RR code
         │              │     └── VCT: กรอก VCT → submit → ได้ VCT code
         │              │              ├── Lab Request → ได้ ANTIHIV code
         │              │              └── HIV Result → บันทึกผล Negative/Positive
         │              │── เขียน results JSON
         │              │
         │              ▼
         │    PHP อ่าน results
         │         │── RR: ส่ง callback ทีละ record
         │         │── VCT: callback ส่งจาก Playwright แล้ว (ข้าม)
         │         │── ส่ง email report
         │         └── ปลด lock (Cache::forget)
         │
         └── DirectHTTP ──► NapDirectHttpService (PHP login + HTTP POST)
```

---

## 3. ขั้นตอนที่ 1 — รับ Request

**Endpoint:** `POST /api/jobs`
**File:** `app/Http/Controllers/Api/AutoNapJobController.php`

### Request Format

```json
{
  "site": "rsat_pte",
  "fy": "2568",
  "form_type": "VCT",
  "method": "ThaiID",
  "staff_name": "สมชาย ใจดี",
  "callback_url": "https://rsat-pte.actse-clinic.com/autonap_callback.php",
  "ably_channel": "job-notification-channel",
  "dry_run": false,
  "items": [
    {
      "source_id": "Clinic_testing_181620_1959900957776",
      "source": "walk in",
      "id_card": "1959900957776",
      "uic": "สช151048",
      "service_date": "2026-04-04",
      "kp": "MSM",
      "cbs": "นายทดสอบ",
      "request_lab": true,
      "hiv_labreq_date": "2026-04-04",
      "test_result": true,
      "hiv_result": "Negative"
    }
  ]
}
```

### Validation Rules

| Field | Required | หมายเหตุ |
|-------|----------|---------|
| `site` | Yes | รหัสสถานบริการ |
| `fy` | Yes | ปีงบประมาณ |
| `form_type` | No | `RR` (default) หรือ `VCT` |
| `method` | No | `ThaiID` (default) หรือ `DirectHTTP` |
| `staff_name` | No | ชื่อเจ้าหน้าที่ผู้ login CAREMAT — ใช้เป็น `nap_staff` ใน callback |
| `callback_url` | Yes | URL สำหรับส่งผลกลับ CAREMAT |
| `ably_channel` | No | Channel สำหรับ real-time progress |
| `items` | Yes | array ขั้นต่ำ 1 รายการ |

**VCT items เพิ่มเติม:** `service_date`, `kp`, `cbs`, `id_card` (13 หลัก)
**RR items เพิ่มเติม:** `rr_form.pid`, `rr_form.rrttrDate`

### Concurrency Control (ป้องกัน Job ซ้ำ)

```php
$lockKey = "autonap:{$site}:{$formType}";  // เช่น "autonap:rsat_pte:VCT"

if (Cache::has($lockKey)) {
    return 429;  // "มี job กำลังทำงานอยู่"
}

Cache::put($lockKey, $jobId, 3600);  // lock 1 ชั่วโมง
```

- ป้องกันไม่ให้ site เดียวกัน + form_type เดียวกัน ส่ง job ซ้อน
- Lock ถูกปลดเมื่อ: job สำเร็จ, job fail, หรือหมดอายุ 1 ชม.

### Response

```json
{
  "status": "ok",
  "job_id": "autonap-ff9ff144275ef7fe",
  "form_type": "VCT",
  "method": "ThaiID",
  "total": 15,
  "queued": true,
  "estimated_wait_minutes": 12,
  "message": "Job dispatched — 15 items queued for VCT (ThaiID)",
  "ably_channel": "job-notification-channel"
}
```

| Field | คำอธิบาย |
|-------|---------|
| `queued` | `true` = มี job อื่นรออยู่ข้างหน้า, `false` = ทำงานได้ทันที |
| `estimated_wait_minutes` | เวลาโดยประมาณที่ต้องรอ (นาที) คำนวณจากจำนวน records ในคิว |

### Request Logging

ทุก request ถูกบันทึกเป็น JSON ที่:
```
storage/app/private/autonap_logs/2026-04-04/autonap-ff9ff144275ef7fe.json
```

---

## 4. ขั้นตอนที่ 2 — Queue Job

**File:** `app/Jobs/ProcessAutoNapJob.php`

### Properties

```php
public int $tries = 1;     // ไม่ retry — fail ก็ fail เลย
public int $timeout = 3600; // 1 ชั่วโมง max
```

### Data File สำหรับ Playwright

PHP สร้างไฟล์ JSON ส่งให้ Node.js:

```
storage/app/private/thaid_{jobId}.json
```

เนื้อหา:
```json
{
  "ablyKey": "xxx",
  "ablyChannel": "channel-name",
  "items": [...],
  "dryRun": false,
  "formType": "VCT",
  "callbackUrl": "https://...",
  "fy": "2568"
}
```

### เรียก Playwright

```php
$process = new Process([
    'node',
    base_path('automation/thaid_login_and_record.cjs'),
    '--jobId=' . $this->jobId,
    '--dataFile=' . $dataFile,
]);
$process->setTimeout($this->timeout);
$process->run();
```

### อ่านผลลัพธ์

Playwright เขียน results ที่:
```
storage/app/private/thaid_{jobId}_results.json
```

```json
{
  "napDisplayName": "ชื่อผู้ใช้ NAP",
  "results": [
    {
      "id_card": "1959900957776",
      "success": true,
      "nap_code": "V69-41692-13918089",
      "nap_lab_code": "ANTIHIV-41692-6904-20215529",
      "uic": "สช151048",
      "error": null
    }
  ]
}
```

---

## 5. ขั้นตอนที่ 3 — Playwright Login (Headed Browser)

**File:** `automation/thaid_login_and_record.cjs` → function `loginViaThaiId()`

### Flow

```
1. เปิด Chromium (headed, ต้องมี Xvfb บน server)
   └─ args: --no-sandbox, --disable-setuid-sandbox
   └─ viewport: 1280x900, locale: th-TH

2. Navigate ไป NAP form URL
   └─ RR: dmis.nhso.go.th/NAPPLUS/rrttr/createRRTTR.do?actionName=load
   └─ VCT: dmis.nhso.go.th/NAPPLUS/vct/createVCT.do?actionName=load

3. NAP redirect ไป Keycloak SSO (iam.nhso.go.th)
   └─ ถ้า URL เป็น dmis.nhso.go.th → login อยู่แล้ว → ข้าม

4. คลิกปุ่ม ThaiD (#social-thaid)
   └─ redirect ไป imauth.bora.dopa.go.th

5. จับ QR Code
   └─ หา <img src="data:image/..."> → ได้ base64
   └─ หา "หมายเลขอ้างอิง" → ได้ refCode (เช่น "D62JWV")

6. ส่ง QR ผ่าน Ably → CAREMAT แสดง QR ให้ user สแกน
   └─ event: job:thaid:qr
   └─ payload: { qrImage: "data:image/png;base64,...", refCode: "D62JWV" }

7. รอ user สแกน ThaiD (timeout 60 วินาที)
   └─ waitForURL: dmis.nhso.go.th หรือ NAPPLUS
   └─ สำเร็จ → login success
   └─ timeout → login failed → ทุก record ถูก mark failed

8. Extract cookies จาก browser context
   └─ loginContext.cookies() → ได้ ~10 cookies
   └─ บันทึกไฟล์: thaid_{jobId}_cookies.json

9. ปิด headed browser
```

### สิ่งสำคัญ

- **ห้าม navigate ไปหน้าอื่น** ก่อนดึง cookies — จะทำให้ session เสีย
- QR code มีอายุสั้น — ถ้า user ไม่สแกนภายใน 60 วินาที job จะ fail
- Headed browser ต้องมี virtual display (Xvfb) บน server

---

## 6. ขั้นตอนที่ 4 — บันทึกข้อมูล (Headless Browser)

### เปิด Headless Browser

```javascript
const workBrowser = await chromium.launch({ headless: true });
const workContext = await workBrowser.newContext({ ... });
await workContext.addCookies(cookies);  // inject session จาก login
```

### Extract NAP Display Name

หลัง load หน้า form ครั้งแรก → ดึงชื่อผู้ใช้จาก header:
```javascript
// ลอง selectors: #header_user_name, .user-name, .username, ...
// Fallback: scan header area หาข้อความภาษาไทย
```

ค่าที่ได้จะถูกส่งกลับใน results JSON → ใช้ใน email report

### SSO Redirect ระหว่างทำงาน

NAP Plus อาจ redirect ไป `iam.nhso.go.th` เพื่อ refresh OAuth token:
```javascript
if (page.url().includes('iam.nhso.go.th')) {
    // รอ auto-redirect กลับ (ไม่ต้อง login ใหม่)
    await page.waitForURL(url => url.includes('dmis.nhso.go.th'), {
        timeout: 30000  // 30 วินาที
    });
}
```

### Browser Crash Recovery

ถ้า browser crash ระหว่างทำงาน:
1. ตรวจจับว่า browser หยุดทำงาน (เช่น process ถูก kill, memory เกิน)
2. เปิด browser ใหม่อัตโนมัติ + inject cookies เดิม
3. retry record ที่ค้าง (สูงสุด **2 ครั้ง/record**)
4. ถ้า retry 2 ครั้งแล้วยัง crash → mark error แล้วข้ามไป record ถัดไป

### Batch Browser Restart

เพื่อป้องกัน memory leak ทุก **25 records** ระบบจะปิด browser แล้วเปิดใหม่:
```
record 1-25  → browser instance #1
record 26-50 → browser instance #2 (restart)
...
```
ลด memory footprint โดยเฉพาะ job ที่มีหลายสิบ records

---

### 6.1 บันทึก RR (RRTTR)

**Function:** `fillAndSubmitRecord(page, rrForm, dryRun)`

```
1. Navigate ไป createRRTTR.do?actionName=load
2. กรอกค้นหา: วันที่ (rrttrDate) + เลขบัตร (pid)
3. คลิก "เพิ่มข้อมูลให้บริการ"
4. ตรวจหน้ายืนยันสิทธิ → คลิก "ตกลง"
5. กรอก form ผ่าน page.evaluate() (DOM manipulation):
   ├─ พฤติกรรมเสี่ยง: check checkboxes ตาม risk_behavior_indices
   ├─ กลุ่มเป้าหมาย: check ตาม target_group_indices
   ├─ ช่องทาง: set radio (access_type)
   ├─ สิทธิการรักษา: select (pay_by)
   ├─ อาชีพ: select (occupation)
   ├─ ความรู้: check ทั้ง 5 ข้อ (hardcoded)
   ├─ ถุงยาง: ใส่จำนวนตาม size (49,52,53,54,56)
   ├─ สารหล่อลื่น: ใส่จำนวน
   └─ การส่งต่อ: check forwards (HIV, STI, TB)
6. คลิก "บันทึก" → ยืนยัน → ดึง RR code จากหน้าผลลัพธ์
   └─ regex: /R\d{2}-\d+-\d+/ (เช่น R67-41692-12345678)
```

### 6.2 บันทึก VCT (3 ขั้นตอน)

**ขั้นตอนที่ 1 — VCT Form**
**Function:** `fillAndSubmitVCT(page, item, dryRun)`

```
1. Navigate ไป createVCT.do?actionName=load
2. กรอกค้นหา: วันที่ (service_date) + เลขบัตร (id_card)
3. คลิกค้นหา → ตรวจ error (ซ้ำ, ไม่พบ)
4. กรอก form ผ่าน page.evaluate():
   ├─ ช่องทาง: check "RRTTR" (index 7)
   ├─ หน่วยงาน/แผนกที่ส่งต่อ: walk in → "คลินิกภายในรพ" (index 0), reach/mobile → "ออกพื้นที่" (index 1)
   ├─ UIC: set #rrtr_uic
   ├─ ประเมินความเสี่ยง: "มีพฤติกรรมเสี่ยง" (Y)
   ├─ กลุ่มผู้รับบริการ: check ตาม KP (VCT_KP_MAP)
   ├─ ปัจจัยเสี่ยง: check "มีเพศสัมพันธ์โดยไม่ป้องกัน"
   │   └─ ถ้า PWID → เพิ่ม "ใช้ยาเสพติด"
   ├─ Pre-test: ทำ (Y), CITC (ผู้รับบริการแสดงความต้องการตรวจด้วยตนเอง), รายบุคคล
   ├─ Post-test: ทำ (Y), มีคู่ แต่คู่ไม่ได้ตรวจ
   ├─ STI: มีผลตรวจ → ส่งต่อ + ผลตรวจ (syphilis, CT, NG) / ไม่มีผล → ส่งต่อ + ไม่ได้ตรวจ
   ├─ เมทาโดน: ไม่ได้รับ
   └─ ถุงยาง: รับ + จำนวนตาม size
5. คลิก "บันทึก" → ยืนยัน → ดึง VCT code
   └─ regex: /V\d{2}-\d+-\d+/ (เช่น V69-41692-13918089)
6. ส่ง VCT callback ไป CAREMAT
```

**ขั้นตอนที่ 2 — Lab Request** (ถ้า `request_lab = true`)
**Function:** `fillAndSubmitLabRequest(page, item, dryRun)`

```
1. Navigate ไป createHivLabRequest.do?actionName=load
2. เลือกประเภท: ANTIHIV (#hiv_lab_type = '1')
3. กรอก: เลขบัตร + วันที่ส่งตรวจ (hiv_labreq_date)
4. คลิกค้นหา → บันทึก
5. ถ้ามี confirm "ใช้สิทธิเกิน 2 ครั้ง" → กรอก PID ยืนยัน
6. ดึง Lab code
   └─ regex: /ANTIHIV-[\d-]+/ (เช่น ANTIHIV-41692-6904-20215529)
7. ส่ง Lab callback ไป CAREMAT
```

**ขั้นตอนที่ 3 — HIV Test Result** (ถ้า `test_result = true`)
**Function:** `fillAndSubmitHivResult(page, context, cookies, labCode, testDate, hivResult, dryRun)`

```
1. Navigate ไป searchResponseLabRequest.do
2. ค้นหาด้วย Lab code + ประเภท ANTIHIV
3. กรอกผล:
   ├─ วันที่ตรวจ: #lab_test_date_0
   ├─ สถานะ: "ตรวจได้" (#lab_status_0 = '1')
   ├─ ผลตรวจ: Negative(2) / Positive(1) / Inconclusive(3)
   └─ #change_result_0 = 'Y' (flag ว่ามีการเปลี่ยนแปลง)
4. เรียก doConfirm() → บันทึก
5. ส่ง Result callback ไป CAREMAT
```

### VCT_KP_MAP — แปลง Key Population เป็น Index

```javascript
{
  'MSM': 0, 'PWID': 1, 'ANC': 2, 'TGW': 3, 'PWUD': 4,
  'TGM': 6, 'Partner of KP': 7, 'TGSW': 9,
  'Partner of PLHIV': 10, 'nPEP': 11, 'MSW': 12,
  'Prisoners': 13, 'General Population': 14,
  'FSW': 15, 'Migrant': 16,
  // CAREMAT aliases
  'Female': 14, 'Male': 14, 'TG': 3,
}
```

---

## 7. ขั้นตอนที่ 5 — Callback & Report

### Callback Structure

**File:** `app/Services/NapCallbackService.php`

| Form Type | จำนวน Callback | ส่งจากไหน |
|-----------|----------------|-----------|
| **RR** | 1 ครั้ง/record | PHP (ProcessAutoNapJob) |
| **VCT** | 3 ครั้ง/record | Playwright (thaid_login_and_record.cjs) |

**VCT 3-Step Callbacks:**

```
Step 1: VCT Code
  { form_type: 'VCT', nap_vct_code: 'V69-...', step: 'VCT' }

Step 2: Lab Code
  { form_type: 'VCT', nap_lab_code: 'ANTIHIV-...', step: 'LAB' }

Step 3: Test Result
  { form_type: 'VCT', hiv_result: 'Negative', step: 'RESULT' }
```

**RR Callback:**
```json
{
  "form_type": "RR",
  "source_id": "...",
  "id_card": "...",
  "nap_code": "R67-41692-12345678",
  "nap_status": "true",
  "nap_staff": "สมชาย ใจดี",
  "nap_comment": "AutoNAP",
  "next_hcode": "41693",
  "next_hname": "รพ.สต.บ้านนา",
  "status": "success"
}
```

> **`nap_staff`** มาจาก `staff_name` ที่ CAREMAT ส่งมาใน request (ชื่อผู้ login CAREMAT)
> **`next_hcode` / `next_hname`** ใช้สำหรับ RR callback เพื่อให้ CAREMAT ทราบว่า record ถัดไปเป็นของ site ไหน

### Email Report

**File:** `app/Mail/AutoNapJobReport.php` + `resources/views/emails/autonap-report.blade.php`

ส่งอัตโนมัติเมื่อ job จบ ไปที่ `MAIL_TO_ADDRESS` ใน `.env`

เนื้อหา:
- Header: AutoNAP Report + form type + site
- Status: สำเร็จทั้งหมด / บางส่วน / ล้มเหลว
- Summary Cards: Total, Success, Failed
- Job Details: Job ID, Site, NAP Username, เวลาเริ่ม/จบ, เฉลี่ย/record
- Results Table: ทุก record พร้อม UIC, PID (mask), Status, NAP Code/Error

---

## 8. โครงสร้างไฟล์

```
NAPExpress/
├── app/
│   ├── Http/Controllers/Api/
│   │   └── AutoNapJobController.php     ← รับ request, validate, dispatch job
│   ├── Jobs/
│   │   └── ProcessAutoNapJob.php         ← queue job, เรียก Playwright, อ่าน results
│   ├── Mail/
│   │   └── AutoNapJobReport.php          ← email report mailable
│   └── Services/
│       ├── AblyProgressService.php       ← real-time progress ผ่าน Ably
│       ├── NapCallbackService.php        ← ส่งผลกลับ CAREMAT (RR)
│       └── NapDirectHttpService.php      ← DirectHTTP flow (legacy)
│
├── automation/
│   └── thaid_login_and_record.cjs        ← Playwright script (login + form filling)
│
├── config/
│   ├── mail.php                          ← SMTP + MAIL_TO_ADDRESS
│   └── queue.php                         ← retry_after: 3900
│
├── resources/views/emails/
│   └── autonap-report.blade.php          ← email template (HTML)
│
├── routes/
│   ├── api.php                           ← POST /api/jobs, GET /api/dashboard
│   └── web.php                           ← GET /autonap (Dashboard page)
│
├── database/
│   └── migrations/
│       ├── create_autonap_requests_table  ← job metadata (job_id, site, form_type, total, status)
│       └── create_autonap_records_table   ← record-level data (source_id, nap_code, status, error)
│
└── storage/app/private/
    ├── autonap_logs/{date}/{jobId}.json  ← request log (audit)
    ├── thaid_{jobId}.json                ← data file สำหรับ Playwright
    ├── thaid_{jobId}_cookies.json        ← session cookies
    └── thaid_{jobId}_results.json        ← ผลลัพธ์จาก Playwright
```

---

## 9. Playwright Script — รายละเอียด

**File:** `automation/thaid_login_and_record.cjs`

### NAP Plus URLs

```javascript
const NAP_URLS = {
  createRR:      'https://dmis.nhso.go.th/NAPPLUS/rrttr/createRRTTR.do?actionName=load',
  createVCT:     'https://dmis.nhso.go.th/NAPPLUS/vct/createVCT.do?actionName=load',
  createHivLab:  'https://dmis.nhso.go.th/NAPPLUS/hivLabRequest/createHivLabRequest.do?actionName=load',
};
```

### Functions

| Function | หน้าที่ | Return |
|----------|--------|--------|
| `loginViaThaiId()` | Login ผ่าน ThaiD QR | `true/false` |
| `fillAndSubmitRecord()` | กรอก + submit RR form | RR code string |
| `fillAndSubmitVCT()` | กรอก + submit VCT form | VCT code string |
| `fillAndSubmitLabRequest()` | ส่งตรวจ HIV Lab | ANTIHIV code string |
| `fillAndSubmitHivResult()` | บันทึกผล HIV | result string |
| `writeResults()` | เขียน results JSON | void |
| `run()` | main orchestrator | void |

### DOM Manipulation Pattern

NAP Plus ใช้ form HTML แบบเก่า — Playwright ใช้ `page.evaluate()` กับ DOM โดยตรง:

```javascript
await page.evaluate((rrForm) => {
    // Check checkbox
    const cb = document.getElementById('rrttr_risk_behavior_status_0');
    cb.checked = true;
    cb.dispatchEvent(new Event('change', { bubbles: true }));
    cb.dispatchEvent(new Event('click', { bubbles: true }));

    // Set select
    document.getElementById('pay_by').value = '1';

    // Set radio
    document.getElementById('access_type_1').checked = true;
}, rrForm);
```

ต้อง dispatch event เพราะ NAP Plus มี JavaScript listener ที่ต้องการ event

### Error Handling ใน Playwright

```javascript
try {
    const vctCode = await fillAndSubmitVCT(page, item, isDryRun);
    results.push({ id_card: item.id_card, success: true, nap_code: vctCode });
} catch (error) {
    results.push({ id_card: item.id_card, success: false, error: error.message });
    // ส่ง error callback ไป CAREMAT
    await sendCallback(callbackUrl, buildErrorCallback(item, error.message));
}
// → ทำ record ถัดไป (ไม่หยุดทั้ง job)
```

---

## 10. Ably Real-time Events

**File:** `app/Services/AblyProgressService.php` (PHP) + inline ใน `thaid_login_and_record.cjs` (Node.js)

| Event | เมื่อไหร่ | ส่งจาก |
|-------|----------|--------|
| `job:start` | เริ่ม job | Playwright |
| `job:connecting` | กำลังเชื่อมต่อ SSO | Playwright |
| `job:thaid:qr` | QR code พร้อมให้สแกน (มี base64 image) | Playwright |
| `job:thaid:waiting` | รอ user สแกน | Playwright |
| `job:login:success` | Login สำเร็จ | Playwright |
| `job:login:failed` | Login timeout | Playwright |
| `job:preparing` | กำลังเตรียมข้อมูล | Playwright |
| `job:record:processing` | เริ่ม record N/total | Playwright |
| `job:record:searching` | ค้นหาข้อมูลบุคคล | Playwright |
| `job:record:filling` | กรอก form | Playwright |
| `job:record:submitting` | กำลังบันทึก | Playwright |
| `job:record:success` | record สำเร็จ + NAP code | Playwright |
| `job:record:failed` | record ล้มเหลว + error | Playwright |
| `job:summarizing` | กำลังสรุปผล | Playwright |
| `job:complete` | จบ job + สถิติ | Playwright |
| `job:failed` | job fail ถาวร (exception) | PHP |

---

## 10.1 Dashboard — หน้าติดตามงาน

### Endpoints

| Method | URL | คำอธิบาย |
|--------|-----|---------|
| GET | `/autonap` | หน้า Dashboard (Inertia page) |
| GET | `/api/dashboard` | API สำหรับดึงข้อมูล dashboard |

### ความสามารถ

- แสดง job ทั้งหมดพร้อมสถานะ (รอคิว, กำลังทำ, สำเร็จ, ล้มเหลว)
- ติดตามความคืบหน้าแบบ real-time ผ่าน Ably
- ดูรายละเอียด record แต่ละรายการ (NAP code, error)

### Database Tables

| Table | คำอธิบาย |
|-------|---------|
| `autonap_requests` | บันทึก request ที่เข้ามา (job_id, site, form_type, total, status, timestamps) |
| `autonap_records` | บันทึก record แต่ละรายการ (source_id, id_card, nap_code, status, error, timestamps) |

### Login Timeout — ไม่มี record

ถ้า ThaiD login ไม่สำเร็จ (หมดเวลา 60 วินาที):
- ไม่ส่ง email report
- ไม่สร้าง record ใน `autonap_records`
- CAREMAT ได้รับเฉพาะ Ably event `job:login:failed`

---

## 11. การ Deploy

### VPS Information

| Item | Value |
|------|-------|
| SSH | `sshinspace` (alias ในเครื่อง local) |
| Path | `/var/www/actse-clinic.com/autonap` |
| Domain | `https://autonap.actse-clinic.com` |
| OS | Ubuntu, 4 cores, 16 GB RAM, Swap 8 GB (swappiness=10) |
| Runtime | PHP 8.4, Node.js, Playwright, Xvfb |

### Deploy Steps

```bash
# 1. ตรวจสอบ workers ก่อน deploy — ดูว่ามี job กำลังทำงานอยู่ไหม
sshinspace "sudo supervisorctl status autonap-worker:*"

# 2. Push code
git push origin main

# 3. Pull + restart workers
sshinspace "cd /var/www/actse-clinic.com/autonap && git pull origin main && sudo supervisorctl restart autonap-worker:*"
```

> **สำคัญ:** ควรตรวจสอบว่าไม่มี job กำลังทำงานอยู่ก่อน deploy เพราะ restart worker จะทำให้ job ที่กำลังทำค้างอยู่ fail

### Supervisor Config

ที่ `/etc/supervisor/conf.d/autonap-worker.conf`:

```ini
[program:autonap-worker]
process_name=%(program_name)s_%(process_num)02d
command=php8.4 /var/www/actse-clinic.com/autonap/artisan queue:work --timeout=600 --tries=3
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/actse-clinic.com/autonap/storage/logs/worker.log
stopwaitsecs=600
environment=DISPLAY=":99"
```

> **หมายเหตุ:** Supervisor ใช้ `--tries=3 --timeout=600` แต่ Job class มี `$tries=1` ซึ่ง override `--tries` ของ worker
> ค่า `--timeout=600` (10 นาที) เพียงพอสำหรับ ~80 records แต่ถ้ามี 250+ records อาจต้องเพิ่ม
> Workers = 4 ตัว (numprocs=4) — รันงาน 4 site พร้อมกันได้

### Supervisor Commands

```bash
# ดูสถานะ
sshinspace "sudo supervisorctl status autonap-worker:*"

# Restart ทุกตัว
sshinspace "sudo supervisorctl restart autonap-worker:*"

# ดู log
sshinspace "tail -100 /var/www/actse-clinic.com/autonap/storage/logs/worker.log"

# ดู Laravel log
sshinspace "tail -100 /var/www/actse-clinic.com/autonap/storage/logs/laravel.log"
```

### Environment Variables (VPS .env)

```ini
# Queue
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=3900

# Mail
MAIL_MAILER=smtp
MAIL_HOST=smtp.zimbra.in.th
MAIL_PORT=465
MAIL_SCHEME=smtps
MAIL_USERNAME="report@actse.co.th"
MAIL_PASSWORD="..."
MAIL_FROM_ADDRESS="report@actse.co.th"
MAIL_FROM_NAME="AutoNAP Report"
MAIL_TO_ADDRESS="wat.chawilai@gmail.com"
```

---

## 12. Timeout & Retry Configuration

| Component | Timeout | ที่ตั้ง |
|-----------|---------|--------|
| Job execution | 3600s (1 ชม.) | `ProcessAutoNapJob::$timeout` |
| Queue retry_after | 3900s (65 นาที) | `config/queue.php` |
| Supervisor worker | 600s (10 นาที) | `/etc/supervisor/conf.d/autonap-worker.conf` |
| ThaiD scan wait | 60s | `thaid_login_and_record.cjs:263` |
| SSO auto-redirect | 30s | `thaid_login_and_record.cjs` (3 จุด) |
| Playwright page ops | 20s | `workContext.setDefaultTimeout(20000)` |
| Callback HTTP | 15s | `NapCallbackService::send()` |
| Batch callback | 30s | `NapCallbackService::sendBatch()` |

### ทำไม retry_after (3900) > timeout (3600)?

ถ้า `retry_after < timeout` → queue จะคิดว่า job ค้าง → ปล่อย job กลับ → worker อีกตัวหยิบไปทำซ้อน → เกิด 2 process ทำงานพร้อมกัน → retry ส่ง error event ทับ process จริง

---

## 13. Troubleshooting

### ปัญหาที่เคยเจอ

| ปัญหา | สาเหตุ | แก้ไข |
|-------|--------|-------|
| UI แสดง "หมดเวลา" แต่ข้อมูลบันทึกครบ | `retry_after: 90` ทำให้ queue ส่ง job ซ้ำ | เพิ่ม `retry_after: 3900` + `$tries = 1` |
| "Target page, context or browser has been closed" ทุก record | Navigate headed browser ไปหน้าอื่นก่อนดึง cookies ทำให้ session เสีย | ย้ายการดึง display name ไป headless browser |
| Checkbox ใน NAP form ไม่ทำงาน | `page.check()` fail เงียบ | ใช้ `page.evaluate()` + dispatch events |
| callback ส่งค่า null | `$validated['items']` ถูก Laravel validate() ตัด fields | ใช้ `$request->input('items')` |
| VPS เข้า callback URL ไม่ได้ | NAT loopback (VPS เดียวกับ CAREMAT) | เพิ่ม `/etc/hosts` mapping |
| Browser crash กลาง job | memory leak หรือ Chromium ค้าง | ระบบ auto restart browser + retry สูงสุด 2 ครั้ง/record |
| SSO redirect ขณะกรอก RR form | NAP Plus refresh OAuth token | รอ auto-redirect กลับ 30 วินาที (ไม่ต้อง login ใหม่) |
| Login timeout → ไม่มี email/dashboard | ThaiD ไม่ได้สแกนใน 60 วินาที | ทุก record ถูก skip, ไม่สร้าง record ใน DB — ต้องส่ง job ใหม่ |

### การดู Log

```bash
# Laravel log (job lifecycle)
sshinspace "tail -200 /var/www/actse-clinic.com/autonap/storage/logs/laravel.log"

# ดู Playwright output เฉพาะ job
sshinspace "grep 'autonap-ff9ff144' /var/www/actse-clinic.com/autonap/storage/logs/laravel.log"

# ดู request ที่เข้ามา
sshinspace "ls -la /var/www/actse-clinic.com/autonap/storage/app/private/autonap_logs/$(date +%Y-%m-%d)/"

# ดู failed jobs
sshinspace "php8.4 /var/www/actse-clinic.com/autonap/artisan queue:failed"
```

### ดู Email Report

- ส่งไปที่ `MAIL_TO_ADDRESS` อัตโนมัติหลัง job จบ
- Subject format: `VCT Report — rsat_pte (Success 15/15)`
- ถ้าไม่ได้รับ → ตรวจ `MAIL_SCHEME=smtps` (port 465 ต้องใช้ implicit SSL)
