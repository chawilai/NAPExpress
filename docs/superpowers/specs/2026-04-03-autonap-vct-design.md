# AutoNAP VCT Design Spec

## Overview

Extend AutoNAP to support VCT (Voluntary Counseling and Testing) form recording on NAP Plus, with optional HIV lab request. Uses the same ThaiID login + Playwright architecture as RR.

## What It Does

1. Receives batch of items from CAREMAT with `form_type: "VCT"`
2. Logs in to NAP Plus via ThaiID QR scan (same as RR)
3. For each item: fills VCT form → submits → extracts VCT ID
4. If `request_lab: true`: fills HIV Lab Request → submits → extracts lab code
5. Sends callback to CAREMAT per record with VCT ID + lab code

## API Contract

### Request: `POST /api/jobs`

```json
{
  "site": "mplus_cmi",
  "fy": "2026",
  "form_type": "VCT",
  "callback_url": "https://caremat.actse-clinic.com/api/autonap_callback.php",
  "ably_channel": "autonap:mplus_cmi",
  "items": [
    {
      "source_id": "76140",
      "source": "clinic",
      "id_card": "1550700153989",
      "kp": "MSM",
      "cbs": "นภสร ใจกาศ",
      "uic": "ศอ160742",
      "service_date": "04/03/2569",
      "request_lab": true,
      "row_id": "VCT_clinic_76140_1550700153989"
    }
  ]
}
```

### Job-level fields

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| `site` | string | yes | — | Site/clinic name |
| `fy` | string | yes | — | Fiscal year |
| `form_type` | string | no | `"RR"` | `"VCT"` or `"RR"`. Omit for backward-compatible RR |
| `callback_url` | string | yes | — | URL for per-record callbacks |
| `ably_channel` | string | no | — | WebSocket channel for progress |
| `method` | string | no | `"ThaiID"` | Login method |

### Per-item fields

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| `source_id` | string | yes | — | Record ID in CAREMAT |
| `source` | string | yes | — | `"clinic"` or `"reach"` |
| `id_card` | string | yes | — | Thai ID (13 digits) |
| `kp` | string | yes | — | Key population group |
| `cbs` | string | yes | — | CBS staff name |
| `service_date` | string | yes | — | VCT date (dd/mm/yyyy Buddhist era) |
| `uic` | string | no | `""` | UIC code (if from Reach) |
| `request_lab` | boolean | no | `false` | Request HIV lab after VCT |
| `row_id` | string | yes | — | Unique row identifier for callback |

### Valid `kp` values

MSM, TGW, TGM, TGSW, FSW, MSW, PWID, PWUD, Migrant, Prisoners, General Population, ANC, Partner of KP, Partner of PLHIV, nPEP, บุคลากรทางการแพทย์, คลอดจากแม่ติดเชื้อเอชไอวี, สามี/คู่ของหญิงตั้งครรภ์

## Callback

AutoNAP POSTs to `callback_url` after each record:

```json
{
  "form_type": "VCT",
  "source_id": "76140",
  "source": "clinic",
  "uic": "ศอ160742",
  "id_card": "1550700153989",
  "kp": "MSM",
  "fy": "2026",
  "nap_code": "V68-10681-9521214",
  "nap_lab_code": "ANTIHIV-41692-6805-18109885",
  "nap_comment": "VCT บันทึกสำเร็จ AutoNAP",
  "nap_staff": "นภสร ใจกาศ",
  "status": "success",
  "row_id": "VCT_clinic_76140_1550700153989"
}
```

- `nap_code`: VCT ID (null if failed)
- `nap_lab_code`: Lab code (null if `request_lab=false` or lab failed)
- `status`: always `"success"` (= attempted). Check `nap_code` for actual result.

## VCT Form: NAP Plus Selectors & Business Rules

### Flow

1. Navigate to `https://dmis.nhso.go.th/NAPPLUS/vct/createVCT.do?actionName=load`
2. Fill `vct_date` + `pid` → click "เพิ่มข้อมูลการให้คำปรึกษา"
3. Fill form fields (see defaults below)
4. Click "บันทึก" (`#cmdPreview`) → click "ตกลง" (confirm page)
5. Extract VCT ID from result page: `td` containing "รหัสผู้รับคำปรึกษา VCT (VCT ID)"

### Form defaults (AutoNAP fills automatically)

