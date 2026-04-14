# AutoNAP — CSV Template Guide

คู่มือการใช้งาน CSV templates สำหรับอัพโหลดข้อมูลเข้าสู่ระบบ AutoNAP เพื่อบันทึกลง NAP Plus อัตโนมัติ

## ไฟล์ Template

| ฟอร์ม | ไฟล์ | ใช้เมื่อ |
|---|---|---|
| **RR** (Reach RR) | `template_rr.csv` | บันทึก reach / outreach record |
| **VCT + Testing** | `template_vct.csv` | บันทึก VCT + ส่งตรวจ lab + ผลตรวจ HIV (ในไฟล์เดียว) |

> **หมายเหตุ:** VCT, Lab HIV และ Result รวมอยู่ในไฟล์เดียวกัน (`template_vct.csv`) เพราะในทางปฏิบัติมักมาด้วยกัน ช่อง lab/result สามารถปล่อยว่างได้ถ้ายังไม่มีข้อมูล

## กฎทั่วไป

- **ต้องใช้ UTF-8 encoding** (รองรับภาษาไทย) — Excel: Save As → CSV UTF-8
- **วันที่รูปแบบ `YYYY-MM-DD`** (ค.ศ.) ระบบจะแปลงเป็น พ.ศ. ให้อัตโนมัติ
- **ห้ามลบหรือเปลี่ยนชื่อ header แถวแรก**
- **1 ไฟล์ = สูงสุด 50 records/batch** (ถ้าเกินให้แยกไฟล์)
- **ช่องที่ว่าง** ปล่อยว่างไว้ได้ (ระบบใช้ค่า default หรือข้ามช่องนั้น)

---

## 1. template_rr.csv — Reach RR Form

### Field Reference

| Field | ชนิด | จำเป็น | คำอธิบาย |
|---|---|---|---|
| `pid` | string (13 หลัก) | ✅ | เลขบัตรประชาชน |
| `uic` | string | ✅ | UIC code ของศูนย์ |
| `kp` | enum | ✅ | Key Population (ดูตารางด้านล่าง) |
| `service_date` | YYYY-MM-DD | ✅ | วันที่ให้บริการ |
| `occupation` | string | ⬜ | อาชีพ (ไทยหรือ eng) — ระบบ map เป็น code ให้ |
| `access_type` | 1/2/3 | ⬜ | 1=DIC, 2=นอก DIC, 3=Social media |
| `condom_49` | int | ⬜ | ถุงยาง (ข้อ 49) — default 0 |
| `condom_52` | int | ⬜ | ถุงยาง (ข้อ 52) — default 20 ถ้าเป็น 0 |
| `condom_53` | int | ⬜ | ถุงยาง (ข้อ 53) — default 0 |
| `condom_54` | int | ⬜ | ถุงยาง (ข้อ 54) — default 20 ถ้าเป็น 0 |
| `condom_56` | int | ⬜ | ถุงยาง (ข้อ 56) — default 20 ถ้าเป็น 0 |
| `female_condom` | int | ⬜ | ถุงยางผู้หญิง |
| `lubricant` | int | ⬜ | สารหล่อลื่น — default 20 ถ้าเป็น 0 |
| `next_hcode` | string (5) | ⬜ | hcode ศูนย์ที่ส่งต่อ |
| `hiv_forward` | 1/2/3 | ⬜ | 1=พาไป, 2=ไปเอง, 3=ไม่ส่งต่อ |
| `sti_forward` | 1/2/3 | ⬜ | เหมือน hiv_forward |
| `tb_forward` | 1/2/3 | ⬜ | เหมือน hiv_forward |

---

## 2. template_vct.csv — VCT + Lab HIV + Result (ไฟล์เดียวครบ)

รวมขั้นตอน VCT → Lab → Result ไว้ในไฟล์เดียว เพราะในทางปฏิบัติข้อมูลส่วนใหญ่มาพร้อมกัน

### Field Reference

#### ส่วนที่ 1 — VCT (จำเป็นทุกแถว)

| Field | ชนิด | จำเป็น | คำอธิบาย |
|---|---|---|---|
| `source_id` | string | ⬜ | ID อ้างอิงภายในศูนย์ (เช่น V001) |
| `id_card` | string (13 หลัก) | ✅ | เลขบัตรประชาชน |
| `uic` | string | ✅ | UIC code |
| `full_name` | string | ⬜ | ชื่อ-นามสกุล |
| `phone` | string | ⬜ | เบอร์โทร (10 หลัก) |
| `kp` | enum | ✅ | Key Population (ดูตารางด้านล่าง) |
| `cbs` | string | ✅ | ชื่อผู้ให้คำปรึกษา (Counselor) |
| `service_date` | YYYY-MM-DD | ✅ | วันที่ให้บริการ VCT |
| `occupation` | string | ⬜ | อาชีพ |
| `location` | enum | ⬜ | `DIC` / `Clinic` / `Outreach` / `Mobile` |

#### ส่วนที่ 2 — Lab HIV (กรอกเมื่อส่งตรวจ)

| Field | ชนิด | จำเป็น | คำอธิบาย |
|---|---|---|---|
| `request_lab` | boolean | ✅ | `true` = ส่งตรวจ lab, `false` = ไม่ส่ง |
| `test_type` | enum | ⬜* | `HIV` / `Syphilis` / `HBV` / `HCV` |
| `specimen_type` | enum | ⬜* | `blood` / `oral` / `serum` |
| `lab_code` | string | ⬜* | Lab Request ID เช่น `ANTIHIV-41692-6904-0001` |
| `provider_hcode` | string (5) | ⬜* | รหัสหน่วยบริการ (hcode) |

\* จำเป็นเมื่อ `request_lab = true`

