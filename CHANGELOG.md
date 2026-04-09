# Changelog — AutoNAP

## v1.5.0 — Dashboard & Crash Recovery (2026-04-08 ~ 09)

### Features
- **Dashboard** (`/autonap`) — Real-time monitoring via Ably, 4 worker cards, queue status, stats with period filter (Today/Week/Month/All/Custom)
- **Browser crash auto-recovery** — Detect crash → restart browser → retry record (max 2 retries/record)
- **Batch browser restart** — ปิด/เปิด Chromium ใหม่ทุก 25 records ป้องกัน memory leak
- **`staff_name`** — CAREMAT ส่งชื่อ login user มา → ใช้เป็น `nap_staff` ใน callback
- **Queue estimated wait time** — คำนวณจาก records ahead × avg sec/record
- **Max 50 items/request** — validation limit (เดิมไม่จำกัด)
- **`next_hcode` + `next_hname`** — RR callback ส่งรหัสสถานพยาบาลส่งต่อกลับ
- **Job history database** — `autonap_requests` + `autonap_records` tables ใน `db_haanhaan`
- **Human-friendly errors** — `humanError()` แปลง Playwright errors เป็นภาษาไทยสำหรับ UI

### Fixes
- **SSO redirect on RR form** — เพิ่ม wait 30s (เดิมมีแค่ VCT/Lab/Result)
- **Login timeout → skip email + DB** — ไม่ส่ง report ถ้าแค่ login ไม่ทัน
- **Dashboard undefined workers** — แก้ stale workerData + validate job_id before render
- **`napSiteName` missing in email** — หลุดจาก `$report` array
- **CarbonImmutable type error** — fix `getStats()` type hint

### Infrastructure
- Swap: 4GB → **8GB**
- swappiness: 60 → **10**
- Workers: 2 → **4** (numprocs=4)

---

## v1.4.0 — Email Report & Duplicate VCT Lookup (2026-04-04 ~ 07)

### Features
- **Email report** — ส่ง HTML email ทุก job ที่จบ (NAP username, site, results table)
- **GET /api/jobs/status** — CAREMAT เช็ค running job + reconnect Ably channel
- **VCT duplicate lookup** — เมื่อ VCT ซ้ำ → ค้นหา code เดิมจาก searchVCT/searchHivLab/searchResponseLabRequest → ส่ง callback กลับแทน error
- **NAP display name + site extraction** — จาก `table.userBar td.name` ใน headless browser
- **`nap_staff` = NAP login user** — ใช้ชื่อผู้ login แทน REACH CBS worker

### Fixes
- **Queue `retry_after: 90 → 3900`** — ป้องกัน job ซ้อน (root cause ของ UI error)
- **`$tries = 1`** — ไม่ retry job ที่ fail
- **`failed()` method** — release cache lock + Ably notify เมื่อ job fail
- **NAP display name crash** — ย้ายจาก headed → headless browser (ห้าม navigate headed หลัง SSO)
- **UIC missing in results** — เพิ่ม `uic` ทุก `results.push()`

---

## v1.3.0 — VCT Form Adjustments (2026-04-07)

### Fixes (per site feedback)
- Pre-test type: **PICT → CITC** (ผู้รับบริการแสดงความต้องการตรวจด้วยตนเอง)
- Couple counseling: **ไม่มีคู่ → มีคู่แต่คู่ไม่ได้ตรวจ**
- STI no result: **ไม่ส่งต่อ+ไม่มีข้อบ่งชี้ → ส่งต่อ+ไม่ได้ตรวจ**
- เพิ่ม **หน่วยงาน/แผนกที่ส่งต่อ** — walk in → คลินิก / reach → ออกพื้นที่

### Infrastructure
- Supervisor: `--timeout=3700 --tries=1`, `stopwaitsecs=3700`
- CI: PHP 8.4 only (drop 8.3/8.5)

---

## v1.2.0 — HIV Test Result & SSO Handling (2026-04-03)

### Features
- **HIV Test Result recording** — Step 3 หลัง VCT + Lab → บันทึก Negative/Positive/Inconclusive
- **Skip logic** — ข้าม VCT/Lab ถ้ามี code อยู่แล้ว → ไปลงผลตรงๆ
- **SSO auto-redirect wait** — รอ 30 วินาทีเมื่อ NAP Plus redirect ไป IAM (VCT/Lab/Result)
- **Duplicate job rejection** — Cache lock per site+formType → 429 ถ้ามี job ซ้ำ

### Fixes
- Separate browser for HIV result → ใช้ same page เหมือน manual flow
- Re-inject cookies after HIV result

---

## v1.1.0 — VCT + Lab Request (2026-03-28 ~ 04-02)

### Features
- **VCT form** — กรอก VCT อัตโนมัติ → ได้ VCT ID
- **Request Lab HIV** — ส่งตรวจ ANTIHIV ต่อจาก VCT → ได้ ANTIHIV code
- **2-step callback** — VCT code ก่อน, Lab code ตามหลัง
- **STI support** — Syphilis/CT/NG + TPHA ถ้า positive
- **KP mapping** — 11 ค่า + 5 aliases (Female→General, TG→TGW, PWID-Male→PWID)
- **Condom defaults** — ทุกขนาด (49,52,53,54,56) = 10 ชิ้น/ขนาด

### Fixes
- VCT confirm page handling (click ตกลง + re-fill date/PID)
- Lab code extraction retry
- Condom field unhide via DOM

---

## v1.0.0 — RR Recording (2026-03-24 ~ 26)

### Features
- **RR form** — กรอก RRTTR อัตโนมัติ → ได้ RR code
- **ThaiID QR login** — Playwright headed browser → QR scan via Ably → cookies transfer to headless
- **Real-time progress** — Ably events per record (processing, searching, filling, submitting, success/fail)
- **POST /api/jobs** — API endpoint for CAREMAT integration
- **Request JSON logging** — audit trail per job

### Architecture
- 2-browser design: headed (login) + headless (form filling)
- Playwright DOM manipulation (`page.evaluate()`) for NAP Plus forms
- Laravel queue (database driver) + Supervisor workers
- Ably pub/sub for real-time UI

### Fixes
- Laravel `validate()` stripping `rr_form` fields → use `$request->input('items')`
- `page.check()` fails silently → use `page.evaluate()` + dispatch events
- DirectHTTP → Playwright (NAP requires JS processing)
