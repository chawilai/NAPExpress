# AutoNAP Introduction Plan — 117 HIV Ecosystem Targets

เอกสารแผนการแนะนำ AutoNAP ไปยัง 117 หน่วยบริการกลุ่ม HIV ecosystem (Target list อยู่ใน `docs/research/cpp_hiv_ecosystem.csv`)

**Goal:** ปิดดีล 30–50 sites ใน 12 เดือนแรก → ARR ฿600k–1.2M

---

## 1. Target Segmentation Recap

| Track | Segment | TAM | Profile | Pain level |
|---|---|---:|---|---|
| **A** | ชมรม/กลุ่ม (RR only) | 63 | Small CBO, peer educators, outreach | ⭐⭐ |
| **B1** | Foundation/Association (no clinic) | ~28 | NGO ที่ outreach ใหญ่ + VCT basic | ⭐⭐⭐ |
| **B2** | Foundation/Association + Paired Clinic | ~13 | Flagship NGOs (MPlus, RSAT, SWING, Caremat) | ⭐⭐⭐⭐ |
| **B3** | Med-Tech Clinic (standalone, HIV-affiliated) | ~13 | Lab-focused, high volume testing | ⭐⭐⭐⭐⭐ |
| | **รวม** | **117** | | |

### 17 ลูกค้าปัจจุบัน (สำหรับอ้างอิง)

- **Track A (RR Only):** 4 sites (hugfang, vcap, namkwan, mplus_bkk)
- **Track B (Full Suite):** 13 sites (MPlus ×5, RSAT ×7, Caremat ×1)

→ เรามี reference customers ครบทุก track แล้ว — ไม่ต้อง guess ว่าใครเหมาะกับอะไร

---

## 2. Value Proposition per Track

### Track A — "ชมรม/กลุ่ม RR Only"

**Audience:** หัวหน้ากลุ่ม / peer coordinator / ผู้ประสานงาน CBO

**ปัญหาที่แก้:**
- บันทึก RR เดือนละหลายร้อยเคสด้วยมือ (1–3 นาที/เคส)
- ผู้ประสานงานเป็นอาสา เสียเวลาที่ควรไปทำงานภาคสนาม
- เคลมเงินตก/ช้า → งบของกลุ่มหายไป
- พนักงานบันทึก turnover สูง (กลุ่มเล็กจ้างคนไม่ไหว)

**Hook line:** *"ประหยัดเวลา 10 ชั่วโมง/สัปดาห์ ให้อาสาไปทำงานจริงแทนนั่งพิมพ์"*

**คุณค่าเชิงตัวเลข:**
- กลุ่มเล็ก (300 เคส/ด.): ประหยัดเวลา ~15 ชม./ด. = ~฿3,000 ในรูป opportunity cost
- ประหยัดเคลมตก ~฿2,000–5,000/ด.
- **Total pain: ~฿5,000–8,000/ด.** → ราคา Starter ฿790 = **ROI 6-10×**

### Track B1 — "Foundation/Association (no clinic)"

**Audience:** ผู้จัดการ มูลนิธิ/สมาคม ที่ทำ outreach + VCT แต่ไม่มี lab ของตัวเอง

**ปัญหาที่แก้:**
- ทุก pain ของ Track A + ต้องบันทึก VCT + ต้อง coordinate กับคลินิกอื่นเรื่อง lab/result
- Staff บันทึก 1 คน = ฿15,000–18,000/เดือน + ประกันสังคม

**Hook line:** *"แทนที่จ้างพนักงานบันทึกคนที่ 2 จ่ายเราแค่ ฿3,990/ด."*

**คุณค่าเชิงตัวเลข:**
- Pain: ~฿20,000/เดือน (staff + opportunity + claim loss)
- Package: Growth ฿3,990 → **ประหยัด ฿16,000/ด.** = ฿192,000/ปี

### Track B2 — "Foundation + Paired Clinic" (Flagship)

**Audience:** ผู้อำนวยการ / ผู้จัดการคลินิก ของ MPlus, RSAT, SWING, Caremat, Sisters, ...

