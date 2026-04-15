#!/usr/bin/env node
/**
 * Phase 2: Scrape individual provider profile from cpp.nhso.go.th/profile/?hcode=XXX
 *
 * Can be called in 2 modes:
 *   1. Single hcode: node scrape_cpp_profile.cjs --hcode=41936
 *   2. Worker mode:  node scrape_cpp_profile.cjs --worker --worker-id=1 --api=http://localhost:8000
 *
 * In worker mode, it polls a Laravel API endpoint for next pending hcode,
 * scrapes it, posts the result back, repeats.
 *
 * Outputs JSON to stdout (single mode) or to API (worker mode).
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

const MODE_WORKER = !!args.worker;
const WORKER_ID = args['worker-id'] ?? 'w1';
const API_BASE = args.api ?? 'http://localhost:8000';
const DELAY_MS = parseInt(args['delay-ms'] ?? '3000', 10);
const HEADLESS = args['headless'] !== 'false';
const MAX_ATTEMPTS = parseInt(args['max-retries'] ?? '3', 10);

const log = (...m) => console.error(`[profile:${WORKER_ID}]`, ...m);

(async () => {
    const browser = await chromium.launch({ headless: HEADLESS });
    const context = await browser.newContext({
        locale: 'th-TH',
        userAgent:
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    });
    const page = await context.newPage();

    try {
        if (MODE_WORKER) {
            log('starting worker mode — API:', API_BASE);
            await runWorker(page);
        } else {
            const hcode = args.hcode;

            if (!hcode) {
                console.error('Usage: --hcode=XXX or --worker');
                process.exit(1);
            }

            const data = await scrapeProfile(page, hcode);
            console.log(JSON.stringify(data, null, 2));
        }
    } catch (e) {
        log('FATAL:', e.message, e.stack);
        process.exitCode = 1;
    } finally {
        await browser.close();
    }
})();

async function runWorker(page) {
    let emptyCount = 0;

    while (true) {
        const next = await apiClaim();

        if (!next || !next.hcode) {
            emptyCount++;

            if (emptyCount > 5) {
                log('no more work — exiting');
                return;
            }

            log(`no pending work (${emptyCount}/5), waiting 30s...`);
            await sleep(30000);
            continue;
        }

        emptyCount = 0;
        const hcode = next.hcode;

        log('claimed', hcode);

        let attempt = 0;
        let data = null;
        let error = null;
        let accessDenied = false;

        while (attempt < MAX_ATTEMPTS) {
            attempt++;

            try {
                data = await scrapeProfile(page, hcode);
                break;
            } catch (e) {
                error = e;

                if (e.code === 'ACCESS_DENIED') {
                    accessDenied = true;
                    break; // no point retrying
                }

                log(`attempt ${attempt} failed for ${hcode}:`, e.message);
                await sleep(DELAY_MS * attempt);
            }
        }

        if (data) {
            await apiReport(hcode, 'done', data);
            log('completed', hcode, '-', data.name || '(no name)');
        } else if (accessDenied) {
            await apiReport(hcode, 'not_found', null, 'access denied');
            log('access denied', hcode);
        } else {
            await apiReport(hcode, 'failed', null, error?.message);
            log('gave up', hcode);
        }

        await sleep(DELAY_MS);
    }
}

class AccessDeniedError extends Error {
    constructor(hcode) {
        super(`access denied for hcode ${hcode}`);
        this.code = 'ACCESS_DENIED';
    }
}

async function scrapeProfile(page, hcode) {
    const url = `https://cpp.nhso.go.th/profile/?hcode=${encodeURIComponent(hcode)}`;

    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 });

    // Check for access-denied error page before waiting for profile content
    const bodyText = await page.evaluate(() => document.body.innerText || '');

    if (bodyText.includes('ไม่เข้าถึงข้อมูลหน้าจอนี้ได้') || bodyText.includes('ไม่สามารถเข้าถึง')) {
        throw new AccessDeniedError(hcode);
    }

    // Wait for lazy-loaded content
    await page.waitForSelector('#normalArticle', { timeout: 15000 });

    await page
        .waitForFunction(
            () => {
                const panel = document.querySelector('#normalArticle');
                return panel && panel.innerText.includes('ชื่อ');
            },
            { timeout: 10000 }
        )
        .catch(() => {});

    // Click network type dialog (fire-and-forget) — data usually loads in <500ms
    try {
        await page.evaluate(() => {
            if (typeof remote === 'function') {
                remote();
            }
        });
        await page.waitForTimeout(600);
    } catch (e) {
        /* ignore */
    }

    return await page.evaluate((hc) => {
        const txt = (sel) => {
            const el = document.querySelector(sel);
            return el ? el.innerText.trim() : null;
        };

        // Extract "label: value" pairs from a table under a given container
        const tableKV = (containerId) => {
            const container = document.querySelector(containerId);
            if (!container) return {};
            const out = {};
            const rows = container.querySelectorAll('table.gt-form-grid tr');

            rows.forEach((tr) => {
                const tds = tr.querySelectorAll('td');

                for (let i = 0; i < tds.length - 1; i += 2) {
                    const label = tds[i].innerText.trim();
                    const value = tds[i + 1].innerText.trim();

                    if (label) {
                        out[label] = value;
                    }
                }
            });
            return out;
        };

        const general = tableKV('#normalArticle');
        const address = tableKV('#addressInfoPanel');

        // Coordinators
        const coordinators = [];
        const coordRows = document.querySelectorAll('#coordinatorTable_data tr');

        coordRows.forEach((tr) => {
            if (tr.classList.contains('ui-datatable-empty-message')) return;
            const tds = tr.querySelectorAll('td');
            if (tds.length < 7) return;

            coordinators.push({
                name: tds[1]?.innerText.trim() || null,
                email: tds[2]?.innerText.trim() || null,
                phone: tds[3]?.innerText.trim() || null,
                mobile: tds[4]?.innerText.trim() || null,
                fax: tds[5]?.innerText.trim() || null,
                department: tds[6]?.innerText.trim() || null,
            });
        });

        // Network types (from dialog table)
        const networkTypes = [];
        const netRows = document.querySelectorAll('#table_data tr');

        netRows.forEach((tr) => {
            if (tr.classList.contains('ui-datatable-empty-message')) return;
            const tds = tr.querySelectorAll('td');
            if (tds.length < 3) return;

            networkTypes.push({
                type_code: tds[1]?.innerText.trim() || null,
                type_name: tds[2]?.innerText.trim() || null,
            });
        });

        // Last updated date
        const footer = document.querySelector('#gtBodyFooter2');
        const lastUpdated = footer ? footer.innerText.match(/วันที่\s+([^\s(]+\s+\S+\s+\d+)/)?.[1] : null;

        // Registration type badge (red button next to provider name)
        const badgeEl = document.querySelector('.networkTypeLable span.lable, .networkTypeLable .lable span.lable');
        let registrationType = badgeEl ? badgeEl.innerText.trim() : null;

        // Fallback: the top-right status line
        if (!registrationType) {
            const statusText = txt('#gtProfileStatus');
            if (statusText) {
                registrationType = statusText.replace(/^ประเภทการขึ้นทะเบียน\s*:\s*/, '').trim() || null;
            }
        }

        return {
            hcode: hc,
            name: txt('#gtProfileName'),
            registration_type: registrationType,
            affiliation: general['ข้อมูลสังกัด'] || null,
            phone: general['เบอร์โทรศัพท์'] || null,
            website: general['เว็บไซต์']?.replace(/^-$/, '') || null,
            service_plan_level: general['การจัดระดับตาม service plan']?.replace(/^-$/, '') || null,
            operating_hours: general['เวลาเปิดให้บริการ'] || null,

            // Address
            address_no: address['เลขที่'] || null,
            moo: address['หมู่']?.replace(/^-$/, '') || null,
            soi: address['ซอย']?.replace(/^-$/, '') || null,
            road: address['ถนน']?.replace(/^-$/, '') || null,
            subdistrict: address['ตำบล'] || null,
            district: address['อำเภอ'] || null,
            province: address['จังหวัด'] || null,
            postal_code: address['รหัสไปรษณีย์'] || null,
            local_admin_area: address['พื้นที่การปกครองของท้องถิ่น']?.replace(/^-$/, '') || null,
            uc_phone: address['เบอร์โทรศูนย์บริการหลักประกันสุขภาพ']?.replace(/^-$/, '') || null,
            quality_phone: address['เบอร์โทรงานประกันคุณภาพ']?.replace(/^-$/, '') || null,
            referral_phone: address['เบอร์โทรศูนย์ประสานงานการส่งต่อ']?.replace(/^-$/, '') || null,
            uc_fax: address['โทรสารศูนย์บริการหลักประกันสุขภาพ']?.replace(/^-$/, '') || null,
            uc_email: address['Email ศูนย์บริการหลักประกันสุขภาพ']?.replace(/^-$/, '') || null,
            doc_email: address['Email งานสารบรรณหน่วยบริการ']?.replace(/^-$/, '') || null,

            coordinators,
            network_types: networkTypes,
            cpp_last_updated: lastUpdated,
            scraped_at: new Date().toISOString(),
        };
    }, hcode);
}

function apiClaim() {
    return apiPost('/api/cpp-scrape/claim', { worker_id: WORKER_ID });
}

function apiReport(hcode, status, data, error) {
    return apiPost('/api/cpp-scrape/report', {
        worker_id: WORKER_ID,
        hcode,
        status,
        data,
        error,
    });
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
                        const parsed = JSON.parse(buf);
                        resolve(parsed);
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

function sleep(ms) {
    return new Promise((r) => setTimeout(r, ms));
}