#### ส่วนที่ 3 — Result (กรอกเมื่อได้ผลตรวจแล้ว)

| Field | ชนิด | จำเป็น | คำอธิบาย |
|---|---|---|---|
| `test_date` | YYYY-MM-DD | ⬜ | วันที่ทราบผล |
| `lab_status` | 1/2 | ⬜ | 1=ตรวจได้, 2=ตรวจไม่ได้ |
| `result` | 1/2/3 | ⬜ | 1=Positive, 2=Negative, 3=Inconclusive |
| `result_text` | string | ⬜ | คำอธิบายผล (เช่น `Negative`) |
| `remarks` | string | ⬜ | หมายเหตุ / การส่งต่อ |

> **ถ้ายังไม่มีผล:** ปล่อย `test_date`, `lab_status`, `result` ว่างไว้ ระบบจะบันทึกแค่ VCT + Lab Request ก่อน แล้วค่อยอัพเดทผลทีหลัง (upload ซ้ำที่ `lab_code` เดียวกัน)

### สถานการณ์ที่รองรับ (ดูตัวอย่างในไฟล์)

| Scenario | request_lab | Lab fields | Result fields |
|---|---|---|---|
| **VCT + Lab + Result Negative** (flow เต็ม) | `true` | ✅ กรอก | ✅ กรอก |
| **VCT + Lab + Result Positive** (ส่งต่อ ART) | `true` | ✅ กรอก | ✅ กรอก + remarks ส่งต่อ |
| **VCT + Lab + Inconclusive** (นัดตรวจซ้ำ) | `true` | ✅ กรอก | ✅ กรอก + remarks นัดซ้ำ |
| **VCT only** (ปฏิเสธ lab) | `false` | (ว่าง) | (ว่าง) |
| **VCT + Lab pending** (รอผล) | `true` | ✅ กรอก | (ว่าง — อัพเดททีหลัง) |

---

## KP (Key Population) Codes — ใช้ทุกฟอร์ม

| Code | ความหมาย |
|---|---|
| `MSM` | Men who have Sex with Men |
| `MSW` | Male Sex Worker |
| `FSW` | Female Sex Worker |
| `TG` / `TGW` | Transgender Woman |
| `TGM` | Transgender Man |
| `TGSW` | Transgender Sex Worker |
| `PWID` | People Who Inject Drugs |
| `MIGRANT` | ประชากรย้ายถิ่น |
| `PRISONER` | ผู้ต้องขัง |
| `MALE` / `FEMALE` | ประชากรทั่วไป |

## Occupation Auto-mapping

ระบบแปลงจากข้อความเป็น code ให้อัตโนมัติ:

| Keyword | Code | ความหมาย |
|---|---|---|
| นักเรียน, นักศึกษา, student | 01 | นักเรียน/นักศึกษา |
| ข้าราชการ, government | 02 | ข้าราชการ |
| รับจ้าง, แรงงาน, general | 03 | รับจ้าง (default) |
| พนักงานบริษัท, office | 04 | พนักงานบริษัท |
| ค้าขาย, ธุรกิจ, self-employed | 05 | ค้าขาย |
| ว่างงาน, unemployed | 06 | ว่างงาน |

## Result Code Reference

| Code | ความหมาย | การดำเนินการแนะนำ |
|---|---|---|
| `1` | **Positive** (ติดเชื้อ) | ส่งต่อ ART clinic + counseling post-test |
| `2` | **Negative** (ไม่ติดเชื้อ) | แจ้งผล + แนะนำ PrEP/ป้องกัน |
| `3` | **Inconclusive** | นัดตรวจซ้ำ 2–4 สัปดาห์ |

---

## Workflow

```
┌──────────────┐
│ ข้อมูลคลินิก │
└──────┬───────┘
       │
       ├─────────────────┐
       ▼                 ▼
┌────────────┐    ┌─────────────────┐
│  RR Form   │    │  VCT + Testing  │
│ (outreach) │    │   (ไฟล์เดียว)   │
└──────┬─────┘    └────────┬────────┘
       │                   │
       │                   │  ครอบคลุม:
       │                   │  • VCT counseling
       │                   │  • Lab request (optional)
       │                   │  • HIV Result (optional)
       │                   │
       └────────┬──────────┘
                ▼
         ┌──────────────┐
         │  NAP Plus    │
         │  (สปสช.)     │
         └──────────────┘
```

---

## เตรียมไฟล์ใน Excel

1. เปิดไฟล์ template ด้วย Excel / Google Sheets
2. กรอกข้อมูลแทนตัวอย่างในแถวที่ 2 เป็นต้นไป (เก็บ header ไว้)
3. Save As → **CSV UTF-8** (สำคัญมาก ถ้าไม่ใช่ UTF-8 จะไม่รองรับภาษาไทย)
4. อัพโหลดผ่าน AutoNAP Dashboard → Upload CSV

## ข้อผิดพลาดที่พบบ่อย

| ปัญหา | วิธีแก้ |
|---|---|
| ภาษาไทยกลายเป็น `???` | Save เป็น CSV **UTF-8** (ไม่ใช่ CSV ธรรมดา) |
| วันที่แปลกๆ เช่น `41732` | Format cell เป็น Text ก่อนพิมพ์วันที่ |
| เลขบัตรหาย 0 ตัวแรก | Format cell เป็น Text ก่อนพิมพ์เลขบัตร |
| Upload error "column missing" | ห้ามลบ/เปลี่ยนชื่อ header |
| เกิน 50 records | แบ่งเป็นหลายไฟล์ |
| ผลตรวจยังไม่มา | ปล่อย `test_date`/`lab_status`/`result` ว่าง → อัพโหลดใหม่ที่ `lab_code` เดิมเมื่อได้ผล |