**ปัญหาที่แก้:**
- บันทึก NAP 4 forms × 1,000+ เคส/เดือน
- จ้างพนักงานบันทึก **2 คน** = ฿30,000–40,000/เดือน
- เคลมจำนวนมากต่อเดือน ทำตกหล่นได้ง่าย
- UIC race condition (เคสนึงเคลมได้แค่ศูนย์เดียว)

**Hook line:** *"คลินิกใหญ่ใช้ AutoNAP แล้ว 5 สาขา — มาร่วมเครือข่ายเดียวกัน"* (social proof)

**คุณค่าเชิงตัวเลข:**
- Pain: ~฿40,000–60,000/เดือน
- Package: Scale ฿7,990 หรือ Enterprise ฿14,990
- **ประหยัด ฿30,000+/ด.** = ฿360,000+/ปี

### Track B3 — "Med-Tech Clinic (HIV-affiliated)"

**Audience:** ผู้จัดการคลินิกเทคนิคการแพทย์ที่ทำ HIV lab

**ปัญหาที่แก้:**
- Volume lab สูงที่สุด (มากกว่า B2)
- ต้องบันทึก Lab + Result ทุกเคส
- Lab tests ล่าช้า = เคลมตก

**Hook line:** *"คลินิก lab HIV ใช้ AutoNAP ทำ VCT→Lab→Result ครบวงจรใน 10 นาที แทนที่จะ 2 ชั่วโมง"*

---

## 3. Product Lineup (Proposed)

### Current: AutoNAP Core (automation-only)

| Package | ราคา | ใครใช้ |
|---|---|---|
| RR Basic Starter | ฿790 | Track A เล็ก |
| RR Basic Growth | ฿1,490 | Track A กลาง |
| Full Suite Starter | ฿1,990 | Track B small |
| Full Suite Growth | ฿3,990 | Track B1 medium |
| Full Suite Scale | ฿7,990 | Track B2 large |
| Full Suite Enterprise | ฿14,990 | Track B2/B3 XL |

### Proposed Expansion: 3 Product Tiers

#### 🟢 AutoNAP Core (ที่มีอยู่แล้ว)
- Upload CSV → บันทึก NAP ให้อัตโนมัติ
- Callback, Dashboard, Email report
- **ราคา: เท่าเดิม**

#### 🔵 AutoNAP RR Lite (ใหม่ — สำหรับ Track A)
ระบบ mini-CRM สำหรับ ชมรม/กลุ่ม ที่ **ไม่มีระบบเลย** (ปัจจุบันใช้กระดาษ/Excel):
- หน้า Web ง่ายๆ กรอก outreach record
- เก็บฐานผู้รับบริการ (PID, UIC, KP)
- Auto-submit ไปยัง NAP ผ่าน AutoNAP engine
- Offline-first (รองรับภาคสนาม)
- **ราคา: ฿1,490/เดือน** (แพงกว่า RR Basic Starter ฿700 — จ่ายเพิ่มเพื่อระบบ end-to-end)

**ทำไมสำคัญ:** 63 กลุ่ม/ชมรม ส่วนใหญ่**ยังใช้กระดาษ** ไม่มีระบบเลย → AutoNAP Core ไม่ช่วย (ต้องมีข้อมูลใน Excel ก่อน) → ต้องสร้าง UI เก็บข้อมูลให้

#### 🔴 AutoNAP Clinic (ใหม่ — สำหรับ Track B2/B3)
ระบบจัดการคลินิก HIV เต็มรูปแบบ + AutoNAP integrated:
- Patient management (Contact, history, UIC)
- VCT workflow (pre/post counseling, risk assessment)
- Lab integration (order → result → NAP)
- PrEP/PEP tracking + refill reminder
- Appointment scheduling
- Staff attendance
- **ราคา: ฿12,900+/เดือน** (แข่งกับ CAREMAT/HIVQual)

**ทำไมสำคัญ:** Track B2/B3 มีระบบแล้ว (CAREMAT ส่วนใหญ่) แต่ถ้าคุณอยาก pitch เข้าคลินิกใหม่ที่ไม่ใช้ CAREMAT ต้องมีทางเลือก

