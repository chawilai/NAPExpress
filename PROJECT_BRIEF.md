# AutoNAP — Project Brief

## ที่มาที่ไป

### ปัญหา
คลินิกที่ทำงานด้าน HIV/AIDS ภายใต้โครงการ KPLHS ต้องรายงานผลการทำงานเข้าระบบ **NAP Plus (National AIDS Program)** ของ สปสช. ผ่านเว็บ `https://dmis.nhso.go.th/NAPPLUS/` เพื่อเคลมเงินค่าบริการคืน

ข้อมูลทุกอย่างถูกบันทึกอยู่ในระบบ **CAREMAT (ACTSE Clinic)** แล้ว แต่ยังต้องจ้างเจ้าหน้าที่หลายคนมานั่งคีย์ซ้ำเข้าเว็บ NAP ทุกจุดบริการ ซึ่ง:
- ซ้ำซ้อน เสียเวลา เสียค่าแรง
- ถ้าข้อมูลผิด → ไม่ได้เงิน
- มีหลาย form หลายประเภท (12+ ประเภท)

### แนวคิด
สร้าง **AutoNAP** เป็น standalone website แยกจาก CAREMAT ที่:
1. องค์กรทั่วไป (ไม่ได้ใช้ ACTSE Clinic) → Download Excel template → กรอก → Upload → ระบบคีย์ NAP ให้อัตโนมัติ
2. ACTSE Clinic → ต่อผ่าน API ส่งข้อมูลให้บันทึก (เก็บค่า API)
3. กรอกตรงบนเว็บ AutoNAP → คีย์ NAP ให้

### Revenue Model
- เว็บ: ฟรีหรือเก็บค่าน้อยมาก
- API integration: เก็บค่าต่อ record / subscription

---

## ประเภท Form NAP ที่ต้องรองรับ

| # | ประเภท | Method | หมายเหตุ |
|---|--------|--------|----------|
| 1 | **Reach (RR/TTR)** | Playwright | มี script พร้อมแล้ว |
| 2 | **Clinic (RR Clinic)** | Playwright | มีหน้า napRRClinic.php แล้ว |
| 3 | VCT Registration | Playwright | |
| 4 | HIV Test Results | Playwright | |
| 5 | HIV Patient Registration | Playwright | |
| 6 | **CD4 Lab** | NHSO Lab API | มี API wrapper แล้ว |
| 7 | **VL (Viral Load)** | NHSO Lab API | มี API wrapper แล้ว |
| 8 | **HCV** | NHSO Lab API | มี API wrapper แล้ว |
| 9 | **Syphilis Screening** | NHSO Lab API | มี API wrapper แล้ว |
| 10 | PrEP Dispensing | Playwright | |
| 11 | HIVST Distribution | Playwright | |
| 12 | (อื่นๆ ที่อาจเพิ่มในอนาคต) | TBD | |

---

## User Flow

```
สมัคร (ชื่อ + องค์กร + ยืนยัน)
  ↓
Login → Dashboard (สรุปสถิติ)
  ↓
เลือก form type (Reach / VCT / PrEP / ...)
  ↓
Download Excel Template
  (มีหัวตาราง + คำแนะนำ + format + ตัวอย่าง)
  ↓
กรอก Excel offline → Upload + ใส่ NAP username/password
  (ไม่เก็บ credentials ลง DB — ใช้ครั้งเดียว)
  ↓
ระบบ Validate → แสดง Preview + Error list
  ↓
User ยืนยัน → Job ทำงาน Background
  ↓
Playwright login NAP ครั้งเดียว → Loop บันทึกทุก record
  ↓
Ably WebSocket push ความคืบหน้า real-time
  (user ปิดหน้าต่างได้ — job ทำงานต่อ)
  ↓
เสร็จ → Download ผลลัพธ์ + ส่ง Email สรุป
```

### ผลลัพธ์ที่ได้

