# AutoNAP — แผนการตลาดและการเข้าสู่ตลาด (Go-to-Market Plan)

> **เอกสารนำเสนอนักลงทุน / Business Case**
> เวอร์ชัน: 1.0 — เมษายน 2569 (2026)
> สถานะระบบ: Production v1.5 (ใช้งานจริงแล้ว 5 ศูนย์)

---

## 1. Executive Summary

**AutoNAP** คือระบบ automation สำหรับบันทึกข้อมูลผู้รับบริการเข้าระบบ **NAP Plus ของ สปสช.** แทนพนักงานบันทึกข้อมูล (Data Entry Staff) โดยลดเวลาการบันทึกจาก **1–3 นาที/เคส เหลือประมาณ 10–20 วินาที/เคส** (เร็วขึ้น ~6–10 เท่า) และลดความผิดพลาดจากการพิมพ์มือ

### ปัญหาที่แก้ (Problem)
1. **บันทึกช้า** — คลินิก/ศูนย์บริการภายใต้ สปสช. ต้องบันทึกหลายฟอร์ม (RR, VCT, Lab, HIV Result, STI) เฉลี่ย 1–3 นาที/เคส
2. **ค่าแรงสูง** — แต่ละศูนย์จ้างพนักงานบันทึกประจำ **1–2 คน** เงินเดือน 12,000–18,000 บาท/คน/เดือน
3. **ตกเงิน Reimbursement** — บันทึกเลตเกิน deadline = เคลมเงินจากภาครัฐไม่ได้ (loss จริง)
4. **ชิงบันทึกไม่ทัน** — ถ้าศูนย์อื่นบันทึก UIC เดียวกันก่อน = เคลมไม่ได้เลย (first-come-first-served)
5. **Human Error** — พิมพ์ผิด รหัสโรค/รหัสบริการผิด = reject

### ทางออก (Solution)
ระบบ **AI อัตโนมัติจาก AutoNAP** ที่รับข้อมูลจากฐานข้อมูลคลินิก (เช่น CAREMAT) แล้วบันทึกลง NAP Plus ให้อัตโนมัติ พร้อม:
- Dashboard real-time ดูสถานะทุกเคส
- Batch processing สูงสุด 50 records/request
- Auto-retry + crash recovery
- Email report ทุกงาน
- Callback เชื่อมกลับระบบต้นทาง
- รองรับหลาย site พร้อมกัน (Multi-tenant)

### Traction ปัจจุบัน (Proof Points)
| Metric | Value |
|---|---|
| ศูนย์ที่ใช้จริง (Production) | **5 sites** |
| ฟอร์มที่รองรับ | RR, VCT, Lab, HIV Result |
| ความสามารถประมวลผลพร้อมกัน | รองรับ 100+ เคส/ชม. |
| Uptime | v1.5 stable, crash auto-recovery |
| เคสที่บันทึกแล้ว (โดยประมาณ) | 1,000+ เคส/เดือน |

---

## 2. Market Analysis — ขนาดตลาดจริง

### 2.1 TAM / SAM / SOM (ตลาดเป้าหมาย)

**Total Addressable Market (TAM)**
- หน่วยบริการภายใต้ สปสช. ที่ต้องบันทึก NAP ประมาณ **8,000–12,000 แห่งทั่วประเทศ** (รวม รพ.รัฐ, รพ.เอกชน, คลินิกชุมชนอบอุ่น, ศูนย์ NGO, DIC, Drop-in Center)
- ฟอร์มหลัก: RR (Risk Reduction), VCT (Voluntary Counseling & Testing), PrEP, ART, STI, TB

**Serviceable Addressable Market (SAM) — โฟกัสแรก**
- คลินิก/ศูนย์ NGO ที่ทำงาน **HIV/STI prevention** ภายใต้ สปสช. และต้อง report หลายฟอร์ม
- ประมาณ **500–800 แห่ง** (MPlus, SWING, Rainbow Sky, SISTERS, Caremat, RSAT, มูลนิธิรักษ์ไทย, etc.)
- รวมถึงคลินิกในเครือภาคีเอกชนที่ใช้ระบบจัดการผู้ป่วยอย่าง CAREMAT, HIVQual, SmartClinic

**Serviceable Obtainable Market (SOM) — 18 เดือนแรก**
- เป้าหมาย **80–120 sites** (10–15% ของ SAM)
- คาดการณ์จาก network effect ของ 5 sites ที่ใช้งานอยู่ (MPlus 3 สาขา + RSAT + Namkwan)

### 2.2 Cost ปัจจุบันของลูกค้า (Pain in Baht)

**กรณีศูนย์ขนาดกลาง** (เคส ~800 ราย/เดือน, 2 ฟอร์ม/เคส)
| รายการ | ค่าใช้จ่ายเดิม/เดือน |
|---|---|
| พนักงานบันทึก 1 คน (ค่าจ้าง + ประกันสังคม) | 15,000–20,000 บาท |
| เวลาที่พยาบาล/เจ้าหน้าที่หลักเสียไปกับการบันทึก | 8,000–12,000 บาท (opportunity cost) |
| เคสบันทึกเลต → ตกเงินเคลม | 3,000–8,000 บาท |
| เคสที่โดน site อื่นชิงไปก่อน | 2,000–5,000 บาท |
| **รวม Pain/เดือน** | **28,000–45,000 บาท** |

**กรณีศูนย์ขนาดใหญ่** (2,000+ เคส/เดือน, จ้างพนักงาน 2 คน)
- Pain/เดือน ประมาณ **50,000–80,000 บาท**

→ **นี่คือ "ceiling" ของราคาที่ลูกค้ายอมจ่าย** — AutoNAP ต้องตั้งราคาให้ ROI ชัดเจน (ประหยัดอย่างน้อย 50%)

### 2.3 Competitive Landscape

| คู่แข่ง | จุดแข็ง | จุดอ่อน |
|---|---|---|
| **พนักงานบันทึกประจำ (Status quo)** | คนรู้จัก, ตรวจงานได้ | ช้า, แพง, ลาออกบ่อย, human error |
| **Outsource data entry** | ถูกกว่าจ้างเอง | ไม่ real-time, ความปลอดภัยข้อมูล |
| **RPA generic (UiPath, Power Automate)** | ยืดหยุ่น | ต้องจ้าง dev, ไม่มี domain knowledge NAP |
| **คู่แข่งตรง (NAP automation)** | — | **ยังไม่มี** ในตลาดไทย (blue ocean) |

> **Insight:** AutoNAP อยู่ในตำแหน่ง **first mover** สำหรับ vertical SaaS เฉพาะทาง NAP Plus

### 2.4 Moat — ทำไม "ไม่ใช่ใครๆ ก็ทำได้"

คำถามที่พบบ่อย: *"นี่ไม่ใช่แค่ automate ฟอร์มเหรอ? ใครๆ ก็ทำได้ไม่ใช่เหรอ?"*