### ⚠️ ข้อควรระวัง — Expansion Risk

**อย่า build AutoNAP Clinic ทั้งหมดตอนนี้** เพราะ:
1. 🛑 แข่งตรงกับ CAREMAT/HIVQual ที่มีฐานลูกค้า 10+ ปี
2. 🛑 Dev effort ใหญ่มาก (6–12 เดือน)
3. 🛑 เสี่ยง dilute focus จาก core AutoNAP ที่ทำเงินอยู่แล้ว
4. ✅ ทำ **AutoNAP RR Lite** ก่อน (scope เล็ก, ตลาด blue ocean 63 sites, ไม่แข่งใคร)
5. ✅ AutoNAP Clinic ทำเป็น **Phase 2** ในปีที่ 2 ถ้า Track A/B เจาะตลาดได้แล้ว

**คำแนะนำ:** Year 1 = Core + RR Lite, Year 2 = AutoNAP Clinic (ถ้า demand ชัด)

---

## 4. Promotions & Launch Offers

### 🎯 Main Launch Offer — "Founding 50"

**เปิดรับ 50 sites แรก** (31 พ.ค.–31 ก.ค. 2569)

| Perks | Details |
|---|---|
| **ส่วนลด 50%** | ล็อค 12 เดือน |
| **Onboarding ฟรี** | Training + setup + data migration |
| **Founding Member badge** | บน dashboard + certificate |
| **Priority support** | Line group dedicated |
| **No overage year 1** | เคสเกินโควตาไม่คิดเพิ่ม |
| **Lock-in ราคา** | เมื่อหมดโปร ขึ้นราคาไม่เกิน 10% |
| **Reference permission** | ใช้ชื่อเป็น case study ได้ (optional, ได้เครดิต) |

### 🎁 Upfront Payment Bonus

| Pay upfront | Bonus |
|---|---|
| 3 เดือน | ฟรี 1 เดือน (4 เดือนจริง) |
| 6 เดือน | ฟรี 2 เดือน (8 เดือนจริง) |
| 12 เดือน | ฟรี 3 เดือน (15 เดือนจริง) + lock ถึงเดือนที่ 18 |

### 👥 Refer-a-Friend (ตลอดปี)

- แนะนำ 1 site → ได้ฟรี 1 เดือน (ผู้แนะนำ) + trial 30 วัน (ผู้ใหม่)
- แนะนำ 3 sites → ได้ฟรี 3 เดือน
- แนะนำ 5 sites → ได้ฟรี 6 เดือน + "Community Champion" badge

### 🗓️ Seasonal Campaigns

| ช่วง | Campaign | เหตุผล |
|---|---|---|
| ต้นปีงบประมาณ (ต.ค.) | Free audit + 1 เดือนฟรี | ลูกค้า plan budget ใหม่ |
| ปลายปีงบประมาณ (ก.ย.) | ลด 20% ถ้า pay annually | ใช้งบก่อนหมด |
| หลังสงกรานต์ (เม.ย.–พ.ค.) | เพิ่ม quota 50% | Peak ช่วงเทศกาล |
| World AIDS Day (1 ธ.ค.) | ฟรี 1 เดือน + donate คนละ ฿100 | Brand campaign |

---

## 5. Outreach Strategy (Channel Plan)

### Phase 1 — Warm Outreach (เดือน 1–2)

**เป้าหมาย:** 20 dealsปิด | MRR ฿40,000 | ARR ฿480k

| Channel | Action | Who |
|---|---|---|
| **1. Personal network** | โทรหา 17 ลูกค้าปัจจุบัน ขอ refer ไปยัง sister sites | คุณ |
| **2. MPlus HQ** | ขอ intro ไป 5 branches ที่ยังไม่ใช้ (BKK, Phitsanulok, Chiang Rai, ฯลฯ) | คุณ |
| **3. RSAT HQ** | ขอ intro ไป 7 branches | คุณ |
| **4. Caremat** | Reference call + ขอให้แนะนำ clinic เครือข่าย | คุณ |
| **5. TNCA / IHRI** | ขอ introduction ผ่าน gatekeeper | คุณ |

