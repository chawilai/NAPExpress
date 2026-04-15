# CPP HIV Ecosystem — 117 เป้าหมายหลัก

ฐานข้อมูลหน่วยบริการที่เกี่ยวข้องกับ HIV ในระบบ สปสช. — กลุ่มเป้าหมายหลักของ AutoNAP

**ไฟล์ข้อมูล:** `docs/research/cpp_hiv_ecosystem.csv` (117 rows, UTF-8 BOM, Excel-ready)

---

## 📊 สรุป 117 หน่วยบริการ

| Entity Type | จำนวน | Registration |
|---|---:|---|
| **Foundation/CBO** (R0216) | 104 | มาตรา 3 HIV community-based |
| **Med-Tech Clinic** (R020602) | 13 | LAB service — paired กับ NGO ข้างบน |
| **รวม** | **117** | |

## 🏢 Breakdown: 104 R0216 ตามนิติบุคคล

| ประเภท | จำนวน | ลักษณะ |
|---|---:|---|
| **กลุ่ม** (Community Groups) | **45** | small CBOs — outreach only |
| **มูลนิธิ** (Foundations) | 20 | large orgs — may have paired clinics |
| **ชมรม** (Clubs/Associations) | 18 | community-level, often PLHIV self-help |
| **สมาคม** (Associations) | 9 | national/regional orgs |
| **เครือข่าย** (Networks) | 3 | coalition/umbrella |
| **คณะทำงาน** (Working Groups) | 1 | coordination body |
| **อื่นๆ** | 8 | mixed/unclassified |

---

## 🎯 NAP Recording Segmentation (สำคัญสำหรับ sales)

### Segment A — "RR Only" (Pure Outreach/Referral)

**63 ชมรม/กลุ่ม** ที่ไม่น่าจะมีห้อง lab ของตัวเอง

ลักษณะงาน:
- Outreach, peer education, ให้ข้อมูล
- ส่งต่อลูกค้าไปตรวจที่คลินิกอื่น
- บันทึก **RR form** เท่านั้น (ข้อมูล outreach)
- ไม่บันทึก VCT, Lab, Result

**Package แนะนำ:** RR Basic ฿790–2,490/เดือน

ตัวอย่าง (จากข้อมูลจริง):
- [G8902] ชมรมรักษ์ม่วง (กาญจนบุรี)
- [G1449] กลุ่ม M.QUEER (ขอนแก่น)
- [G1457] กลุ่มแอ็คทีม ขอนแก่น
- [G8894] กลุ่มดอกหญ้า (ตรัง)
- [G8937] กลุ่มพลังรัก (ชัยภูมิ)
- ...และอีก 58 กลุ่ม

### Segment B — "RR + Testing" (Foundation + Clinic)

**~41 มูลนิธิ/สมาคม** ที่มี clinic paired (หรือทำ testing เอง)

ลักษณะงาน:
- Outreach + **มีคลินิกของตัวเอง** ทำ testing
- บันทึก RR + VCT + Lab + Result ครบวงจร
- Volume สูง

**Package แนะนำ:** Full Suite ฿1,990–14,990/เดือน

**Pattern สำคัญ:** 1 องค์กร = 2 entity แยกใน สปสช.
```
มูลนิธิเอ็มพลัส (R0216) ◄── RR ───┐
                                    ├── ทำงานร่วมกัน
เอ็มพลัสสหคลินิก (R020602) ◄── VCT+Lab+Result ─┘
```

ตัวอย่าง sibling pairs:
| มูลนิธิ (R0216) | คลินิก (R020602) |
|---|---|
| มูลนิธิเอ็มพลัสเชียงใหม่ (F0380) | เอ็มพลัสสหคลินิก (41681) |
| RSAT ชลบุรี (F0409) | คลินิกฟ้าสีรุ้งชลบุรี (41696) |
| มูลนิธิแคร์แมท (G1371) | **Caremat คลินิก (41936)** ← reference |
| SWING ชลบุรี (F0413) | สวิงพัทยา (41592) |
| มูลนิธิเอ็มเฟรนด์อุดร (F0396) | เอ็มเฟรนด์คลินิก (53588) |
| RSAT หาดใหญ่ ... | ฟ้าสีรุ้งหาดใหญ่ (40921) |

---

## 🏥 คำตอบ: คลินิกอื่นที่ทำงานด้าน HIV และบันทึก NAP

### TAM ที่แท้จริง — คลินิกที่บันทึก NAP ได้

**1,228 คลินิก** ที่ต้องบันทึก NAP เพื่อเคลมเงินกับ สปสช.:

