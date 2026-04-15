# CPP Provider Scraping

ระบบดึงข้อมูลหน่วยบริการจาก `cpp.nhso.go.th` เข้า local database เพื่อใช้เป็นฐานลูกค้าเป้าหมายของ AutoNAP

## ภาพรวม

- **แหล่งข้อมูล:** `cpp.nhso.go.th/search/` (~29,309 หน่วยบริการ) + `cpp.nhso.go.th/profile/?hcode=XXX` (รายละเอียด)
- **2 phases:**
  - **Phase 1 (List)** — iterate search pagination → ได้ hcode list
  - **Phase 2 (Profile)** — parallel workers → ดึง detail + coordinator + network types
- **Parallel:** Phase 2 รองรับ N workers ขนาน (default 4)

## โครงสร้าง Database

```
cpp_providers                  — main table (1 row per hcode)
├── cpp_provider_network_types — หลายประเภทต่อ provider
└── cpp_provider_coordinators  — หลายผู้ประสานงานต่อ provider

cpp_scrape_queue               — work queue สำหรับ worker distribution
```

## Files

| File | หน้าที่ |
|---|---|
| `database/migrations/..._create_cpp_providers_tables.php` | Schema |
| `app/Models/CppProvider.php` + related | Eloquent models |
| `app/Http/Controllers/Api/CppScrapeController.php` | Worker API (claim/report/status) |
| `app/Console/Commands/CppScrape.php` | Artisan command |
| `automation/scrape_cpp_list.cjs` | Phase 1 — list scraper |
| `automation/scrape_cpp_profile.cjs` | Phase 2 — profile scraper (worker) |
| `automation/spawn_cpp_workers.sh` | Multi-worker launcher |

## การใช้งาน (Workflow เต็ม)

### Step 1 — Run migration

```bash
php artisan migrate
```

### Step 2 — Phase 1: Enumerate all hcodes

รันครั้งเดียว ใช้เวลา ~2-3 ชั่วโมง

```bash
# Headless (default)
node automation/scrape_cpp_list.cjs --output=automation/cpp_list.jsonl

# ดูการทำงาน (debug)
node automation/scrape_cpp_list.cjs --headless=false --delay-ms=3000

# Range specific pages (ทดสอบ 10 หน้าแรก)
node automation/scrape_cpp_list.cjs --start-page=1 --end-page=10
```

ได้ output file `automation/cpp_list.jsonl` ที่มี JSON ต่อบรรทัด

### Step 3 — Import hcode list ลง queue

```bash
php artisan cpp:scrape import-list --file=automation/cpp_list.jsonl
```

ตรวจสอบ:
```bash
php artisan cpp:scrape status
```

### Step 4 — Phase 2: Parallel profile scraping

**ต้องรัน Laravel server ก่อน** (เพื่อให้ workers เรียก API):

```bash
# Terminal 1 — Laravel server
php artisan serve

# Terminal 2 — Spawn 4 workers
./automation/spawn_cpp_workers.sh 4 http://localhost:8000

# หรือกำหนด delay (ยิ่งน้อย ยิ่งเร็ว แต่เสี่ยงโดน rate limit)
DELAY_MS=2000 ./automation/spawn_cpp_workers.sh 8
```

### Step 5 — Monitor progress

```bash
# Terminal 3
watch -n 10 'php artisan cpp:scrape status'

# หรือดู log ของ workers
tail -f storage/logs/cpp_worker_*.log
```

### Step 6 — Reset stuck claims (ถ้ามี)

```bash
php artisan cpp:scrape reset
```

## คำนวณเวลาโดยประมาณ

Phase 2 (profile scraping):
- 1 provider = ~5 วินาที (navigate + wait for lazy load + extract)
- 4 workers × 60 req/นาที ÷ 4 = ~15 req/วินาที
- **29,309 providers ÷ 15 req/s = ~32 นาที** (ทฤษฎี)
- ในทางปฏิบัติจริง ~2-4 ชั่วโมง (รวม retries, timeouts)

## Query ตัวอย่าง

### หาเฉพาะคลินิกเอกชนในเชียงใหม่

```sql
SELECT hcode, name, phone, district, postal_code
FROM cpp_providers
WHERE province = 'เชียงใหม่'
  AND affiliation = 'เอกชน'
  AND registration_type LIKE '%คลินิก%';
```

### หาหน่วยบริการ HIV (ผ่าน network types)

```sql
SELECT p.hcode, p.name, p.phone, p.province, n.type_name
FROM cpp_providers p
JOIN cpp_provider_network_types n ON n.cpp_provider_id = p.id
WHERE n.type_name LIKE '%HIV%'
   OR n.type_name LIKE '%เอชไอวี%';
```

### หาผู้ประสานงานที่มี email

```sql
SELECT p.name AS provider, c.name AS contact, c.email, c.mobile
FROM cpp_provider_coordinators c
JOIN cpp_providers p ON p.id = c.cpp_provider_id
WHERE c.email IS NOT NULL AND c.email != '';
```

### Export เป็น CSV สำหรับ sales team

```bash
php artisan tinker
> \App\Models\CppProvider::with('coordinators')->get()->each(fn($p) => ...);
```

## ข้อควรระวัง

1. **Rate limiting** — cpp.nhso.go.th เป็นระบบราชการ, อย่ายิงรัวเกิน 1-2 req/วินาทีต่อ worker
2. **Legal** — ข้อมูลเป็น public (ใครเข้าเว็บก็ดูได้) แต่ใช้เพื่อ **business use** ควรอ้าง terms of use ของ NHSO
3. **PDPA** — ข้อมูลผู้ประสานงาน (ชื่อ, email, เบอร์) เป็น personal data → ต้อง compliant ตาม PDPA เมื่อใช้เพื่อ marketing (เช่น ขอ consent ก่อน email, ให้ opt-out ได้)
4. **Data staleness** — NHSO update ข้อมูลไม่ถี่ ควร re-scrape 1-2 ครั้ง/ปี

## Troubleshooting

### `waitForSelector timeout`

- Server ช้า → เพิ่ม `--delay-ms=5000`
- Network slow → run non-headless เพื่อดู

### Workers stuck on same hcode

```bash
php artisan cpp:scrape reset  # ปลด claim ที่ค้าง > 10 นาที
```

### `table not found` error

```bash
php artisan migrate
```

### Rate limit / 503 errors

- ลดจำนวน workers: `./spawn_cpp_workers.sh 2`
- เพิ่ม delay: `DELAY_MS=5000 ./spawn_cpp_workers.sh 4`

## Next Steps

หลังได้ข้อมูลครบ:

1. **Filter ลูกค้าเป้าหมาย** (HIV NGO, คลินิก, รพ. รัฐ ขนาดเล็ก-กลาง)
2. **Merge กับ `hiv_ngo_prospects.csv`** ที่มีอยู่เดิม
3. **ทำ sales CRM** ติดตาม contact status
4. **ใช้ใน AutoNAP** เป็น hcode validation + autocomplete