**Messaging:**
> "ตอนนี้ AutoNAP มีลูกค้า 17 sites ทั่วประเทศแล้ว (MPlus, RSAT, Caremat, SWING) — ช่วยลดเวลาบันทึก NAP จาก 3 นาที เหลือ 15 วินาที/เคส อยากให้พี่ลองดูไหมครับ?"

### Phase 2 — Content + Inbound (เดือน 3–6)

**เป้าหมาย:** +15 sites | MRR ฿70,000

| Channel | Action |
|---|---|
| **Landing page** | เปิด autonap.actse-clinic.com พร้อม ROI calculator |
| **Blog / บทความ** | 4 บทความ/เดือน ใน Line OA, Facebook group สาธารณสุข |
| **Video case study** | 3 videos (Caremat, MPlus, RSAT) — "ก่อน/หลังใช้ AutoNAP" |
| **Webinar** | เดือนละ 1 ครั้ง — demo live + Q&A |
| **Email นuturing** | สัปดาห์ละ 1 ครั้ง ส่งให้ 117 sites (ขอ consent ก่อน) |

### Phase 3 — Direct Sales + Partners (เดือน 7–12)

**เป้าหมาย:** +15 sites | MRR ฿120,000

| Channel | Action |
|---|---|
| **On-site visit** | ไปเยี่ยม flagship clinics — demo on-site |
| **Booth ที่สัมมนา** | HIV forum, สปสช. forum, NGO coalition meeting |
| **Partner channel** | CAREMAT/HIVQual integration → commission 15% |
| **Google Ads** | "บันทึก NAP" | "ระบบบันทึก สปสช" | "สปสช. HIV" |

---

## 6. Landing Page Recommendation

### ⭐ **ใช่ครับ ต้องมี landing page** — deal breaker สำหรับ Phase 2/3

### URL แนะนำ
- **Main:** `autonap.actse-clinic.com` (มีอยู่แล้วไหม?)
- **Alternative:** `autonap.co.th`, `bannaprr.com`, `napexpress.app`

### Landing Page Structure (ที่แนะนำ)

```
┌─────────────────────────────────────────┐
│  [Logo] AutoNAP      [Login] [เริ่มทดลอง] │
├─────────────────────────────────────────┤
│                                          │
│   บันทึก NAP ให้เสร็จก่อนคู่แข่ง           │  ← Hero
│   อัตโนมัติ 100% — เร็วขึ้น 10 เท่า        │
│                                          │
│   [Logo ลูกค้า: MPlus RSAT Caremat...]   │  ← Social proof
│                                          │
│   [▶ Video Demo 90 วินาที]              │
│   [คำนวณ ROI ของคุณ]                    │  ← Interactive
│                                          │
├─────────────────────────────────────────┤
│  ปัญหาที่คุณเจอทุกเดือน                   │  ← Pain
│  • บันทึก NAP 1–3 นาที/เคส               │
│  • พนักงานลาออกบ่อย                      │
│  • เคลมตก, UIC ถูกชิงไป                  │
│  • เสียเงินเดือนละ ฿20,000–40,000         │
├─────────────────────────────────────────┤
│  วิธีการทำงาน (3 ขั้นตอน)                 │  ← How it works
│  1. Upload CSV / ใช้ Web Form            │
│  2. AutoNAP บันทึกให้                    │
│  3. รับผลทาง email + dashboard           │
├─────────────────────────────────────────┤
│  แพ็คเกจ & ราคา                          │  ← Pricing
│  [RR Basic] [Growth] [Scale] [Enterprise]│
│  ROI Calculator (interactive)            │
├─────────────────────────────────────────┤
│  ลูกค้าของเราพูดว่า                       │  ← Testimonials
│  [คำชม Caremat] [MPlus] [RSAT]          │
├─────────────────────────────────────────┤
│  คำถามที่พบบ่อย (FAQ)                     │  ← FAQ
│  • ข้อมูลผู้ป่วยปลอดภัยไหม?                │
│  • รองรับ CAREMAT ไหม?                    │
│  • ถ้าไม่มีระบบคลินิก ใช้ได้ไหม?            │
├─────────────────────────────────────────┤
│  Founding 50 — เหลือ X ที่นั่ง            │  ← Urgency
│  [เริ่มทดลองฟรี 30 วัน] [นัดคุย Demo]     │
└─────────────────────────────────────────┘
```