**คำตอบสั้น: ไม่** — ที่ดูเหมือนง่ายเพราะปัจจุบันมัน "ทำงานได้" ซึ่งเป็นผลจากการเจ็บตัวมาหลายสิบรอบ ความยากจริงไม่ได้อยู่ที่ "กดปุ่มแทนคน" แต่อยู่ที่ 5 ชั้นลึกซ่อนใต้ผิวน้ำ:

#### ชั้นที่ 1 — ความรู้เชิงลึกของระบบ NAP Plus (Tacit Knowledge)
- NAP Plus **ไม่มี public documentation** — ทุกอย่างเรียนรู้จากการลองผิดลองถูก
- UI/validation เปลี่ยนเงียบๆ (ไม่ประกาศ) — ต้องมี monitoring + ปรับรับการเปลี่ยนแปลง
- Sub-option ที่ซ่อนอยู่ (เช่น field ที่โผล่มาเฉพาะบางเงื่อนไข) — ต้องเคยเจอจริงถึงรู้
- SSO/session redirect quirks — ต้องจัดการเฉพาะจุด ไม่งั้นหลุดกลางเคส

#### ชั้นที่ 2 — Domain Knowledge ด้าน NHSO / การเคลม
- รู้ว่า **เคสไหนกรอกฟอร์มไหน** (RR, VCT, Lab, HIV Result, STI, PrEP, ART) และ **ลำดับ** ที่ถูกต้อง
- รู้ **กติกา UIC** — ใครชิงบันทึกก่อนได้เคลม → ระบบต้องจัดลำดับความสำคัญให้
- รู้ **deadline การเคลม** ของแต่ละโครงการ
- รู้ว่า mapping ข้อมูลจากคลินิก → ฟอร์ม NAP ต้องแปลงค่ายังไง (เช่น occupation, KP, service location)

> **ข้อนี้ไม่ใช่ทักษะโปรแกรมเมอร์** — ต้องเคยทำงานสาธารณสุขหรืออยู่ใกล้ชิดกับคนทำ

#### ชั้นที่ 3 — Production-hardening จาก Edge Cases จริง
สิ่งที่ prototype "เดโม่แล้วใช้งานจริงไม่ได้" มักเจอ:
- **Session timeout** ระหว่างกรอก 50 เคส — ต้อง detect + re-login + resume
- **Process crash** กลาง batch — ต้อง auto-recover + retry record ที่ค้าง (max N ครั้งเพื่อไม่ loop)
- **Batch restart** ทุก N records เพื่อกัน memory leak
- **Rate limiting** ฝั่ง NAP — เร็วไปจะโดน silent fail
- **Data stripping** — ฟอร์มบางอันตัดข้อมูลออกเงียบๆ ตอน validate ต้อง workaround
- **Queue timing** — retry_after / job timeout ต้องสัมพันธ์กันไม่งั้นงานซ้ำหรือหาย

แต่ละข้อข้างต้น AutoNAP **เจอจริง แก้จริง** จาก 5 sites production — คู่แข่งต้องเจ็บซ้ำเองทั้งหมด

#### ชั้นที่ 4 — Reliability ระดับ Mission-Critical
คลินิกไม่ทนระบบที่ "บางทีใช้ได้ บางทีใช้ไม่ได้" เพราะ **ตกเงินเคลมจริง** AutoNAP มี:
- Dashboard real-time ทุก worker/queue
- Email report ทุกงาน
- Callback กลับระบบต้นทางพร้อมสถานะ
- Error ภาษาไทยที่คนใช้เข้าใจได้ (ไม่ใช่ stack trace)
- History ย้อนหลังตรวจสอบได้

> **Reliability = trust. Trust = retention.** คู่แข่งใหม่ที่ยังไม่มี production track record จะขายยากมาก

#### ชั้นที่ 5 — Integration Network
- CAREMAT integration ใช้งานจริงอยู่แล้ว
- Pipeline สำหรับสร้าง connector ใหม่เร็ว (HIVQual, SmartClinic, HOSxP กำลังตามมา)
- ทุก site ใหม่ที่ onboard = edge case ใหม่ที่กลายเป็น moat เพิ่มขึ้น → **compound advantage**

### สรุป: ใครๆ ก็ทำ "โปรโตไทป์" ได้ แต่ production-grade NAP automation ต้องใช้เวลา 12–18 เดือนและ 3–5 sites จริงกว่าจะเสถียร AutoNAP เริ่มมาแล้วและอยู่ข้างหน้า

---

## 3. กลุ่มเป้าหมาย (Target Segments)

### Tier 1 — Early Adopters (เดือน 1–6)
**"ศูนย์ NGO ที่มีเคสเยอะและ tech-savvy"**

| Profile | ตัวอย่าง |
|---|---|
| ศูนย์บริการ HIV/STI prevention ภาคี สปสช. | MPlus, RSAT, SWING, Rainbow Sky, SISTERS, Caremat |
| เคส 500+ ราย/เดือน | — |
| ใช้ระบบจัดการผู้ป่วยออนไลน์อยู่แล้ว | CAREMAT, HIVQual |
| มี IT coordinator หรือ manager ที่เข้าใจ automation | — |

**ทำไม Tier นี้ก่อน?**
- ROI ชัด (เคสเยอะ = ประหยัดเยอะ)
- Pain สูงสุด (พนักงานบันทึกไม่พอ)
- มี champion ในองค์กรที่พร้อมทดลอง
- พร้อม integrate กับ CAREMAT (เรามี integration อยู่แล้ว)

### Tier 2 — Mainstream (เดือน 6–12)
- คลินิกชุมชนอบอุ่นขนาดกลาง
- ศูนย์บริการจังหวัด/อำเภอภายใต้ PCU
- คลินิกเอกชนที่รับงาน สปสช.

### Tier 3 — Enterprise (ปีที่ 2)
- รพ.รัฐขนาดกลาง-ใหญ่ที่ต้องบันทึก ART/PrEP/TB หลายร้อยเคส/วัน
- เครือข่ายรพ.เอกชนที่รับงาน สปสช.

### 3.4 Integration Options — รองรับศูนย์ที่ไม่ได้ใช้ CAREMAT

คำถามสำคัญ: *"เราไม่ได้ใช้ CAREMAT จะใช้ AutoNAP ยังไง?"*

AutoNAP ออกแบบให้ **input-agnostic** — ไม่บังคับว่าข้อมูลต้องมาจากไหน รองรับ 5 ช่องทาง ครอบคลุมทุกขนาดและทุกระดับของ tech-savvy:

