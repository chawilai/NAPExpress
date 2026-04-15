# AutoNAP API Authentication

คู่มือเชื่อมต่อระบบภายนอก (เช่น **ACTSE Clinic**) เข้ากับ AutoNAP ผ่าน API

**Base URL:** `https://autonap.actse-clinic.com`

---

## 📋 ภาพรวม — OAuth2 Client Credentials Flow

AutoNAP ใช้รูปแบบ **OAuth2 client_credentials** แบบเดียวกับ Thailand Post, Stripe, ฯลฯ

1. **Long-lived credentials** (`client_id` + `client_secret`) — ออกจาก AutoNAP dashboard, เก็บถาวร
2. **Short-lived access token** — แลกได้จาก credentials, หมดอายุใน 1 ชั่วโมง
3. **ใช้ access token** ใน header `Authorization: Bearer xxx` สำหรับทุก API call

---

## 🔑 Step 1 — ออก API Client ที่ AutoNAP Dashboard

1. Login เข้า `https://autonap.actse-clinic.com/login`
2. ไปที่ **Settings → API Tokens**
3. กด **"สร้าง API Client ใหม่"**
4. ตั้งชื่อ (เช่น `ACTSE Clinic production`)
5. *(ไม่บังคับ)* ใส่ **IP Whitelist** คั่นคอมม่า เช่น `103.117.148.89, 43.229.150.41`
6. กด **"สร้าง"**
7. **คัดลอก `client_id` + `client_secret` เก็บไว้** — ระบบแสดงเพียง**ครั้งเดียว**

```
client_id:     acs_1aBcDeFgHiJkLmNoPqRsTuVwXyZ01234
client_secret: acsk_ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890abcd
```

⚠️ **หาย = สร้างใหม่เท่านั้น**

---

## 🎫 Step 2 — ขอ Access Token

**Endpoint:** `POST /api/auth/token`

**Headers:**
```
Content-Type: application/json
Accept: application/json
```

**Body:**
```json
{
  "client_id": "acs_1aBcDeFgHiJkLmNoPqRsTuVwXyZ01234",
  "client_secret": "acsk_ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890abcd"
}
```

**Response (200):**
```json
{
  "access_token": "at_OcnSVv8DDhuBvPI5MfL9XFeMOVqfOlLpFBiCsuaCGgqX",
  "token_type": "Bearer",
  "expires_in": 3600,
  "scopes": ["api:write"]
}
```

---

## 🔐 Step 3 — ใช้ Access Token

ใส่ access token ใน header ทุก API call:

```
Authorization: Bearer at_OcnSVv8DDhuBvPI5MfL9XFeMOVqfOlLpFBiCsuaCGgqX
```

ตัวอย่าง verify:
```bash
curl -H "Authorization: Bearer $ACCESS_TOKEN" \
     https://autonap.actse-clinic.com/api/auth/me
```

---

## 🔄 Token Lifecycle

| Event | Action |
|---|---|
| Access token หมดอายุ (1 ชม.) | เรียก `/api/auth/token` ใหม่ |
| Credentials หาย | สร้าง client ใหม่ + revoke เก่า |
| ไม่ต้องการใช้แล้ว | `POST /api/auth/revoke` หรือ revoke client ใน dashboard |

### Best Practice
- **Cache access token** ด้วย TTL 55 นาที (อย่าเรียก `/token` ทุกครั้ง)
- **Retry logic** — ถ้า 401 → เรียก `/token` ใหม่ → retry
- **Secret management** — เก็บใน env var หรือ secret manager (อย่าใส่ใน git)

---

## 🛠️ Integration Example — Laravel

```php
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AutoNapClient
{
    private const BASE_URL = 'https://autonap.actse-clinic.com';

    public function getAccessToken(): string
    {
        return Cache::remember('autonap_access_token', now()->addMinutes(55), function () {
            $res = Http::post(self::BASE_URL.'/api/auth/token', [
                'client_id' => config('services.autonap.client_id'),
                'client_secret' => config('services.autonap.client_secret'),
            ])->throw();

            return $res['access_token'];
        });
    }

    public function submitJob(array $payload): array
    {
        return Http::withToken($this->getAccessToken())
            ->post(self::BASE_URL.'/api/jobs', $payload)
            ->throw()
            ->json();
    }
}
```

## 🛡️ Security

1. **HTTPS only** — TLS 1.2+ เท่านั้น
2. **IP whitelist** — แนะนำใส่ IP ของ production
3. **Secret rotation** — rotate ทุก 6-12 เดือน
4. **Rate limit** — `/api/auth/token` limit 20 req/min

---

## 📞 Support

- Email: `support@autonap.co.th`
- Dashboard: `https://autonap.actse-clinic.com/settings/api-tokens`