### Content Must-Haves

1. **Hero video 60–90 วินาที** — แสดงของจริง ก่อน/หลังใช้
2. **ROI Calculator** — ใส่จำนวนเคส/เดือน → โชว์ประหยัดเท่าไร (viral)
3. **Logo wall** — 6–8 logo ลูกค้า (ขอ consent ก่อน)
4. **Video testimonial** จาก Caremat + MPlus CMI — อย่างน้อย 2 คลิป 30 วินาที
5. **Pricing table** — ชัด ไม่ต้องติดต่อเพื่อดูราคา (transparency = trust)
6. **Free trial CTA** — 30 วันฟรี, ไม่ต้องใส่บัตร
7. **Booking widget** — Calendly/Cal.com สำหรับนัด demo
8. **Case study PDF** — "MPlus CMI ประหยัด ฿260,000/ปี"
9. **PDPA compliance badge** — "ข้อมูลเก็บในไทย" + "PDPA compliant"
10. **Live chat** (LINE OA / Intercom / Tidio) — สำคัญสำหรับ Thai audience

### Tech Stack (แนะนำ)

- **Framework:** Astro หรือ Next.js (SEO-friendly)
- **CMS:** Headless (Sanity/Notion) สำหรับแก้ไข content ได้โดยไม่ต้อง dev
- **Analytics:** Plausible หรือ GA4
- **Heatmap:** Hotjar (ช่วง launch ดู user behavior)
- **Form:** Native → Laravel API
- **Hosted:** Vercel / Netlify / VPS เดียวกัน

### Timeline

- **Week 1:** Wireframe + copywriting + ROI calculator logic
- **Week 2:** Design + responsive dev
- **Week 3:** Content, video shoot, QA
- **Week 4:** Launch + announce

---

## 7. Outreach Sequence Template

### 🎯 Cold Email Sequence (5 touches)

**Email 1 (Day 0) — Intro + social proof**
```
Subject: [ชื่อองค์กร] เคยเจอปัญหาเวลาบันทึก NAP ตกหล่นไหมครับ?

สวัสดีครับ พี่ [ชื่อ]

ผม [ชื่อคุณ] จาก AutoNAP ที่กำลังช่วยศูนย์ HIV 17 แห่ง รวม
MPlus, RSAT, Caremat บันทึก NAP อัตโนมัติ — ลดเวลาจาก 3 นาที
เหลือ 15 วินาที/เคส

เห็นว่า [ชื่อองค์กร] ทำงาน HIV ในพื้นที่ [จังหวัด] ซึ่งน่าจะเจอ
ปัญหาเดียวกัน สะดวกคุยกันสัก 15 นาทีไหมครับ? ผมมีข้อมูลที่
[ศูนย์ reference ที่อยู่ในเครือ] ประหยัดได้เดือนละเกือบ ฿20,000

- Video demo 90 วินาที: [link]
- หน้า AutoNAP: [landing page]

ขอบคุณครับ
[ชื่อ] + เบอร์ + email
```

**Email 2 (Day 3) — ROI calculator**
```
Subject: พี่ [ชื่อ] ลองคำนวณ ROI หน่อยได้ไหมครับ?

...เคสต่อเดือนของ [ชื่อองค์กร] ประมาณเท่าไรครับ?

ผมทำ ROI calculator ให้ลองเล่นดู [link]
ส่วนใหญ่ศูนย์ขนาดกลางประหยัด ฿15,000–25,000/เดือน
```

**Email 3 (Day 7) — Case study**
```
Subject: เคสจริง: MPlus CMI ประหยัด ฿260,000/ปี

[แนบ case study PDF 1 หน้า]
```

