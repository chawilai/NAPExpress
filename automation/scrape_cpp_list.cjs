#!/usr/bin/env node
/**
 * Phase 1: Scrape cpp.nhso.go.th/search/ to enumerate all providers.
 *
 * Parses each row's raw text and POSTs to AutoNAP API in batches to
 * create cpp_providers (basic fields) + seed cpp_scrape_queue.
 *
 * Phase 2 (scrape_cpp_profile.cjs workers) enriches each row later with
 * registration_type, network_types, coordinators, etc.
 *
 * Usage:
 *   node automation/scrape_cpp_list.cjs [--api=http://localhost:8000] [--start-page=1] [--end-page=99999] [--delay-ms=2500] [--batch=50]
 */
const { chromium } = require('playwright');
const http = require('http');
const https = require('https');

const args = Object.fromEntries(
    process.argv.slice(2).map((a) => {
        const [k, v] = a.replace(/^--/, '').split('=');
        return [k, v ?? true];
    })
);

const API_BASE = args.api ?? 'http://localhost:8000';
const START_PAGE = parseInt(args['start-page'] ?? '1', 10);
const END_PAGE = parseInt(args['end-page'] ?? '99999', 10);
const DELAY_MS = parseInt(args['delay-ms'] ?? '2500', 10);
const BATCH_SIZE = parseInt(args['batch'] ?? '50', 10);
const HEADLESS = args['headless'] !== 'false';

const log = (...m) => console.error('[list]', ...m);

(async () => {
    log('starting — pages', START_PAGE, '→', END_PAGE, '| api', API_BASE, '| batch', BATCH_SIZE);

    const browser = await chromium.launch({ headless: HEADLESS });
    const context = await browser.newContext({
        locale: 'th-TH',
        userAgent:
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    });
    const page = await context.newPage();

    let batch = [];
    const seenHcodes = new Set();
    let totalSeen = 0;
    let totalDupes = 0;
    let totalSent = 0;
    let totalFailed = 0;

    try {
        await page.goto('https://cpp.nhso.go.th/search/', {
            waitUntil: 'networkidle',
            timeout: 60000,
        });

        await page.waitForSelector('.ui-datatable-data tr', { timeout: 30000 });

        let currentPage = 1;

        while (currentPage < START_PAGE) {
            if (!(await clickNextPage(page))) {
                log('couldnt skip to start page', START_PAGE);
                break;
            }
            currentPage++;
        }

        while (currentPage <= END_PAGE) {
            await page.waitForSelector('.ui-datatable-data tr', { timeout: 30000 });
            await page.waitForTimeout(400);

            const rows = await extractPageRows(page);

            if (rows.length === 0) {
                log('no rows on page', currentPage, '- end of results');
                break;
            }

            const parsed = rows
                .map(parseRawCell)
                .filter((r) => r && r.hcode);

            let newInPage = 0;
            let dupeInPage = 0;

            for (const r of parsed) {
                if (seenHcodes.has(r.hcode)) {
                    dupeInPage++;
                    continue;
                }
                seenHcodes.add(r.hcode);
                batch.push(r);
                newInPage++;
            }

            totalSeen += newInPage;
            totalDupes += dupeInPage;

            log(
                `page ${currentPage}: +${newInPage} new` +
                    (dupeInPage ? ` (${dupeInPage} dupes)` : '') +
                    ` | unique ${totalSeen}`
            );

            if (batch.length >= BATCH_SIZE) {
                const slice = batch.splice(0, BATCH_SIZE);
                const result = await sendBatch(slice);
                if (result) {
                    totalSent += slice.length;
                    log(
                        `  → sent batch [${totalSent - slice.length + 1}..${totalSent}]`,
                        `created=${result.providers_created}`,
                        `updated=${result.providers_updated}`,
                        `queued=${result.queue_created}`
                    );
                } else {
                    totalFailed += slice.length;
                    log(`  ✗ batch send failed — ${slice.length} rows lost`);
                }
            }

            if (!(await clickNextPage(page))) {
                log('no next button — end at page', currentPage);
                break;
            }

            currentPage++;
            await page.waitForTimeout(DELAY_MS);
        }

        // Flush remaining
        if (batch.length > 0) {
            const result = await sendBatch(batch);
            if (result) {
                totalSent += batch.length;
                log(`flush: sent ${batch.length} remaining`);
            }
        }

        log(
            `done — unique ${totalSeen}, dupes ${totalDupes}, sent ${totalSent}, failed ${totalFailed}`
        );
    } catch (e) {
        log('ERROR:', e.message, e.stack);
        process.exitCode = 1;
    } finally {
        await browser.close();
    }
})();