| # | ID Card | Status | NAP Code | Message |
|---|---------|--------|----------|---------|
| 1 | xxxx3237 | สำเร็จ | RR-26-001234 | - |
| 2 | xxxx8841 | สำเร็จ | RR-26-001235 | - |
| 3 | xxxx5512 | ล้มเหลว | - | ซ้ำในระบบ FY2569 |

- ลำดับ # ตรงกับแถวใน Excel ต้นฉบับ
- เลขบัตร masked (แสดงแค่ 4 ตัวท้าย)
- กลับมาดูผล + download ได้ทีหลัง

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Runtime | Node.js 20 LTS |
| Web Framework | Express.js |
| Frontend | Vue 3 + PrimeVue 4 + Vite |
| Database | MySQL 8 + Knex.js |
| Job Queue | BullMQ + Redis |
| Browser Automation | Playwright |
| Real-time | Ably WebSocket |
| Excel | ExcelJS |
| Email | Nodemailer |
| Auth | JWT (access + refresh) |

### เหตุผลที่ใช้ Node.js แทน PHP
- Playwright เป็น native Node.js — ไม่ต้อง shell out จาก PHP (ใน CAREMAT ใช้ `proc_open` ซึ่ง fragile)
- Job ใช้เวลานาน (นาที-ชั่วโมง) — Node async event loop จัดการได้ดีกว่า PHP
- Ably Node SDK เป็น first-class

---

## Database Schema

```sql
-- 5 tables
organizations   -- id, name, hcode, verified, subscription, api_enabled
users           -- id, org_id, email, password_hash, name, role
jobs            -- id, org_id, user_id, form_type, method, status, counts, ably_channel
job_rows        -- id, job_id, row_number, pid_masked, nap_response_code, error_message
api_keys        -- id, org_id, key_hash, rate_limit (สำหรับ API integration)
```

**NAP credentials ไม่เก็บ DB** — อยู่ใน BullMQ job data (Redis memory) ระหว่าง process เท่านั้น พอ job จบ credentials หายไปกับ process

---

## สิ่งที่มีอยู่แล้วจาก CAREMAT

### 1. Playwright Automation Scripts

#### NAP RR Form Filling (`task/task_2025-09-22_playwright_autonapplus.js`)
Script ที่ทำงานได้แล้วสำหรับกรอก form RR/TTR ใน NAP Plus:

**Form Elements & Selectors:**