| # | ช่องทาง | เหมาะกับ | ความซับซ้อนการเริ่มใช้ |
|---|---|---|---|
| 1 | **CSV / Excel Upload** | ศูนย์ที่มีข้อมูลในไฟล์ Excel อยู่แล้ว (ทุกที่) | ⭐ ต่ำมาก — แค่ download template, กรอก, อัพโหลด |
| 2 | **Web Form บน AutoNAP** | ศูนย์ที่ไม่มีระบบเลย / คีย์ทีละเคส | ⭐ ต่ำ — กรอกครั้งเดียว ระบบบันทึกเข้า NAP หลายฟอร์มให้ |
| 3 | **Google Sheets Sync** | ทีมที่ทำงานร่วมกันใน sheet | ⭐⭐ ต่ำ — แชร์ sheet ตาม template |
| 4 | **Open API / Webhook** | ศูนย์ที่มีนักพัฒนาหรือใช้ระบบ custom | ⭐⭐⭐ กลาง — เอกสาร API ให้ integrate เอง |
| 5 | **Custom Connector** (Pro/Enterprise) | เครือข่าย รพ./คลินิกที่ใช้ HOSxP, JHCIS, SmartClinic, HIVQual, etc. | ⭐⭐⭐⭐ AutoNAP ทำให้ — คิดค่า setup ครั้งเดียว |

#### ทำไมยังคุ้มแม้ใช้แค่ CSV Upload?

หลายคนคิดว่า "ถ้าต้องคีย์ข้อมูลลง Excel เองอยู่แล้ว จะใช้ AutoNAP ทำไม?" — คำตอบคือ **NAP Plus เองนั่นแหละคือปัญหา** ไม่ใช่การคีย์ข้อมูล:

| กระบวนการ | ใช้พนักงานบันทึก | ใช้ AutoNAP (CSV) |
|---|---|---|
| เตรียมข้อมูล (Excel/จดบันทึก) | 20 นาที/50 เคส | 20 นาที/50 เคส *(เท่ากัน)* |
| เข้าระบบ NAP + กรอกทีละเคส | **150 นาที** (3 นาที × 50) | **10 นาที** (upload + รอ) |
| แก้ error / reject | 20 นาที | 5 นาที (error ภาษาไทย + retry อัตโนมัติ) |
| **รวม 50 เคส** | **~190 นาที** | **~35 นาที** |

→ **เร็วขึ้น ~5.4 เท่า** แม้ไม่มีระบบคลินิกอัตโนมัติใดๆ

#### Onboarding Path สำหรับศูนย์ที่ไม่มีระบบ

1. **Day 1** — สมัคร + download CSV template (รองรับทุกฟอร์ม)
2. **Day 1–3** — เจ้าหน้าที่กรอก Excel แบบเดิม แต่ใช้ template ของ AutoNAP
3. **Day 3** — Upload batch แรก → AutoNAP บันทึกให้
4. **Week 2** — ดู report, ปรับกระบวนการภายใน
5. **เดือนที่ 2+** — อยากได้ realtime? → ย้ายมาใช้ Web Form หรือคุยเรื่อง custom integration

> **Insight สำหรับการขาย:** "CSV path" คือ **Trojan horse** — เข้าตลาดง่าย ไม่ต้อง IT, ลูกค้าเห็น value ทันที แล้วค่อย upsell เป็น integration ลึกขึ้นทีหลัง

### Persona หลัก — "คุณเอ๋ ผู้จัดการคลินิก"
> อายุ 35–45, จบสาธารณสุข/พยาบาล, ดูแลคลินิก NGO 1 สาขา, เคสเฉลี่ย 800/เดือน, จ้างพนักงานบันทึก 1 คน, เจ็บใจทุกเดือนเวลาเคลมตกหล่น, อยากให้พยาบาลมีเวลาดูแลผู้รับบริการมากกว่ามานั่งพิมพ์

**Pain หลักของเอ๋:**
1. พนักงานบันทึกลาออก → หาคนใหม่ยาก 2–3 เดือน
2. เคสค้างเมื่อเดือน → ต้องให้พยาบาลมาช่วยบันทึก = บริการตกคุณภาพ
3. โดนชิง UIC บ่อย

---

## 4. Pricing Strategy — โครงสร้างราคา

### 4.1 หลักการตั้งราคา
- **Value-based + Usage-scaled** — base fee ต่อเดือน + quota เคสที่รวมอยู่ + overage สำหรับเคสเกิน (แบบเดียวกับ Twilio, SendGrid)
- **Predictable base** — ลูกค้ารู้ต้นทุนคงที่ต่อเดือน ไม่ anxious เรื่องบิล
- **Scales with value** — site ใหญ่จ่ายมากกว่า site เล็ก (fair)
- **Free trial 14–30 วัน** — ให้ลูกค้าเห็น value ก่อนจ่าย
- **Hybrid Tiered** ไม่ใช่ Pure Token Credit — เหตุผลใน §4.6

### 4.2 Pricing Tiers (List Price)

#### Full Suite (RR + Testing — VCT + Lab + HIV Result)

| Tier | ค่าบริการ/เดือน | Quota | เคสเกินโควต้า | ช่วง volume |
|---|---|---|---|---|
| **Starter** | **฿1,990** | 500 เคส | ฿4/เคส | < 500 เคส/เดือน |
| **Growth** ⭐ | **฿3,990** | 1,500 เคส | ฿3/เคส | 500–1,500 |
| **Scale** | **฿7,990** | 3,000 เคส | ฿2.5/เคส | 1,500–3,000 |
| **Enterprise** | **฿14,990** | 6,000 เคส | ฿2/เคส | 3,000+ |

#### RR Basic (RR Only)

| Tier | ค่าบริการ/เดือน | Quota | Overage |
|---|---|---|---|
| **Starter** | **฿790** | 300 เคส | ฿2/เคส |
| **Growth** | **฿1,490** | 800 เคส | ฿1.5/เคส |
| **Scale** | **฿2,490** | 1,500 เคส | ฿1/เคส |

> **Founding discount 50% off** ล็อค 12 เดือน (รายละเอียด §4.5)

### 4.3 ตัวอย่างคำนวณ ROI (Growth Full Suite)

```
กรณีศูนย์ NGO ขนาดกลาง — ~1,000 เคส/เดือน RR+Testing

ต้นทุนเดิม:
  พนักงานบันทึก 1 คน (ค่าจ้าง + ประกันสังคม)  ~฿18,000
  Opportunity cost (พยาบาลช่วยบันทึกบ้าง)     ~฿6,000
  เคลมตก/โดนชิง UIC                            ~฿4,000
  รวม Pain                                     ~฿28,000/เดือน

ต้นทุนใหม่ (AutoNAP Growth @ list price):
  ฿3,990/เดือน

ประหยัด:   ฿24,010/เดือน = ฿288,120/ปี
ROI:       602% ต่อเดือน
Payback:   < 1 สัปดาห์
```

**Selling point:** *"จ่ายเราเท่าค่าแรงพนักงาน 6 วัน — ประหยัดเงินเดือนทั้งเดือน"*

### 4.4 Mapping ตัวอย่าง 17 Sites ปัจจุบัน → Tier

อ้างอิงจากข้อมูลใช้งานจริง ม.ค.–มี.ค. 2026 (เฉลี่ย 3 เดือน):