| Field | Selector | Default | Logic |
|-------|----------|---------|-------|
| ช่องทางมารับบริการ | `#vct_receive_from_status_7` | RRTTR (index 7) | Always check. Also fill `#rrtr_uic` with item's `uic` |
| ประเมินความเสี่ยง | `input[name="risk_flag"]` | `"Y"` | KP = มีพฤติกรรมเสี่ยง |
| กลุ่มผู้มารับบริการ | `#vct_target_group_status_{index}` | Map from `kp` | See KP→index mapping below |
| ปัจจัยเสี่ยง | `#vct_risk_factor_status_0` | Always check index 0 | Add index 3 if PWID |
| Pre-test | `#pre_test_status_y` | "ทำ" | |
| Pre-test date | `#pre_test_date` | = `service_date` | |
| Pre-test type | `#pre_test_type_1` | PICT (value 1) | |
| Pre-test method | `#pre_test_method_2` | รายบุคคล (value 2) | |
| Post-test | `#post_test_status_y` | "ทำ" | |
| Post-test date | `#post_test_date` | = `service_date` | |
| Couple counseling | `#post_test_couple_result_status_3` | ไม่มีคู่ (value 3) | |
| STI ส่งต่อ | `#post_test_sti_2` | ไม่ส่งต่อ (value 2) | |
| เมทาโดน | `#post_test_methadone_2` | ไม่ได้รับ (value 2) | |
| ถุงยาง | `condom_receive_y` | รับ | |
| ถุงยาง 53mm | `#condom_amount_53` | `"10"` | |
| สารหล่อลื่น | `#lubricant_amount` | `"10"` | |

### KP → target_group_status index mapping

| KP | Index | Hidden value |
|----|-------|-------------|
| MSM | 0 | 3 |
| PWID | 1 | 1 |
| ANC | 2 | 16 |
| TGW | 3 | 4 |
| PWUD | 4 | 2 |
| คลอดจากแม่ติดเชื้อเอชไอวี | 5 | 17 |
| TGM | 6 | 5 |
| Partner of KP | 7 | 11 |
| บุคลากรทางการแพทย์ | 8 | 14 |
| TGSW | 9 | 10 |
| Partner of PLHIV | 10 | 12 |
| nPEP | 11 | 15 |
| MSW | 12 | 8 |
| Prisoners | 13 | 7 |
| General Population | 14 | 13 |
| FSW | 15 | 9 |
| Migrant | 16 | 6 |
| สามี/คู่ของหญิงตั้งครรภ์ | 17 | 18 |

## HIV Lab Request Flow (when `request_lab: true`)

1. Navigate to HIV Lab Request create page
2. Select `hiv_lab_type` = `"1"` (ANTIHIV)
3. Fill `pid` + `hiv_labreq_date` (= `service_date`)
4. Click "เพิ่มข้อมูลการส่งตรวจ HIV"
5. Click "บันทึก" (`#cmdPreview`)
6. If "ใช้สิทธิเกิน 2 ครั้ง" confirmation appears: fill `confirmPid` + click "ยืนยัน"
7. Extract lab code from result page: `td` containing "เลขที่ใบส่งตรวจทางห้องปฏิบัติการ"

### Lab Request NAP URLs

- Create: `https://dmis.nhso.go.th/NAPPLUS/hivLabRequest/createHivLabRequest.do?actionName=load`
- Submit: `https://dmis.nhso.go.th/NAPPLUS/hivLabRequest/createHivLabRequest.do`

## Files to Modify

### 1. `app/Http/Controllers/Api/AutoNapJobController.php`
- Add `form_type` validation (nullable string, default `"RR"`)
- Conditional validation: VCT items don't require `rr_form`
- Pass `form_type` to `ProcessAutoNapJob`

### 2. `app/Jobs/ProcessAutoNapJob.php`
- Add `form_type` constructor parameter (default `"RR"`)
- Include `form_type` in data file for Playwright
- Handle VCT results: pass `nap_lab_code` to callback

### 3. `automation/thaid_login_and_record.cjs`
- Add `NAP_URLS.createVCT` and `NAP_URLS.createHivLab`
- Add `VCT_KP_MAP`: kp string → target_group index
- Add `fillAndSubmitVCT(page, item, dryRun)` function
- Add `fillAndSubmitLabRequest(page, item, dryRun)` function
- Modify main loop: route by `form_type` from data file
- VCT results include `{ vct_code, lab_code }` instead of single `nap_code`

### 4. `app/Services/NapCallbackService.php`
- Add `form_type` and `nap_lab_code` to `buildPayload()`

### 5. `routes/api.php` — New dummy callback endpoint
- `POST /api/test-callback` — logs payload, returns 200
- For testing without CAREMAT's real endpoint

## Backward Compatibility

- `form_type` defaults to `"RR"` — existing RR integrations unchanged
- `rr_form` validation only required when `form_type` is not `"VCT"`
- Callback adds new fields (`form_type`, `nap_lab_code`) — additive only