```
Risk Behaviors (6 checkboxes):
  #rrttr_risk_behavior_status_0  = TG (Transgender)
  #rrttr_risk_behavior_status_1  = MSM
  #rrttr_risk_behavior_status_2  = SW (Sex Worker)
  #rrttr_risk_behavior_status_3  = PWID
  #rrttr_risk_behavior_status_4  = Migrant
  #rrttr_risk_behavior_status_5  = Prisoner

Target Groups (17 checkboxes):
  #rrttr_target_group_status_0   = MSM
  #rrttr_target_group_status_1   = PWID
  #rrttr_target_group_status_2   = ?
  #rrttr_target_group_status_3   = TGW
  #rrttr_target_group_status_4   = ?
  #rrttr_target_group_status_5   = ?
  #rrttr_target_group_status_6   = TGM
  #rrttr_target_group_status_7   = ?
  #rrttr_target_group_status_8   = ?
  #rrttr_target_group_status_9   = TGSW
  #rrttr_target_group_status_10  = ?
  #rrttr_target_group_status_11  = ?
  #rrttr_target_group_status_12  = MSW
  #rrttr_target_group_status_13  = Prisoner
  #rrttr_target_group_status_14  = General Population
  #rrttr_target_group_status_15  = FSW
  #rrttr_target_group_status_16  = Migrant

Access Type:
  #access_type_1  = ใน DIC
  #access_type_2  = นอก DIC (default)
  #access_type_3  = Social Media

Occupation (#occupation select):
  '01' = นักเรียน/นักศึกษา
  '02' = ข้าราชการ
  '03' = รับจ้าง/แรงงาน (DEFAULT)
  '04' = พนักงานบริษัท
  '05' = ค้าขาย
  '06' = ว่างงาน
  ... (29 codes total)

Knowledge Status (5 checkboxes):
  #rrttr_knowledge_status_0  = HIV
  #rrttr_knowledge_status_1  = STD
  #rrttr_knowledge_status_2  = TB
  #rrttr_knowledge_status_3  = Harm Reduction (ลดอันตรายจากยาเสพติด)
  #rrttr_knowledge_status_4  = HCV

PPE (5 checkboxes):
  #rrttr_ppe_status_0  = ถุงยางชาย
  #rrttr_ppe_status_1  = ถุงยางหญิง
  #rrttr_ppe_status_2  = สารหล่อลื่น
  #rrttr_ppe_status_3  = อุปกรณ์ฉีดยาปลอดเชื้อ
  #rrttr_ppe_status_4  = หน้ากากอนามัย

Condom Sizes:
  #rrttr_condom_amount_49  = ขนาด 49mm
  #rrttr_condom_amount_52  = ขนาด 52mm
  #rrttr_condom_amount_53  = ขนาด 53mm
  #rrttr_condom_amount_54  = ขนาด 54mm
  #rrttr_condom_amount_56  = ขนาด 56mm
  (ต้อง show ด้วย page.evaluate ก่อนกรอกค่า)

  #rrttr_female_condom_amount  = ถุงยางหญิง
  #rrttr_lubricant_amount      = สารหล่อลื่น (ซอง)

Healthcare Referral:
  #next_hcode   = รหัสหน่วยบริการ (e.g. 41936)
  #next_hname   = ชื่อหน่วยบริการ
  #next_place   = สถานที่

Forward Services:
  #hiv_forward_1/2/3   = HIV (1=เจ้าหน้าที่พาไป, 2=ไปเอง, 3=ไม่ส่งต่อ)
  #sti_forward_1/2/3   = STI
  #tb_forward_1/2/3    = TB
  #hcv_forward_*       = HCV (ถ้ามี)
  #methadone_forward_* = Methadone (ถ้ามี)
```