#### RR + Testing (13 sites) — รวม ~13,614 เคส/เดือน

| # | Site | เฉลี่ย/ด. | Tier | Founding (50%) | List |
|---|---|---:|---|---:|---:|
| 1 | **rsat_bkk** 👑 | 3,866 | Enterprise | ฿7,495 | ฿14,990 |
| 2 | mplus_cmi | 1,617 | Scale | ฿3,995 | ฿7,990 |
| 3 | mplus_nma | 1,005 | Growth | ฿1,995 | ฿3,990 |
| 4 | mplus_plk | 972 | Growth | ฿1,995 | ฿3,990 |
| 5 | caremat | 881 | Growth | ฿1,995 | ฿3,990 |
| 6 | rsat_ubn | 848 | Growth | ฿1,995 | ฿3,990 |
| 7 | rsat_cbi | 697 | Growth | ฿1,995 | ฿3,990 |
| 8 | rsat_nsn | 697 | Growth | ฿1,995 | ฿3,990 |
| 9 | mplus_cri | 659 | Growth | ฿1,995 | ฿3,990 |
| 10 | rsat_npt | 657 | Growth | ฿1,995 | ฿3,990 |
| 11 | rsat_pte | 611 | Growth | ฿1,995 | ฿3,990 |
| 12 | rsat_ska | 593 | Growth | ฿1,995 | ฿3,990 |
| 13 | mplus_lpg | 512 | Starter | ฿995 | ฿1,990 |
| | **รวม** | **13,614** | | **฿30,440** | **฿60,880** |

#### RR Only (4 sites) — รวม ~1,950 เคส/เดือน

| # | Site | เฉลี่ย/ด. | Tier | Founding | List |
|---|---|---:|---|---:|---:|
| 1 | namkwan | 792 | Growth | ฿745 | ฿1,490 |
| 2 | vcap | 717 | Growth | ฿745 | ฿1,490 |
| 3 | hugfang | 378 | Starter | ฿395 | ฿790 |
| 4 | mplus_bkk | 64 | Pay-as-you-go* | ~฿192 | ฿3/เคส |
| | **รวม** | **1,950** | | **~฿2,077** | **~฿3,962** |

\* mplus_bkk volume ต่ำมาก (อาจเพิ่งเริ่มหรือกำลังเลิกใช้) → ให้ทางเลือก Pay-as-you-go ฿3/เคส แทน base fee

**รวม MRR potential ถ้าทุก site จ่าย:**
- Founding (ปีแรก): **~฿32,517/เดือน → ARR ~฿390,000**
- List (หลังปีแรก): **~฿64,842/เดือน → ARR ~฿778,000**

### 4.5 Founding Members Offer (สำหรับ 16 sites ที่ใช้งานจริง)

**ข้อเสนอ — ปิดรับ 31 พ.ค. 2569:**
- ล็อคราคา **50% off** เป็นเวลา 12 เดือน
- Onboarding + training ฟรี
- Badge "Founding Member" + ชื่อใน customer wall
- Priority support ตลอดไปแม้ยกเลิกแล้วกลับมา
- **ไม่คิด overage ในปีแรก** (ราคาคงที่ตลอด 12 เดือน — ลด friction)
- เมื่อหมดช่วง Founding → ขยับเป็น list price แบบอัตโนมัติ (แจ้งล่วงหน้า 60 วัน)

**Pay-upfront bonus:**
- จ่ายล่วงหน้า 3 เดือน = ฟรี 1 เดือน (รวม 4 เดือน)
- จ่ายล่วงหน้า 12 เดือน = ฟรี 2 เดือน (รวม 14 เดือน) + lock ราคาถึงเดือนที่ 18

**Cash potential:** ถ้า 10/16 sites จ่ายล่วงหน้า 3 เดือน
→ เฉลี่ย ~฿2,000 × 10 × 3 = **~฿60,000 cash ก้อนแรก** (immediate runway)

### 4.6 ทำไมไม่ใช้ Pure Token Credit

แม้ token credit จะ "fair ที่สุด" แต่ Thai B2B มีปัญหาเฉพาะ:

| ปัญหา | ผลกระทบ |
|---|---|
| **Budget anxiety** | ผู้จัดการตอบหัวหน้าไม่ได้ว่าเดือนนี้จ่ายเท่าไร → ไม่อนุมัติ |
| **Self-throttling** | ลูกค้ากลัวบิลบาน → ไม่กล้าใช้ → value ต่ำ → churn |
| **ระบบจัดซื้อ NGO/ราชการ** | ต้องการ "ราคาคงที่ต่อเดือน" เพื่อลงบัญชีและทำเบิก |
| **ขายยาก** | ไม่มีตัวเลขที่พูดสั้นๆ ได้ในการ pitch |

**แต่ใช้ Credit Pack เป็น add-on** ในกรณี:
- ลูกค้ามี seasonal spike / campaign ใหญ่
- Top-up สำหรับเคสเกินโควตาชั่วคราว
- Trial 30 วัน = 100 credits ฟรี

### 4.7 Credit Pack Add-on (ของเสริม)

| Pack | ราคา | Credits | ต่อเคส | หมดอายุ |
|---|---|---:|---:|---|
| **Small** | ฿1,500 | 500 | ฿3.00 | 6 เดือน |
| **Medium** | ฿5,000 | 2,000 | ฿2.50 | 6 เดือน |
| **Large** | ฿10,000 | 5,000 | ฿2.00 | 12 เดือน |

Credits ใช้แทน overage → ลูกค้า stock ไว้ล่วงหน้าได้

### 4.8 Commission Model (Channel Partners)

สำหรับ **channel partner** (เช่น CAREMAT, HIVQual, vendor ระบบคลินิก):
- **Referral commission:** 15–20% ของค่าธรรมเนียม 12 เดือนแรก
- **Reseller commission:** 25–30% (partner จัดการ onboarding + support tier 1 เอง)
- **White-label:** negotiable (minimum revenue guarantee)

สำหรับลูกค้าที่แนะนำต่อ (refer a friend):
- ลูกค้าเก่าได้ส่วนลด 1 เดือน (Full Suite), 0.5 เดือน (RR Basic)
- ลูกค้าใหม่ได้ทดลองใช้ 30 วัน (แทน 14 วัน)

---

## 5. Go-to-Market Playbook — เริ่มยังไง

### Phase 0 — Foundation (เดือน 0) **[กำลังทำอยู่]**
- ✅ ระบบ production-ready (v1.5)
- ✅ 5 sites reference customers
- ⬜ Landing page + demo video
- ⬜ Case study จาก MPlus CMI (เคสเยอะสุด)
- ⬜ Pricing page + ระบบ self-serve signup

### Phase 1 — Beachhead (เดือน 1–3) "10 sites แรก"
**เป้าหมาย:** 10 paying customers, ARR ฿600,000