| Type Code | Service | Providers |
|---|---|---:|
| R0207 | เวชกรรม (general medicine) | **927** |
| R020602 | LAB ทั่วไป | 285 |
| R0206 | เทคนิคการแพทย์ (general) | 41 |
| R020601 | LAB COVID-19 | 36 |

**Affiliation:**
- เอกชน **1,122** (91%)
- ภาครัฐ 106 (9%)

### ข้อเท็จจริงสำคัญ

**ทุกคลินิกที่มีสัญญากับ สปสช. ต้องบันทึก NAP เพื่อเคลมเงิน** — ไม่ใช่แค่คลินิก HIV

**แต่** — เฉพาะคลินิกที่ทำงานด้าน HIV จะมี pain ของ AutoNAP โดยตรง เพราะ:
1. ต้องบันทึกหลาย form (VCT + Lab + Result + ART)
2. Volume สูง (ตรวจ HIV หลายสิบ/วัน)
3. UIC race condition (เคลมตกหล่นถ้าบันทึกช้า)
4. Staff บันทึกประจำลาออกบ่อย

**คลินิกทั่วไป** ที่ไม่ได้ focus HIV จะบันทึก NAP น้อยกว่า (อาจมีแค่ 10-30 เคส/เดือน) → ROI ของ AutoNAP ต่ำกว่า

---

## 💰 AutoNAP TAM Layered

```
    Layer 1 — Hot prospects (117)
    ├── 63 ชมรม/กลุ่ม         → RR Only package
    ├── 41 มูลนิธิ/สมาคม      → Full Suite package
    └── 13 HIV clinics (paired) → Full Suite package (high value)
    
    Layer 2 — Warm prospects (~200)
    └── Private clinics in HIV-heavy provinces
        ที่ทำ STI/HIV screening ร่วมด้วย
    
    Layer 3 — Cold TAM (~1,100)
    └── General private clinics/labs
        (น้อย HIV pain, ต้อง solve อะไรอื่นแทน)
```

**Sales focus แนะนำ:**
1. **Year 1:** Layer 1 (117 prospects) — ปิด 30-50 sites
2. **Year 2:** Layer 2 (+200 cold-reached via referral)
3. **Year 3:** Layer 3 expansion (partner channel / self-serve)

---

## 🔍 Queries ที่ใช้ได้

### List 117 HIV ecosystem

```sql
SELECT DISTINCT
  p.hcode, p.name, p.phone, p.province,
  CASE
    WHEN p.id IN (SELECT cpp_provider_id FROM cpp_provider_network_types WHERE type_code = 'R0216')
    THEN 'Foundation/CBO'
    ELSE 'Med-Tech Clinic'
  END AS entity_type
FROM cpp_providers p
WHERE p.id IN (SELECT cpp_provider_id FROM cpp_provider_network_types WHERE type_code = 'R0216')
   OR (p.name REGEXP 'ฟ้าสีรุ้ง|เอ็มพลัส|แคร์แมท|สวิง|ซิสเตอร์|เอ็มเฟรนด์'
       AND p.id IN (SELECT cpp_provider_id FROM cpp_provider_network_types WHERE type_code LIKE 'R0206%'))
ORDER BY p.province, p.name;
```

### List pure RR-only candidates (63 ชมรม/กลุ่ม)

```sql
SELECT p.hcode, p.name, p.phone, p.province, p.district
FROM cpp_providers p
JOIN cpp_provider_network_types n ON n.cpp_provider_id = p.id
WHERE n.type_code = 'R0216'
  AND (p.name LIKE 'ชมรม%' OR p.name LIKE 'กลุ่ม%')
ORDER BY p.province, p.name;
```

### List RR + Testing candidates (foundations with clinic siblings)

```sql
SELECT DISTINCT
  f.hcode AS foundation_hcode, f.name AS foundation_name, f.province,
  c.hcode AS clinic_hcode, c.name AS clinic_name
FROM cpp_providers f
JOIN cpp_provider_network_types nf ON nf.cpp_provider_id = f.id
LEFT JOIN cpp_providers c ON c.province = f.province
  AND c.name REGEXP REPLACE(REPLACE(f.name, 'มูลนิธิ', ''), 'สมาคม', '')
  AND c.id IN (SELECT cpp_provider_id FROM cpp_provider_network_types WHERE type_code LIKE 'R0206%')
WHERE nf.type_code = 'R0216'
  AND f.name REGEXP 'มูลนิธิ|สมาคม'
ORDER BY f.province;
```

---

## 🗂️ ไฟล์ที่เกี่ยวข้อง

- **`cpp_hiv_ecosystem.csv`** — 117 rows ครบทุกฟิลด์ (main deliverable)
- `cpp_hiv_providers.csv` — 104 R0216 only (เดิม)
- `hiv_providers_merged.md` — analysis merge กับ old research