async function extractPageRows(page) {
    return await page.evaluate(() => {
        const rows = [];
        const trs = document.querySelectorAll('.ui-datatable-data tr');

        for (const tr of trs) {
            if (tr.classList.contains('ui-datatable-empty-message')) continue;
            const tds = tr.querySelectorAll('td');
            if (tds.length < 2) continue;
            const cells = Array.from(tds).map((td) => td.innerText.trim());
            rows.push(cells);
        }
        return rows;
    });
}

function parseRawCell(cells) {
    // Take the first non-empty cell (sometimes duplicated across multiple columns)
    const text = (cells.find((c) => c && c.length > 5) || '').trim();

    if (!text) return null;

    const lines = text.split('\n').map((l) => l.trim()).filter(Boolean);
    const result = {
        hcode: null,
        name: null,
        phone: null,
        address_no: null,
        moo: null,
        soi: null,
        road: null,
        subdistrict: null,
        district: null,
        province: null,
        postal_code: null,
    };

    for (const line of lines) {
        let m;

        if ((m = line.match(/^\(([^)]+)\)\s*(.*)$/))) {
            result.hcode = m[1].trim();
            result.name = (m[2] || '').trim() || null;
            continue;
        }

        if ((m = line.match(/^เบอร์โทรศัพท์\s*:\s*(.+)$/))) {
            const phone = m[1].trim();
            result.phone = phone === '-' || phone === '' ? null : phone;
            continue;
        }

        if ((m = line.match(/^ที่อยู่\s*:\s*(.+)$/))) {
            Object.assign(result, parseAddress(m[1]));
        }
    }

    return result;
}

function parseAddress(addr) {
    const out = {
        address_no: null,
        moo: null,
        soi: null,
        road: null,
        subdistrict: null,
        district: null,
        province: null,
        postal_code: null,
    };

    const keyMap = {
        เลขที่: 'address_no',
        หมู่: 'moo',
        ซอย: 'soi',
        ถนน: 'road',
        ตำบล: 'subdistrict',
        อำเภอ: 'district',
        จังหวัด: 'province',
        รหัสไปรษณีย์: 'postal_code',
    };

    const keys = Object.keys(keyMap);
    const keysAlt = keys.join('|');
    const re = new RegExp(`(${keysAlt})\\s+(.+?)(?=\\s+(?:${keysAlt})|\\s*$)`, 'g');

    let m;
    while ((m = re.exec(addr)) !== null) {
        const [, keyword, value] = m;
        const field = keyMap[keyword];
        const v = (value || '').trim();
        if (v && v !== '-') {
            out[field] = v;
        }
    }

    return out;
}

async function sendBatch(items) {
    try {
        return await apiPost('/api/cpp-scrape/bulk-upsert', { items });
    } catch (e) {
        log('api error:', e.message);
        // Retry once after 5s
        try {
            await new Promise((r) => setTimeout(r, 5000));
            return await apiPost('/api/cpp-scrape/bulk-upsert', { items });
        } catch (e2) {
            log('api retry failed:', e2.message);
            return null;
        }
    }
}

async function clickNextPage(page) {
    const nextBtn = await page.$('.ui-paginator-next:not(.ui-state-disabled)');
    if (!nextBtn) return false;
    await nextBtn.click();
    await page
        .waitForFunction(
            () => {
                const loader = document.querySelector('.ui-datatable-loading');
                return !loader || loader.style.display === 'none';
            },
            { timeout: 15000 }
        )
        .catch(() => {});
    await page.waitForTimeout(300);
    return true;
}

function apiPost(path, body) {
    return new Promise((resolve, reject) => {
        const url = new URL(API_BASE + path);
        const lib = url.protocol === 'https:' ? https : http;
        const payload = JSON.stringify(body);

        const req = lib.request(
            {
                method: 'POST',
                hostname: url.hostname,
                port: url.port || (url.protocol === 'https:' ? 443 : 80),
                path: url.pathname + url.search,
                headers: {
                    'Content-Type': 'application/json',
                    'Content-Length': Buffer.byteLength(payload),
                    Accept: 'application/json',
                },
                timeout: 30000,
            },
            (res) => {
                let buf = '';
                res.on('data', (c) => (buf += c));
                res.on('end', () => {
                    try {
                        resolve(JSON.parse(buf));
                    } catch (e) {
                        reject(new Error('bad JSON: ' + buf.slice(0, 200)));
                    }
                });
            }
        );

        req.on('error', reject);
        req.on('timeout', () => req.destroy(new Error('timeout')));
        req.write(payload);
        req.end();
    });
}