**ช่องทาง:**
1. **Warm outreach** ใช้ network เดิม — MPlus, RSAT, SWING, Rainbow Sky, SISTERS (คุยตรงกับผู้จัดการ)
2. **CAREMAT co-marketing** — ศูนย์ที่ใช้ CAREMAT อยู่แล้ว integrate ง่ายมาก
3. **Referral จาก 5 sites ปัจจุบัน** — ขอคำแนะนำไปยังศูนย์พี่น้อง
4. **Soft launch webinar** — 1 ครั้ง/เดือน demo ระบบ + Q&A

**ราคาพิเศษ Founding Customers:**
- ล็อคราคา Growth เหลือ **฿2,990/เดือน ตลอด 12 เดือน** (40% discount)
- Onboarding ฟรี
- ขอใช้ชื่อเป็น reference customer

### Phase 2 — Expansion (เดือน 4–9) "50 sites"
**เป้าหมาย:** 50 paying customers, ARR ฿3,000,000

**ช่องทาง:**
1. **Content marketing** — บทความใน Facebook Group สาธารณสุข, Line OA วิชาชีพ
   - "5 เทคนิคลดเวลาบันทึก NAP ให้ทันเคลม"
   - "เคสศึกษา: MPlus CMI ประหยัด 260,000 บาท/ปี ด้วย AutoNAP"
2. **ประชุมวิชาการ/สัมมนา** — บูธในงาน HIV/STI Forum, สปสช. forum, NGO coalition meeting
3. **Direct sales** — จ้าง AE 1 คน focus ศูนย์ขนาดกลาง
4. **Partner channel** — ทำ integration กับ HIVQual, SmartClinic แล้วแบ่ง commission

### Phase 3 — Scale (เดือน 10–18) "120+ sites"
**เป้าหมาย:** 120+ customers, ARR ฿8–12M

**ช่องทาง:**
1. **Inbound marketing** — SEO บน Google ("บันทึก NAP", "ระบบบันทึก สปสช.")
2. **Enterprise sales** — เจาะ รพ.รัฐ, เครือข่ายรพ.เอกชน
3. **Self-serve signup** — ลด CAC ด้วย landing page + onboarding อัตโนมัติ
4. **Regional expansion** — ภาคอีสาน, ใต้ (ปัจจุบันเน้นเหนือ)

### 5 Channels ที่ควรใช้ — เรียงลำดับ ROI

| Channel | Cost | Speed | ROI | Phase |
|---|---|---|---|---|
| 1. Warm referral จาก reference customers | ต่ำมาก | เร็วมาก | สูงสุด | 1–2 |
| 2. Co-marketing กับ CAREMAT/HIVQual | ต่ำ | เร็ว | สูง | 1–3 |
| 3. Content + Case study | กลาง | กลาง | สูง | 2–3 |
| 4. ประชุมวิชาการ/สัมมนา | กลาง-สูง | กลาง | กลาง | 2 |
| 5. Direct sales (AE) | สูง | กลาง | กลาง | 2–3 |

---

## 6. Promotion & Launch Tactics

### 6.1 Launch Offer (Founding 20)
- 20 ศูนย์แรก: **ล็อคราคา 12 เดือน** + **onboarding ฟรี** + **badge "Founding Member"** บน dashboard
- Deadline-driven: "ปิดรับ 30 มิ.ย. 2569"

### 6.2 Seasonal Promotions
| ช่วง | Promotion | เหตุผล |
|---|---|---|
| **ต้นปีงบประมาณ (ต.ค.)** | Free month + audit ฟรี 1 รอบ | ลูกค้า plan budget ใหม่ |
| **ปลายปีงบฯ (ก.ย.)** | Pay annually → ลด 20% | คลินิกรีบใช้งบก่อนหมด |
| **ช่วง peak เคส (หลังสงกรานต์)** | +50% quota ฟรี 1 เดือน | เคสเยอะ ลูกค้ารู้สึก value |
| **Refer-a-friend ต่อเนื่อง** | ลด 1 เดือนทุกคนที่แนะนำ (ไม่จำกัด) | viral growth |

### 6.3 Content Ideas
- **Case study video** — "1 ชั่วโมงบันทึก 50 เคส (จากเดิม 2 ชั่วโมง)"
- **ROI Calculator** บน landing page — ใส่เคส/เดือน → โชว์ประหยัดเท่าไร
- **Live demo** ทุกวันอังคาร 14:00 (Zoom)
- **Whitepaper** — "NAP Data Entry Bottleneck in Thai Community Clinics"

### 6.4 Trust Signals
- Logo 5 sites ปัจจุบันบน landing page (ขอ permission ก่อน)
- Testimonial จากผู้จัดการ MPlus CMI
- Badge "PDPA Compliant" + "ข้อมูลผู้ป่วยเก็บในประเทศไทย"
- อ้างอิงตัวเลข uptime จริง

---

## 7. Revenue Projection (18 เดือน)

### 7.1 สมมติฐาน
- Churn 3%/เดือน (sticky product — integration ลึก + switching cost สูง)
- ARPU เฉลี่ย ~฿2,900/เดือน ช่วง Founding, ขยับเป็น ~฿4,200 หลัง 12 เดือน
- CAC เฉลี่ย ฿6,000/ลูกค้า (mix ของ referral + content + direct)

### 7.2 Base Case — เริ่มจาก 17 sites ในมือ

| เดือน | Event | Customers | MRR (บาท) | ARR (บาท) |
|---|---|---|---|---|
| 1 | Founding launch (70% convert จาก 17) | 12 | 22,600 | 271,200 |
| 3 | +5 sites ใหม่ | 17 | 32,500 | 390,000 |
| 6 | +10 sites ใหม่ | 27 | 58,000 | 696,000 |
| 9 | Expansion ผ่าน CAREMAT partner | 45 | 105,000 | 1,260,000 |
| 12 | Founding lock เริ่มหมดบางราย → list price | 65 | 180,000 | 2,160,000 |
| 18 | Content + partner scale | 100 | 320,000 | **3,840,000** |

### 7.3 Optimistic Case — Viral via referral

| เดือน | Customers | MRR | ARR |
|---|---|---|---|
| 6 | 35 | 78,000 | 936,000 |
| 12 | 90 | 260,000 | 3,120,000 |
| 18 | 140 | 480,000 | **5,760,000** |

### 7.4 Conservative Case — Churn สูงกว่าคาด

| เดือน | Customers | MRR | ARR |
|---|---|---|---|
| 6 | 20 | 42,000 | 504,000 |
| 12 | 45 | 120,000 | 1,440,000 |
| 18 | 70 | 210,000 | **2,520,000** |

### 7.5 Unit Economics

- **LTV เฉลี่ย:** ฿4,200 × 33 เดือน (churn 3%) = **฿138,600**
- **LTV:CAC ratio:** 23x (เกณฑ์ดี > 3x)
- **Gross margin:** ~78–82% (infra + support + amortized CAC)
- **Payback period:** ~1.4 เดือน (หลังจาก Founding period)