**Email 4 (Day 14) — Urgency (Founding offer)**
```
Subject: เหลือ X ที่นั่งใน Founding 50 ครับ

ตอนนี้มีสมาชิกใหม่เข้าร่วม Founding offer แล้ว X รายใน 2 สัปดาห์
เหลืออีก 50-X ที่นั่ง (ปิดรับ 31 ก.ค.)

Lock ราคา 50% off 12 เดือน = ฿2,490 แทน ฿4,990
```

**Email 5 (Day 21) — Break-up**
```
Subject: คงไม่เหมาะกับ [ชื่อองค์กร] ใช่ไหมครับ?

ถ้ายังไม่ใช่ช่วงเวลาที่เหมาะ ไม่เป็นไรครับ — ผมจะไม่รบกวนอีก

ขอฝากไว้: [link ROI calculator] ใช้ได้ตลอดเมื่อพี่มีเวลาคิด
ถ้าอยากคุยในอนาคต ทักผมได้ทุกเวลาครับ

ขอบคุณที่สละเวลาอ่านครับ
```

### 📞 Phone Call Script (15 นาที)

```
1. Opening (2 นาที)
   - แนะนำตัว + อ้างอิง (ใครแนะนำ / email ที่ส่งไปแล้ว)
   - ถามขออนุญาต 15 นาที

2. Discovery (5 นาที)
   - ศูนย์ทำอะไรบ้าง (RR? VCT? Lab?)
   - เคสต่อเดือน
   - ใครบันทึก NAP?
   - เคยเจอปัญหาตกเคลมไหม

3. Demo + Value (5 นาที)
   - แชร์จอ / เล่า workflow
   - เล่า case study ที่ตรงกับขนาดของเขา
   - คำนวณ ROI

4. Close (3 นาที)
   - "Founding offer ลด 50% ถึง 31 ก.ค.
     สนใจเริ่ม trial 30 วันเลยไหมครับ?"
   - ถ้าสนใจ → ส่ง link สมัคร + นัด onboarding
   - ถ้าไม่ → ถามว่าติดขัดอะไร (price / feature / ยังไม่พร้อม)
```

---

## 8. 90-Day Execution Plan

### Month 1 — Foundation

- [ ] Export 117 targets → CRM (Sheets/Airtable)
- [ ] Segment ลูกค้าปัจจุบัน 17 sites ให้ตรง CPP hcodes (ดู task ถัดไป)
- [ ] ร่าง landing page draft
- [ ] เขียน 3 case studies (Caremat, MPlus, RSAT)
- [ ] ทำ ROI calculator
- [ ] เขียน email sequence template
- [ ] ขอ testimonial video จาก 2 ลูกค้า
- [ ] เริ่ม warm outreach — target 10 sites จาก 17 network → refer 20+ leads

**KPI เดือน 1:** 5 demos, 3 paying customers → ARR ฿120k

### Month 2 — Launch

- [ ] Landing page LIVE
- [ ] ประกาศ Founding 50 (เปิดรับ)
- [ ] เริ่ม cold email sequence → 30 sites ต่อสัปดาห์
- [ ] Webinar #1 (demo + Q&A)
- [ ] เริ่มเขียน blog + Facebook content
- [ ] Social media (LINE OA, FB page)

**KPI เดือน 2:** 10 demos, 5 new customers → ARR ฿240k

### Month 3 — Optimize

- [ ] วิเคราะห์ funnel (conversion rate each stage)
- [ ] ปรับ messaging ตาม feedback
- [ ] เปิด AutoNAP RR Lite (MVP) ให้ Track A 1–2 sites ทดลอง
- [ ] Webinar #2
- [ ] พัฒนา partner channel (CAREMAT, HIVQual)
- [ ] Review Founding 50 progress (target 50% filled)

**KPI เดือน 3:** 15 demos, 5 new customers → Total 13 sites → ARR ฿360k

---

## 9. Content Calendar — 4 Months