**Condom Size Visibility Hack** (ต้องทำก่อนกรอกค่า):
```javascript
await page.evaluate(() => {
  const sizes = ['49', '52', '53', '54', '56'];
  sizes.forEach((size) => {
    document
      .querySelectorAll(
        `#lb_condom_amount_${size}_1, #lb_condom_amount_${size}_2`
      )
      .forEach((e) => {
        e.style.display = 'inline';
      });
    const input = document.querySelector(`#rrttr_condom_amount_${size}`);
    if (input) input.style.display = 'inline';
  });
});
```

---

### 2. KP → Risk/Target Group Mapping (`autoNapPlus.php`)

```
KP Code          → Risk Behavior Index  → Target Group Index
─────────────────────────────────────────────────────────────
MSM              → [1]                  → [0]
MSW              → [2]                  → [12]
FSW              → [2]                  → [15]
TG / TGW         → [0]                  → [3]
TGM              → [0]                  → [6]
TGSW             → [0]                  → [9]
PWID (all)       → [3]                  → [1]
MIGRANT          → [4]                  → [16]
PRISONER         → [5]                  → [13]
MALE / FEMALE    → []                   → [14]  (General Pop)
```

**Sub-KP ที่มีหลาย index:**
- TGSW → risk[0] + target[9] (TG ที่เป็น SW → TGW + TGSW)

**PWID พิเศษ:**
- Knowledge: เพิ่ม index 3 (Harm Reduction) + 4 (HCV)
- Place: เพิ่ม index 4
- PPE: เพิ่ม index 3 (อุปกรณ์ฉีดยาปลอดเชื้อ)

---

### 3. Occupation Mapping (Thai → Code)

```
Text Matching (substring, case-insensitive):
'นักเรียน', 'นักศึกษา', 'student'      → '01'
'ข้าราชการ', 'government'              → '02'
'รับจ้าง', 'general', 'แรงงาน'         → '03' (DEFAULT)
'พนักงานบริษัท', 'office'              → '04'
'ค้าขาย', 'self-employed', 'ธุรกิจ'    → '05'
'ว่างงาน', 'unemployed'               → '06'
```

---

### 4. Thai Date Conversion

```
CE → Buddhist Era:  year + 543
Format: dd/mm/yyyy (e.g. 02/07/2568 = July 2, 2025)
```

**UIC → Birth Date:**
```
UIC last 6 chars = DDMMYY
Year heuristic: YY > 70 → 19xx, else 20xx
Example: UIC "...020785" → DOB 1985-07-02
```

---

### 5. NAP RR Clinic Form (`napRRClinic.php`)

หน้าสำหรับบันทึก **RR Clinic** — เคสที่ผ่าน Reach แล้ว (มี nap_code) แต่ยังไม่ได้บันทึก Clinic:

**Flow:**
```
Reach → NAP RR (nap_code = RR-xxx) → Clinic Visit → NAP RR Clinic (rr_clinic_code = ?)
```

**Columns:**
- เลข NAP (Reach) — จากการลง Reach ก่อนหน้า (อ่านอย่างเดียว)
- รหัส RR Clinic — ช่องกรอกรหัสใหม่จาก NAP
- Comment, Staff, Date — บันทึกผล

**Data Source:** ใช้ `fetchNHSOForReach()` เหมือน Reach แต่ filter:
- `nap_code` ไม่ว่าง (ลง Reach แล้ว)
- `rr_clinic_code` ว่าง (ยังไม่ลง Clinic)

---

### 6. NHSO Lab API (สำหรับ CD4/VL/HCV/Syphilis)

```
Endpoint: https://dmis.nhso.go.th/NAPPLUSLABAPI/api/set_lab_result
Headers:
  Content-Type: application/json
  UserName: <nap_username>
  Password: <nap_api_key>