### 7.6 Revenue Potential จาก 17 Sites ปัจจุบัน (อ้างอิง §4.4)

| เฟส | MRR | ARR |
|---|---|---|
| Founding (12 เดือนแรก, 100% convert) | ฿32,517 | ฿390,204 |
| Post-Founding (list price, 100% retention) | ฿64,842 | ฿778,104 |
| + Overage เมื่อ volume โต 20% | ฿75,000+ | ฿900,000+ |

**หมายเหตุ:** ตัวเลขนี้ *ไม่นับลูกค้าใหม่* — เป็น floor ของรายได้จาก 17 sites ที่อยู่ในมือแล้ว

---

## 8. Brand & UI/UX Theme — หน้าตาแอป

### 8.1 Brand Positioning
- **Personality:** มืออาชีพ, น่าเชื่อถือ, เรียบง่าย, ทันสมัย — *"เครื่องมือของคนสาธารณสุขยุคใหม่"*
- **Tone:** สุภาพ, ให้ความรู้, ไม่เว่อร์, ไม่ขายตรง
- **Tagline แนะนำ:**
  - 🇹🇭 "บันทึก NAP ให้เสร็จก่อนคู่แข่ง"
  - 🇹🇭 "บันทึกเร็ว เคลมทัน ทุกเคส"
  - 🇬🇧 "Automate NAP. Claim on time."

### 8.2 Color Palette ที่แนะนำ

**Primary Theme — "Clinical Trust"** (แนะนำ ⭐)

เน้น professional medical-grade แต่ไม่แข็งเกินไป เหมาะกับคนสาธารณสุข

| บทบาท | สี | HEX | ใช้ที่ไหน |
|---|---|---|---|
| Primary | น้ำเงินสาธารณสุข (Medical Blue) | `#0F6FDE` | ปุ่มหลัก, header, logo |
| Primary Dark | น้ำเงินเข้ม | `#0A4FA0` | hover, active |
| Accent | เขียวมิ้นต์ (Success Green) | `#10B981` | success state, badge ✅ |
| Warning | เหลืองอำพัน | `#F59E0B` | queue pending |
| Danger | แดงเข้ม | `#DC2626` | error, failed |
| Neutral 900 | เทาเข้ม (text) | `#111827` | body text |
| Neutral 500 | เทากลาง | `#6B7280` | muted |
| Neutral 50 | เทาอ่อนมาก | `#F9FAFB` | background |
| Surface | ขาว | `#FFFFFF` | card |

**ทำไมน้ำเงิน?** — ตลาดสาธารณสุขไทยคุ้นเคยกับน้ำเงิน สปสช./กระทรวงสาธารณสุข → สื่อว่า "เป็นพวกเดียวกัน" ไม่ใช่ product เชิงพาณิชย์ก้าวร้าว

**Dark Mode** — รองรับ เพราะเจ้าหน้าที่บางคนใช้งานกลางคืน/ระหว่างเคส

### 8.3 Alternative Palettes

- **"Fresh Health"** — เขียวมิ้นต์ primary (`#10B981`) + น้ำเงินรอง → เหมาะถ้าอยาก friendly กว่า แต่อาจดู consumer เกินไป
- **"Hospital Pro"** — น้ำเงินเข้ม navy (`#1E3A8A`) + ทอง → ดู premium แต่ too corporate สำหรับ NGO

### 8.4 Typography
- **ภาษาไทย:** ฟอนต์ sans-serif สมัยใหม่ที่อ่านง่ายบน dashboard (น้ำหนักครบทั้ง regular/medium/bold)
- **ภาษาอังกฤษ/ตัวเลข:** ฟอนต์ที่ตัวเลขเรียงสวยสำหรับหน้า stats (tabular numerals)

### 8.5 Design System
- **สไตล์ UI:** Clean, modern, มืออาชีพ — ใช้ component library มาตรฐานอุตสาหกรรม
- **ไอคอน:** ชุดไอคอนเรียบ สม่ำเสมอทั้งระบบ
- **Layout หลัก:**
  - Sidebar ซ้าย (แคบได้, collapse ได้) — dashboard, queue, history, settings
  - Topbar แสดงชื่อ site + ผู้ใช้ + theme toggle
  - พื้นที่เนื้อหาหลักกว้าง responsive
- **Data-dense mode** — dashboard โชว์หลาย metric พร้อมกัน (queue, สถานะงาน, สถิติ) โดยไม่รกตา
- **Real-time indicators** — แสดงจุดสีเขียวกะพริบเวลาเชื่อมต่อสำเร็จ, แดงเวลาขาด
- **Mobile:** responsive แต่ไม่ต้อง optimize หนัก (กลุ่มผู้ใช้หลักคือ desktop)

### 8.6 Feel & Motion
- Micro-interaction น้อยแต่ชัด (loading skeleton, optimistic update)
- Transition 150–200ms (สั้น, เร็ว)
- ห้ามใช้ animation ใหญ่ที่ทำให้ดู gimmicky — user มืออาชีพ
- Error message เป็นภาษาไทย (ใช้ `humanError()` ที่มีอยู่แล้ว) — สำคัญมากต่อ trust

---

## 9. Risks & Mitigation

| Risk | Impact | Mitigation |
|---|---|---|
| **สปสช. เปลี่ยน NAP Plus API/UI** | สูง | Abstraction layer, monitor changes, retainer กับนักพัฒนา |
| **คู่แข่งลอกเลียน** | กลาง | Moat คือ integration + reference customers + domain knowledge |
| **Privacy/PDPA** | สูง | ไม่เก็บ PII นานเกินจำเป็น, audit log, PDPA consent, hosting ไทย |
| **สปสช. ทำระบบเองฟรี** | สูง | โฟกัส UX + integration + speed — ของรัฐโดยปกติช้าและ UX ไม่ดี |
| **Single point of failure ของ infra** | กลาง | Multi-region, backup infrastructure, disaster recovery plan |
| **Churn เมื่อ champion ลาออก** | กลาง | Onboarding หลายคน, documentation ดี, email training |

---

## 10. Recommended Next Actions (90 วัน)

### Month 1
- [ ] ทำ landing page + ROI calculator + pricing page
- [ ] ถ่าย case study video กับ MPlus CMI
- [ ] Set up self-serve signup flow + billing (payment gateway)
- [ ] Email 20 ศูนย์เป้าหมาย (warm list) เสนอ Founding offer

### Month 2
- [ ] Onboard 5 Founding customers แรก (เป้า 10 ภายในเดือน 3)
- [ ] เขียน blog 4 บทความ (SEO)
- [ ] จัด webinar demo ครั้งที่ 1
- [ ] Integration ทางเทคนิคกับ HIVQual หรือ SmartClinic 1 ราย

### Month 3
- [ ] วัดผล: MRR, churn, NPS
- [ ] ปรับ pricing จริงตาม feedback
- [ ] เตรียมสื่อสำหรับ Phase 2 (content + AE hiring)
- [ ] เสนอ Founding Members ให้ช่วย refer 1 ศูนย์ต่อคน

