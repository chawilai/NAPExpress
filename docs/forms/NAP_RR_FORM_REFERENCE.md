# NAP RR Form Reference — ทุก field และตัวเลือก

อ้างอิงจาก NAP Plus (https://dmis.nhso.go.th/NAPPLUS/) form RR/TTR

---

> **ที่มาของไฟล์นี้:**
> สำเนามาจากโปรเจค **AutoNAP** (`/Users/chawilai/Documents/code/AutoNAP/docs/NAP_RR_FORM_REFERENCE.md`)
> เมื่อวันที่ **2026-04-20** ก่อนปิดโปรเจค AutoNAP
>
> AutoNAP เป็น initial attempt (Node.js + Express stack) ที่ user เริ่มเมื่อ 2026-03-24 ก่อน pivot มาทำ NAPExpress (Laravel) ในวันเดียวกัน เอกสารนี้สร้างขึ้นเมื่อ 2026-03-25 ระหว่างที่กำลัง spec ฟอร์ม RR/TTR
>
> **คุณค่าของเอกสาร:** รวม selectors + KP mapping + occupation codes ของฟอร์ม RR/TTR ในที่เดียว — selectors เหล่านี้ฝังอยู่ใน `automation/thaid_login_and_record.cjs` ของ NAPExpress แล้ว แต่เอกสารนี้อ่านง่ายกว่าเวลา debug หรือเพิ่มฟอร์มใหม่ที่คล้ายกัน
>
> **⚠️ ตรวจสอบกับโค้ดจริง:** ถ้า NAPExpress มีการแก้ selectors หรือ mapping หลัง 2026-03-25 — โค้ดใน `automation/` คือ source of truth ไม่ใช่เอกสารนี้

---

## 1. ข้อมูลพื้นฐาน

| Field | Selector | Type | ตัวอย่าง | หมายเหตุ |
|---|---|---|---|---|
| วันที่บริการ | `rrttrDate` | Text | `02/07/2568` | dd/mm/yyyy พ.ศ. |
| เลขบัตรประชาชน | `pid` | Text | `1199600374276` | 13 หลัก |

---

## 2. พฤติกรรมเสี่ยง (Risk Behavior) — เลือกได้หลายตัว

| Index | Selector | ค่า |
|---|---|---|
| 0 | `#rrttr_risk_behavior_status_0` | TG (คนข้ามเพศ) |
| 1 | `#rrttr_risk_behavior_status_1` | MSM (ชายรักชาย) |
| 2 | `#rrttr_risk_behavior_status_2` | SW (พนักงานบริการ) |
| 3 | `#rrttr_risk_behavior_status_3` | PWID (ฉีดยาเสพติด) |
| 4 | `#rrttr_risk_behavior_status_4` | Migrant (แรงงานข้ามชาติ) |
| 5 | `#rrttr_risk_behavior_status_5` | Prisoner (ผู้ต้องขัง) |

**Mapping จาก KP:**
```
MSM        → [1]
MSW        → [2]
FSW        → [2]
TG/TGW     → [0]
TGM        → [0]
TGSW       → [0]
PWID       → [3]
MIGRANT    → [4]
PRISONER   → [5]
MALE/FEMALE→ [] (ไม่เลือก)
```

---

## 3. กลุ่มเป้าหมาย (Target Group) — เลือกได้หลายตัว

| Index | Selector | ค่า |
|---|---|---|
| 0 | `#rrttr_target_group_status_0` | MSM |
| 1 | `#rrttr_target_group_status_1` | PWID |
| 2 | `#rrttr_target_group_status_2` | ANC (ฝากครรภ์) |
| 3 | `#rrttr_target_group_status_3` | TGW |
| 4 | `#rrttr_target_group_status_4` | PWUD (ใช้สารเสพติดไม่ฉีด) |
| 5 | `#rrttr_target_group_status_5` | เด็กคลอดจากแม่ติดเชื้อ HIV |
| 6 | `#rrttr_target_group_status_6` | TGM |
| 7 | `#rrttr_target_group_status_7` | Partner of KP |
| 8 | `#rrttr_target_group_status_8` | บุคลากรทางการแพทย์ |
| 9 | `#rrttr_target_group_status_9` | TGSW |
| 10 | `#rrttr_target_group_status_10` | Partner of PLHIV |
| 11 | `#rrttr_target_group_status_11` | nPEP |
| 12 | `#rrttr_target_group_status_12` | MSW |
| 13 | `#rrttr_target_group_status_13` | Prisoners |
| 14 | `#rrttr_target_group_status_14` | General Population |
| 15 | `#rrttr_target_group_status_15` | FSW |
| 16 | `#rrttr_target_group_status_16` | Migrant |

**Mapping จาก KP:**
```
MSM        → [0]
MSW        → [12]
FSW        → [15]
TG/TGW     → [3]
TGM        → [6]
TGSW       → [9]
PWID       → [1]
MIGRANT    → [16]
PRISONER   → [13]
MALE/FEMALE→ [14] (General Population)
```

---

## 4. คู่ของ (Partner With) — optional, เลือก 1 ตัว

| Value | Selector | ค่า |
|---|---|---|
| 1 | `#partner_with` option | PWID |
| 2 | | MSM |
| 3 | | TG |
| 4 | | Migrant |
| 5 | | Prisoner |
| 6 | | Youths |
| 7 | | MSW |
| 8 | | FSW |
| 9 | | TGSW |
| 10 | | General Population |

**Default:** `null` (ไม่เลือก)

---

## 5. ช่องทางการเข้าถึง (Access Type) — เลือก 1+ ตัว

| Selector | ค่า |
|---|---|
| `#access_type_1` | ใน DIC |
| `#access_type_2` | นอก DIC (**default**) |
| `#access_type_3` | สื่อสังคมออนไลน์ |

**Default:** `'2'` (นอก DIC)

---

## 6. Social Media — optional, เลือก 1 ตัว (เฉพาะเมื่อเลือก access_type_3)

| Value | ค่า |
|---|---|
| 1 | Facebook |
| 2 | Google |
| 3 | Twitter |
| 4 | Line |
| 5 | Whatsapp |
| 6 | Website |
| 7 | Grindr |
| 8 | Skype |
| 99 | Other |

**Default:** `null`

---

## 7. ที่อยู่ — optional

| Selector | ค่า |
|---|---|
| `#ref_addr` | ที่อยู่ |
| `#ref_province` | จังหวัด (select) |
| `#ref_amphur` | อำเภอ (select) |
| `#ref_tumbon` | ตำบล (select) |
| `#ref_postal` | รหัสไปรษณีย์ |

**Default:** ว่างทั้งหมด

---

## 8. เบอร์โทร / อีเมล

| Selector | ค่า | จาก |
|---|---|---|
| `#ref_tel` | เบอร์โทร | reach.phone / clinic.phone |
| `#ref_email` | อีเมล | ว่าง (optional) |

---

## 9. อาชีพ (Occupation) — เลือก 1 ตัว

| Value | ค่า |
|---|---|
| 01 | ไม่มี/ว่างงาน |
| 02 | เกษตรกร |
| **03** | **รับจ้างทั่วไป (DEFAULT)** |
| 04 | ช่างฝีมือ |
| 05 | เจ้าของกิจการ / ธุรกิจ |
| 06 | ข้าราชการทหาร |
| 07 | นักวิทยาศาสตร์และนักเทคนิก |
| 08 | บุคลากรด้านสาธารณสุข |
| 09 | นักวิชาชีพ/นักวิชาการ |
| 10 | ข้าราชการพลเรือนทั่วไป |
| 11 | พนักงานรัฐวิสาหกิจ |
| 12 | นักบวช/งานด้านศาสนา |
| 13 | อื่น ๆ |
| 14 | ข้าราชการตำรวจ |
| 15 | พนักงาน/ลูกจ้างบริษัท |
| 16 | ค้าขาย |
| 17 | กรรมกร, ผู้ใช้แรงงาน |
| 18 | ลูกจ้างโรงงาน |
| 19 | ขับรถรับจ้าง |
| 20 | นักเรียน/นักศึกษา |
| 21 | รับจ้างทำประมง |
| 22 | ขายบริการทางเพศ |
| 23 | นักแสดง นักร้อง นักดนตรี |
| 24 | พนักงานเสริฟท์ ทำงานบาร์ |
| 25 | เสริมสวย |
| 26 | แม่บ้าน / งานบ้าน |
| 27 | ผู้ต้องขัง |
| 28 | เด็กต่ำกว่าวัยเรียน |
| 29 | ไม่ระบุอาชีพ |

**Mapping จาก text ใน DB:**
```
นักเรียน/นักศึกษา/student     → 01 (หมายเหตุ: NAP ใช้ 20 สำหรับนักเรียน แต่ mapping ปัจจุบันใช้ 01)
ข้าราชการ/government          → 02
รับจ้าง/แรงงาน/general        → 03 (DEFAULT)
พนักงานบริษัท/office          → 04
ค้าขาย/self-employed/ธุรกิจ   → 05
ว่างงาน/unemployed            → 06
```

**⚠️ หมายเหตุ:** Mapping ปัจจุบันใช้ code เก่าซึ่งอาจไม่ตรง NAP 100%:
- "นักเรียน" map เป็น 01 (ไม่มี/ว่างงาน) แต่ NAP จริงคือ 20 (นักเรียน/นักศึกษา)
- "ข้าราชการ" map เป็น 02 (เกษตรกร) แต่ NAP จริงคือ 10 (ข้าราชการพลเรือน)
- "ค้าขาย" map เป็น 05 (เจ้าของกิจการ) แต่ NAP จริงคือ 16 (ค้าขาย)

---

## 10. การให้ความรู้ (Knowledge) — เลือกได้หลายตัว

| Index | Selector | ค่า |
|---|---|---|
| 0 | `#rrttr_knowledge_status_0` | เอชไอวี (HIV) |
| 1 | `#rrttr_knowledge_status_1` | โรคติดต่อทางเพศสัมพันธ์ (STD) |
| 2 | `#rrttr_knowledge_status_2` | วัณโรค (TB) |
| 3 | `#rrttr_knowledge_status_3` | ลดอันตรายจากยาเสพติด (Harm Reduction) |
| 4 | `#rrttr_knowledge_status_4` | ไวรัสตับอักเสบซี (HCV) |

**Default:**
```
ทั่วไป → [0, 1, 2]           (HIV, STD, TB)
PWID   → [0, 1, 2, 3, 4]    (เพิ่ม Harm Reduction + HCV)
```

---

## 11. ข้อมูลสถานที่บริการ (Place) — เลือกได้หลายตัว

| Index | Selector | ค่า |
|---|---|---|
| 0 | `#rrttr_place_status_0` | สถานที่บริการ HIV |
| 1 | `#rrttr_place_status_1` | สถานที่บริการ STD |
| 2 | `#rrttr_place_status_2` | สถานที่บริการวัณโรค (TB) |
| 3 | `#rrttr_place_status_3` | สถานที่บริการเมทาโดน |
| 4 | `#rrttr_place_status_4` | สถานที่บริการ HCV |

**Default:**
```
ทั่วไป → [0, 1, 2]        (HIV, STD, TB)
PWID   → [0, 1, 2, 3]    (เพิ่ม Methadone)
```

---

## 12. อุปกรณ์ป้องกัน (PPE) — เลือกได้หลายตัว

| Index | Selector | ค่า |
|---|---|---|
| 0 | `#rrttr_ppe_status_0` | ถุงยางอนามัยชาย |
| 1 | `#rrttr_ppe_status_1` | ถุงยางอนามัยหญิง |
| 2 | `#rrttr_ppe_status_2` | สารหล่อลื่น |
| 3 | `#rrttr_ppe_status_3` | อุปกรณ์ฉีดยาปลอดเชื้อ |
| 4 | `#rrttr_ppe_status_4` | หน้ากากอนามัย |

**Default:**
```
ทั่วไป → [0, 2]           (ถุงยางชาย + สารหล่อลื่น)
PWID   → [0, 2, 3]       (เพิ่มอุปกรณ์ฉีดยา)
```

---

## 13. จำนวนถุงยาง (Condom) — ต้อง show hidden fields ก่อนกรอก

| Selector | ค่า |
|---|---|
| `#rrttr_condom_amount_49` | ขนาด 49mm |
| `#rrttr_condom_amount_52` | ขนาด 52mm |
| `#rrttr_condom_amount_53` | ขนาด 53mm |
| `#rrttr_condom_amount_54` | ขนาด 54mm |
| `#rrttr_condom_amount_56` | ขนาด 56mm |
| `#rrttr_female_condom_amount` | ถุงยางหญิง |
| `#rrttr_lubricant_amount` | สารหล่อลื่น (ซอง) |

**จากข้อมูลจริง:** ดึงจาก reach.condom49-56, reach.lubricant

---

## 14. หน่วยงานส่งต่อ (Referral)

| Selector | ค่า | จาก |
|---|---|---|
| `#next_hcode` | รหัสหน่วยบริการ | site_specific.sitename14 หรือ default 41936 |
| `#next_hname` | ชื่อหน่วยบริการ | optional |
| `#next_place` | สถานที่ | optional |

---

## 15. ส่งต่อบริการ (Forward Services) — เลือก 1 ตัวต่อประเภท

แต่ละประเภทมี 3 ตัวเลือก:

| Value | ค่า |
|---|---|
| 1 | เจ้าหน้าที่พาไป |
| **2** | **ไปเอง (DEFAULT)** |
| 3 | ไม่ได้ส่งต่อ |

| ประเภท | Selector | Default | เมื่อไหร่เลือก |
|---|---|---|---|
| HIV | `#hiv_forward_1/2/3` | **2** | เลือกเสมอ |
| STI | `#sti_forward_1/2/3` | **2** | เลือกเสมอ |
| TB | `#tb_forward_1/2/3` | **2** | เลือกเสมอ |
| HCV | `#hcv_forward_1/2/3` | `null` | เลือกเมื่อ PWID เท่านั้น |
| Methadone | `#methadone_forward_1/2/3` | `null` | เลือกเมื่อ PWID เท่านั้น |

---

## สรุป: API rr_form Response vs NAP Form

```json
{
  "rrttrDate": "14/02/2569",           // → วันที่ พ.ศ.
  "pid": "1589700023854",              // → เลขบัตร 13 หลัก
  "risk_behavior_indices": [1],        // → checkbox พฤติกรรมเสี่ยง
  "target_group_indices": [0],         // → checkbox กลุ่มเป้าหมาย
  "partner_with": null,                // → dropdown คู่ของ
  "access_type": "2",                  // → checkbox ช่องทาง
  "social_media": null,                // → dropdown social media
  "address": {...},                    // → ที่อยู่ (optional)
  "ref_tel": "0954023226",            // → เบอร์โทร
  "ref_email": "",                     // → อีเมล (optional)
  "occupation": "01",                  // → dropdown อาชีพ
  "knowledge_indices": [0, 1, 2],      // → checkbox ให้ความรู้
  "place_indices": [0, 1, 2],          // → checkbox สถานที่บริการ
  "ppe_indices": [0, 2],              // → checkbox อุปกรณ์ป้องกัน
  "condom": {"49":0,"52":0,...},       // → input จำนวนถุงยาง
  "female_condom": 0,                  // → input ถุงยางหญิง
  "lubricant": 0,                      // → input สารหล่อลื่น
  "next_hcode": "41936",              // → input รหัสหน่วยบริการ
  "forwards": {                        // → checkbox ส่งต่อ (1/2/3)
    "hiv": 2, "sti": 2, "tb": 2,
    "hcv": null, "methadone": null
  }
}
```