Retry: GET=3 attempts, POST=2 attempts (ป้องกัน duplicate)
Timeout: connect 2s, total 8-12s
```

**Helper Functions (function_plugin.php):**
```php
nhso_proxy_call($url, $api_key, $username, $data, $label)
nhso_get_lab_request($api_key, $username, $data)
nhso_get_lab_result($api_key, $username, $data)
nhso_set_lab_result($api_key, $username, $data)
```

---

### 7. NAP Plus System URLs

```
Login Page:     https://dmis.nhso.go.th/NAPPLUS/login.jsp
Login POST:     https://dmis.nhso.go.th/NAPPLUS/login.do
Create RR:      https://dmis.nhso.go.th/NAPPLUS/rrttr/createRRTTR.do
Person Search:  https://dmis.nhso.go.th/NAPPLUS/common/personPopupSearch.do
VCT History:    https://dmis.nhso.go.th/NAPPLUS/common/vctHistoryPopupSearch.do
VCT Create:     https://dmis.nhso.go.th/NAPPLUS/vct/createVCT.do
Lab API:        https://dmis.nhso.go.th/NAPPLUSLABAPI/api/
```

---

### 8. NAP Credentials Management

**Table: `napplus_credentials`**
```sql
id              BIGINT UNSIGNED AUTO_INCREMENT
user_id         INT NULL (UNIQUE)
username        VARCHAR(100)
password        VARCHAR(255)  -- plaintext ใน CAREMAT
active          TINYINT(1) DEFAULT 1
label           VARCHAR(100)  -- PROD/TEST
last_used_at    DATETIME
use_count       INT UNSIGNED DEFAULT 0
```

**ใน AutoNAP จะไม่เก็บ credentials ลง DB** — ผ่าน process memory เท่านั้น

---

## HCODE Reference (รหัสหน่วยบริการ)

```
caremat   = 41936
(แต่ละ site มี hcode ของตัวเอง เก็บใน site_specific)
```

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────┐
│                    AutoNAP Website                        │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐               │
│  │  Login/   │  │Dashboard │  │ New Job  │               │
│  │ Register  │  │  Stats   │  │  Upload  │               │
│  └──────────┘  └──────────┘  └────┬─────┘               │
│                                    │                      │
│                          ┌─────────▼──────────┐          │
│                          │   Validate Excel    │          │
│                          │   Preview + Errors  │          │
│                          └─────────┬──────────┘          │
│                                    │ + NAP credentials    │
│                          ┌─────────▼──────────┐          │
│                          │    BullMQ Queue     │          │
│                          │  (Redis - in memory)│          │
│                          └────┬──────────┬────┘          │
│                               │          │                │
│                    ┌──────────▼┐  ┌──────▼──────┐        │
│                    │ Playwright │  │  NHSO Lab   │        │
│                    │  Worker    │  │  API Worker │        │
│                    │            │  │             │        │
│                    │ Reach RR   │  │ CD4, VL     │        │
│                    │ RR Clinic  │  │ HCV, Syph   │        │
│                    │ VCT, PrEP  │  │             │        │
│                    │ HIVST      │  │             │        │
│                    └─────┬──────┘  └──────┬──────┘        │
│                          │                │                │
│                    ┌─────▼────────────────▼─────┐        │
│                    │      Ably WebSocket         │        │
│                    │   (real-time progress)       │        │
│                    └─────────────┬───────────────┘        │
│                                  │                        │
│                    ┌─────────────▼───────────────┐        │
│                    │  Results (masked PII)        │        │
│                    │  + Email Report              │        │
│                    │  + Download Excel            │        │
│                    └─────────────────────────────┘        │
└─────────────────────────────────────────────────────────┘

┌──────────────────────────────┐
│   ACTSE Clinic (CAREMAT)     │
│   + Other Systems            │
│                              │
│   POST /api/v1/jobs          │──── API Integration (เก็บค่า)
│   (API Key auth)             │
└──────────────────────────────┘
```

---

## Implementation Phases

| Phase | สิ่งที่ทำ | สถานะ |
|-------|---------|-------|
| **1. Foundation** | Project structure, Auth, DB, Vue SPA scaffold | ✅ Done |
| **2. Reach RR** | Excel template, Upload, Validate, Playwright fill, Ably progress, Results | Pending |
| **3. Lab API** | CD4, VL, HCV, Syphilis via NHSO REST API | Pending |
| **4. More Forms** | VCT, HIV test, HIV register, PrEP, HIVST, RR Clinic | Pending |
| **5. Polish** | Email reports, Download results, Job history | Pending |
| **6. API** | External API for ACTSE Clinic + other systems | Pending |
| **7. Deploy** | Production on VPS | Pending |

---

## Files Reference (CAREMAT source to port)

| File | สิ่งที่ใช้ |
|------|---------|
| `napktb/autoNapPlus.php` | KP mapping, occupation codes, UIC→birthdate, date conversion, rr_form structure |
| `napktb/napRRClinic.php` | RR Clinic form structure, data source |
| `task/task_2025-09-22_playwright_autonapplus.js` | Playwright selectors, form filling sequence, condom hack |
| `task/task_2025-07-03 AutoNAP RR by playwright.js` | Architecture design, WebSocket flow, email template |
| `task/task_2025-09-24_nap_auto_on_rr.js` | UI/UX design, default values, KP-specific rules |
| `task/task_2025-08-15 playwright to check NAP RR history.js` | NAP URLs, credentials table, search flow |
| `services/ajaxCheckNapRR.php` | PHP→Node process spawn, credential handling |
| `services/ajaxNHSO_NAP.php` | NAP record save/update logic |
| `services/nhso_api_proxy/set_lab_result.php` | NHSO Lab API URL, headers, retry logic |
| `helpers/function_plugin.php` | nhso_proxy_call(), fetchNHSOForReach() |