---

## 11. KPIs ที่ต้องวัดทุกเดือน

### Acquisition
- Leads / เดือน
- Demo booked / Lead (conversion)
- Paid customer / Demo (close rate)
- CAC (บาทต่อลูกค้า)

### Activation
- % customer ที่บันทึกเคสแรกภายใน 7 วัน
- Time to first value (ชั่วโมง)

### Retention
- MRR churn %
- Logo churn %
- Net Revenue Retention (NRR)

### Revenue
- MRR, ARR
- ARPU
- LTV, LTV:CAC ratio

### Product
- เคสที่บันทึกสำเร็จ / เคสรวม (success rate)
- เวลาเฉลี่ย / เคส
- Uptime %
- Support ticket / customer

---

## 12. Strategic Review — เมษายน 2569 (Post-Competitive Research)

> อัปเดตหลังทำ competitive research + pricing review รอบที่ 2 (2026-04-20)

### 12.1 Competitive Intelligence Update

**ผลสำรวจตลาด (เม.ย. 2569):** ไม่พบคู่แข่งทางตรง (blue ocean ยืนยัน) แต่มีทางเลือกใกล้เคียงต้องจับตา

#### คู่แข่งทางอ้อมที่สำคัญที่สุด — NAPPLUS LAB API

- **เปิดตัว:** 26 ส.ค. 2567 โดย กรมควบคุมโรค + สปสช. + Thai-US CDC
- **Pilot:** 74 รพ. ใน 13 จังหวัด (เชียงใหม่, นนทบุรี, อุดรฯ, สุราษฎร์, กทม.)
- **แผน:** ขยายทั่วประเทศ 12–18 เดือน
- **ข้อจำกัด:** ส่งเฉพาะผล LAB (HIV, CD4, Viral Load) — **ไม่ครอบคลุม VCT / RR / ลงทะเบียน / Result**
- **ต้องการ:** ความสามารถด้าน dev ของ รพ./คลินิก เพื่อ integrate

#### ภูมิทัศน์คู่แข่งทางอ้อม

| ชื่อระบบ | ประเภท | จุดแข็ง | จุดอ่อนเทียบ AutoNAP |
|---|---|---|---|
| **NAPPLUS LAB API** | Gov API (ฟรี) | ทางการ, integrate กับระบบ สปสช. ตรง | LAB only, ต้องมี dev, ไม่มี UI/dashboard |
| **EIIS (Auto HIV Case Reporting)** | Gov system | ดึงจาก EMR อัตโนมัติ | ใช้กับ รพ. ที่มี EMR ครบเท่านั้น, เป็น case detection ไม่ใช่ form filling |
| **HOSxP / HIS รายใหญ่** | HIS | ใช้ใน 300+ รพ. | ไม่มี NAP auto-submission โมดูลทางการ, เน้น รพ.รัฐไม่ใช่ NGO |
| **Love2Test** | Booking platform | Ecosystem NGO, traffic สูง | ไม่ใช่ automation — เป็น discovery layer |
| **RPA ทั่วไป (UiPath ฯลฯ)** | Generic RPA | ยืดหยุ่นสูง | ต้องจ้าง consultant, ไม่มี domain NAP, CAC สูง |

#### Threat Matrix

| Threat | Timeline | ระดับ | Counter-move |
|---|---|---|---|
| NAPPLUS API ขยายทั่วประเทศ | 12–18 เดือน | 🔴 สูง | เน้น VCT/RR/Result (ส่วนที่ API ไม่ทำ) + Lock Founding ยาวๆ |
| สปสช. ทำ VCT/RR API เอง | 24–36 เดือน | 🟡 กลาง | Build moat ด้วย integration + UX — ของรัฐขยายช้า |
| HOSxP เพิ่ม NAP module | 12 เดือน | 🟡 กลาง | โฟกัส NGO/CBO ที่ไม่ใช้ HOSxP |
| RPA consultant custom | ตลอดเวลา | 🟢 ต่ำ | CAC ของเขาสูง, SMB ราคาเข้าไม่ถึง |

### 12.2 Positioning ที่อัปเดต

```
                  ระดับการ Automation
                       สูง
                        ↑
          ┌─────────────────────────────┐
          │     🟢 AutoNAP              │
          │     (VCT + RR + Multi-form) │
          │     Full workflow           │
          │                             │
          │  🔴 NAPPLUS API (gov)       │
          │  (LAB only, partial)        │
          │                             │
เชิงฟอร์ม ←────────────────────────────→ เชิงข้อมูล (backend)
          │                             │
          │  🟡 HOSxP / HIS             │
          │  (general HIS, weak NAP)    │
          │                             │
          │  ⚪ พนักงาน + Excel          │
          │  (status quo)               │
          └─────────────────────────────┘
                        ↓
                      ต่ำ
```

**Insight:** AutoNAP ต้องเลิก position ว่า "ระบบอัตโนมัติบันทึก NAP" (generic) แล้วเจาะลงเป็น **"ระบบบันทึก VCT + RR + Lab + Result ครบทุกฟอร์ม"** — เพราะถ้าลูกค้าเจอ NAPPLUS API ฟรีแล้วจะถามทันทีว่า "ทำไมต้องจ่ายเรา"

### 12.3 Pricing Inconsistency — ต้องแก้ก่อนใช้เชิงพาณิชย์

พบว่าเลข Scale tier ไม่ตรงกันระหว่างเอกสาร:

| Source | Growth List | Scale List | Quota Scale |
|---|---|---|---|
| `marketing.md` §4.2 | ฿3,990 | ฿7,990 | 3,000 |
| memory (2026-04-15) | ฿3,990 | ฿5,990 | 3,500 |
| Landing page (Welcome.vue) | — | ต้องตรวจ | — |

→ **Action:** เลือก 1 version แล้ว sync ทั้ง 3 จุด (marketing.md + memory + landing) ก่อนออก collateral ใหม่

### 12.4 Pricing Review — จุดที่ควรปรับ

#### A. Scale tier — ช่วงกว้างเกินไป
จากข้อมูล 17 sites: `mplus_cmi 1,617 เคส` ตกช่องเดียวของ Scale แต่ใช้ quota แค่ ~50%

- Growth (1,500) → แตก = overage wave
- Scale (3,000–3,500) → ว่างเยอะ (headroom 1,400 เคส)
- 13 ใน 17 sites อยู่ 500–1,700 เคส → ส่วนใหญ่ทรงตัวที่ช่วง Growth–Scale ทั้งหมด

#### B. RR Basic ฿790 — ทิ้งมูลค่าบนโต๊ะ
- ศูนย์ RR Only ยังประหยัด ~฿10–15K/เดือน
- ราคา ฿790 = ~5% ของ saving → ต่ำกว่า rule of thumb 10–20%
- Anchor เทียบ Starter ฿1,990 = -60% ห่างเกินไป