| Week | Content Type | หัวข้อ |
|---|---|---|
| 1 | Blog | "5 วิธีลดเวลาบันทึก NAP ให้ทันเคลม" |
| 2 | Case Study | "MPlus CMI ประหยัด ฿260k/ปี" |
| 3 | Video | "Demo AutoNAP ใน 90 วินาที" |
| 4 | Webinar | "เทคนิคจัดการ UIC race condition" |
| 5 | Blog | "PDPA กับการบันทึก NAP — ต้องรู้อะไรบ้าง" |
| 6 | Video | "วันในชีวิตของเจ้าหน้าที่บันทึก NAP" |
| 7 | Blog | "CSV Upload vs. Integration — แบบไหนเหมาะกับศูนย์คุณ" |
| 8 | Webinar | "HIV NGO network best practices" |
| 9 | Case Study | "ชมรมรักษ์ม่วง — ลดเวลาอาสา 10 ชม./สัปดาห์" |
| 10 | Video | "ROI Calculator walkthrough" |
| 11 | Blog | "อนาคตของการบันทึก NAP — AI + automation" |
| 12 | Webinar | "Year 1 recap — 30 sites ใช้ AutoNAP แล้ว" |

---

## 10. KPIs ที่ต้องวัด

### Acquisition
- Leads / เดือน (target 50)
- Demo booked / lead (target 30%)
- Close rate / demo (target 40%)
- CAC per customer (target < ฿8,000)

### Product
- Time to first value (target < 24 ชม.)
- Setup completion rate (target 80%+)
- Cases processed / customer / เดือน

### Retention
- MRR churn (target < 5%)
- NPS (target > 50)

### Revenue
- MRR / ARR
- ARPU (target ฿3,000)
- LTV (target > ฿100,000)
- LTV:CAC ratio (target > 12x)

---

## 11. Risks & Mitigation

| Risk | Impact | Mitigation |
|---|---|---|
| สปสช. เปลี่ยน NAP UI | สูง | Monitoring + retainer dev + ลูกค้าใช้ต่อเพราะ switching cost |
| คู่แข่งโคลน (CAREMAT ทำเอง) | กลาง | Moat = 17 reference + domain knowledge + speed |
| Cold outreach fatigue | กลาง | Stop at 5 touches, respect opt-out, use partners |
| Price too high | กลาง | ROI calculator + Founding discount |
| Price too low | ต่ำ | Grand opening ราคา, ขยับขึ้นหลัง year 1 |
| PDPA violation | สูง | Data localization + audit log + consent flow |
| Track A (กลุ่ม/ชมรม) ไม่มีเงินจ่าย | กลาง | RR Lite ราคาต่ำ + กลุ่มใหญ่ค่อยสนับสนุนกลุ่มเล็ก |

---

## 12. Next Actions (this week)

1. ⬜ **Commit + deploy 117 ecosystem data ลง VPS** (ถ้ายังไม่ได้ทำ)
2. ⬜ **Map 17 ลูกค้าปัจจุบัน → CPP hcodes** เพื่อรู้ sister entities
3. ⬜ **เลือก 10 flagship targets** จาก 117 ให้ warm outreach ก่อน
4. ⬜ **ร่าง landing page copy** (Hero, pricing, CTA)
5. ⬜ **ถ่าย case study video** กับ Caremat + MPlus CMI
6. ⬜ **ทำ ROI calculator** (Google Sheet / HTML)
7. ⬜ **เปิด Founding 50 announcement** (Facebook post + email)
8. ⬜ **ติดตั้ง CRM** (Airtable / Notion / HubSpot free)

---

## ไฟล์ที่เกี่ยวข้อง

- `marketing.md` — Big picture GTM plan (เอกสารแม่)
- `docs/research/cpp_hiv_ecosystem.csv` — **117 target list**
- `docs/research/cpp_hiv_ecosystem.md` — Segmentation analysis
- `docs/research/hiv_providers_merged.md` — Cross-ref with old prospects
- `docs/csv_templates/` — Customer-facing CSV templates
- `docs/cpp_scraping.md` — Technical detail ของ scraper

---

**Version:** 1.0
**เขียนเมื่อ:** 15 เมษายน 2569
**Owner:** Sales/Growth