#### C. Enterprise ไม่มี headroom ด้านบน
`rsat_bkk 3,866 เคส` → ถ้าโตเป็น 6,000+ → ไม่มี upsell ต่อ → ทิ้งดีลใหญ่ของเครือข่าย รพ.

### 12.5 Proposed Pricing v2 — 3 Options

#### Option A — Safe (ไม่กระทบ 17 sites เดิม)
- Lock 17 sites ที่ Founding price ต่อไป (ตามสัญญา)
- แก้เฉพาะ **list price** สำหรับลูกค้าใหม่
- เพิ่ม **Pro tier** ระหว่าง Growth กับ Scale เพื่อ smooth upsell

#### Option B — Restructure (แนะนำ ⭐)

```
┌─────────────┬──────────┬──────────┬───────────┐
│ Tier        │ Founding │ List     │ Quota     │
├─────────────┼──────────┼──────────┼───────────┤
│ RR Basic    │ ฿790     │ ฿1,290   │ 400 เคส   │
│ Starter     │ ฿995     │ ฿1,990   │ 600       │
│ Growth ⭐   │ ฿1,995   │ ฿3,990   │ 1,500     │
│ Pro (ใหม่)   │ ฿2,495   │ ฿4,990   │ 2,500     │
│ Scale       │ ฿2,995   │ ฿5,990   │ 4,000     │
│ Enterprise  │ ฿6,495   │ ฿12,990  │ 8,000     │
│ Custom      │ คุย      │ คุย      │ unlimited │
└─────────────┴──────────┴──────────┴───────────┘
```

#### Option C — Aggressive (รอ 25+ sites & retention ≥95%)
- ขึ้น list price +30–50% ทั้งบอร์ด
- ARR โต 1.5x ทันที
- เสี่ยง churn ใหม่เพิ่ม, existing deals ต่อไม่ได้

### 12.6 Messaging Updates (หลัง NAPPLUS API)

**เปลี่ยน messaging หลัก:**
- ❌ เดิม: "ระบบอัตโนมัติบันทึก NAP"
- ✅ ใหม่: "บันทึก **VCT + RR + Lab + Result** ครบทุกฟอร์ม ในคลิกเดียว — ที่ NAPPLUS API ยังทำไม่ได้"

**Landing page ต้องเพิ่ม:**
- [ ] Comparison table **"AutoNAP vs NAPPLUS LAB API"**
- [ ] FAQ entry: *"สปสช. มี NAPPLUS API ฟรีแล้วไม่ใช่เหรอ ทำไมต้องใช้ AutoNAP"*
- [ ] Badge **"ครอบคลุม 4+ ฟอร์ม"** / **"ไม่ต้องจ้าง dev"**
- [ ] Positioning เป็น **complementary** (ใช้ร่วมกับ NAPPLUS API ได้) ไม่ใช่ replacement

**Content ใหม่ที่ควรทำ:**
- [ ] Blog: *"NAPPLUS LAB API vs AutoNAP: เลือกตัวไหน ใช้ควบคู่ได้ไหม"* (SEO + educate)
- [ ] Blog: *"VCT Automation: ทำไม NAP API ยังไม่ครอบคลุม"*
- [ ] Whitepaper PDPA — pre-empt concern ของภาคราชการ
- [ ] Case study integration NAPPLUS + AutoNAP ร่วมกัน

### 12.7 Channel Strategy Update

Research ชี้ว่า ecosystem NGO ไทยรวมตัวอยู่ภายใต้:

| Channel Partner | Reach | Priority |
|---|---|---|
| **Love Foundation / Love2Test** | MPlus, RSAT, SWING, SISTERS, Caremat ครบ | 🔴 Priority #1 |
| **ThaiAIDS / Asia Pacific HIV Conference** | ประชุมวิชาการระดับชาติ/ภูมิภาค | 🟡 เข้าในนามวิชาการ |
| **สปสช. Forum** | ลูกค้าตรงแต่ conflict risk | 🟡 Careful — เข้าในนามวิชาการไม่ใช่ขาย |
| **Thai CBO/NGO coalition meetings** | เข้าถึง decision maker โดยตรง | 🟢 Build relationship |

### 12.8 90-Day Priority Actions (Revised)

| ลำดับ | Action | ความสำคัญ | Deadline |
|---|---|---|---|
| 1 | Sync pricing ทั้ง marketing.md + memory + landing page | 🔴 ด่วน | ก่อน 30 เม.ย. |
| 2 | เพิ่ม section **"AutoNAP vs NAPPLUS API"** บน landing | 🔴 ด่วน | 15 พ.ค. |
| 3 | ปิด Founding lock กับ 17 sites ให้ครบ | 🔴 ด่วน | 31 พ.ค. |
| 4 | Test **Pro tier ฿4,990 / 2,500 เคส** กับ 2–3 prospects ใหม่ | 🟡 กลาง | มิ.ย. |
| 5 | Outreach Love Foundation เรื่อง channel partnership | 🟡 กลาง | มิ.ย. |
| 6 | Content series **"VCT Automation"** (3 articles) | 🟢 normal | มิ.ย.–ก.ค. |
| 7 | เขียน blog **"NAPPLUS API vs AutoNAP"** (SEO target) | 🟢 normal | ก.ค. |

### 12.9 One-line Summary

> **Pricing โครงสร้างถูกต้องแล้ว แต่ต้องเคลียร์ความไม่ตรงกันของเลข + preempt threat NAPPLUS API + เพิ่ม Pro tier ให้ upsell smooth — ภายใน Q2 2569**

---

## ภาคผนวก A — เหตุผลทำไมราคานี้ถูกต้อง

**Rule of thumb SaaS B2B ไทย:** ราคาต่อเดือนควร = **10–20% ของต้นทุนเดิมที่ลูกค้าประหยัด**

- ลูกค้า Growth ประหยัด ~23,000 บาท/เดือน → ราคา 10–20% = **฿2,300–4,600**
- ราคา ฿4,990 อยู่ปลายบน = leave room สำหรับ discount, promo, channel commission โดยยังคง margin ดี

## ภาคผนวก B — Unit Economics ตัวอย่าง

```
Growth Customer (฿4,990/เดือน)
├─ Infra cost (server + realtime + email) ~฿300/เดือน
├─ Support (1 ticket, 30 นาที)         ~฿400/เดือน
├─ CAC amortized (ผ่อน 12 เดือน)       ~฿500/เดือน
└─ Gross profit                        ~฿3,790/เดือน (76% margin)

Payback period: ~1.6 เดือน
```

---

**Version:** 1.1
**เขียนเมื่อ:** 13 เมษายน 2569
**อัปเดตล่าสุด:** 20 เมษายน 2569 — เพิ่ม §12 Strategic Review (competitive intelligence + pricing review รอบ 2)
**สถานะ:** Active — ใช้งานจริง 17 sites, รอ sync pricing และ preempt NAPPLUS API
