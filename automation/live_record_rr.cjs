const { chromium } = require('playwright');
const fs = require('fs');

/**
 * LIVE: Record NAP RR + capture all network requests for Direct HTTP analysis.
 *
 * This script:
 * 1. Logs into NAP Plus
 * 2. Fills the RR form with real data from CAREMAT API
 * 3. Actually SAVES the record
 * 4. Captures the RR code
 * 5. Logs ALL HTTP requests/responses for reverse-engineering Direct HTTP POST
 */

const CREDENTIALS = {
    username: '65127054298057',
    password: 'mpluscmi003',
};

const RR_FORM = {
    rrttrDate: '04/03/2569',
    pid: '1550700153989',
    birth_date_thai: '16/07/2542',
    risk_behavior_indices: [1],
    target_group_indices: [0],
    partner_with: null,
    access_type: '2',
    social_media: null,
    ref_tel: '0617978524',
    ref_email: '',
    occupation: '01',
    condom: { '49': 0, '52': 20, '53': 0, '54': 20, '56': 20 },
    lubricant: 20,
    female_condom: 0,
    next_hcode: '41681',
    knowledge_indices: [0, 1, 2],
    place_indices: [0, 1, 2],
    ppe_indices: [0, 2],
    forwards: { hiv: 2, sti: 2, tb: 2, hcv: 3, methadone: 3 },
    sw_type: null,
    pay_by: '1',
};

const networkLog = [];
let stepCount = 0;

function log(msg) {
    console.log(`[${++stepCount}] ${msg}`);
}

async function delay(ms) {
    return new Promise((r) => setTimeout(r, ms));
}

async function run() {
    const browser = await chromium.launch({
        headless: false,
        slowMo: 150,
        args: ['--window-size=1400,900'],
    });

    const context = await browser.newContext({
        viewport: { width: 1400, height: 900 },
        locale: 'th-TH',
        timezoneId: 'Asia/Bangkok',
        geolocation: { latitude: 13.7563, longitude: 100.5018 },
        permissions: ['geolocation'],
    });
    context.setDefaultTimeout(20000);

    const page = await context.newPage();

    // ===== Capture ALL network requests =====
    page.on('request', (req) => {
        const url = req.url();
        if (!url.includes('nhso.go.th')) return; // only NAP requests

        const entry = {
            timestamp: new Date().toISOString(),
            step: stepCount,
            method: req.method(),
            url: url,
            headers: req.headers(),
            postData: req.postData() || null,
        };
        networkLog.push(entry);

        if (req.method() === 'POST') {
            console.log(`  📡 POST ${url.split('?')[0]}`);
            if (req.postData()) {
                console.log(`     Body: ${req.postData().substring(0, 200)}`);
            }
        }
    });

    page.on('response', async (res) => {
        const url = res.url();
        if (!url.includes('nhso.go.th')) return;

        const entry = networkLog.find(
            (e) => e.url === url && !e.responseStatus
        );
        if (entry) {
            entry.responseStatus = res.status();
            entry.responseHeaders = res.headers();
            // Capture response body for key pages
            if (res.status() === 200 && url.includes('createRRTTR')) {
                try {
                    const body = await res.text();
                    entry.responseBodyLength = body.length;
                    // Save key snippets
                    if (body.includes('RR-')) {
                        const match = body.match(/RR-\d{2,4}-\d+/);
                        if (match) entry.rrCodeFound = match[0];
                    }
                } catch {}
            }
        }
    });

    // Handle dialogs
    page.on('dialog', async (dlg) => {
        log(`⚠️ Dialog: ${dlg.type()} — "${dlg.message()}"`);
        await dlg.accept();
    });

    try {
        // ===== Step 1: Login =====
        log('Login to NAP Plus...');
        await page.goto('https://dmis.nhso.go.th/NAPPLUS/login.jsp', { waitUntil: 'domcontentloaded' });
        await page.fill('input[name="user_name"]', CREDENTIALS.username);
        await page.fill('input[name="password"]', CREDENTIALS.password);
        await page.click('input[type="submit"]');
        await page.waitForURL('**/NAPPLUS/login.do', { timeout: 15000 });
        log('✅ Login successful');

        // ===== Step 2: Navigate to Create RR =====
        log('Navigate to Create RR...');
        await page.goto('https://dmis.nhso.go.th/NAPPLUS/rrttr/createRRTTR.do?actionName=load', {
            waitUntil: 'domcontentloaded',
        });
        await page.waitForLoadState('networkidle').catch(() => {});
        log('✅ Search page loaded');

        // ===== Step 3: Search person =====
        log(`Search PID: ${RR_FORM.pid}, Date: ${RR_FORM.rrttrDate}`);
        await page.fill('input[name="rrttrDate"]', RR_FORM.rrttrDate);
        await page.fill('input[name="pid"]', RR_FORM.pid);
        await page.click('text=เพิ่มข้อมูลให้บริการ');
        await page.waitForLoadState('networkidle').catch(() => {});
        await delay(1500);

        const isConfirm = await page.isVisible('text=แสดงข้อมูลสิทธิการรักษา');
        if (!isConfirm) {
            // Check for error
            const errText = await page.evaluate(() => {
                const td = document.querySelector('table.alert td.text');
                return td ? td.textContent.trim() : null;
            });
            if (errText) {
                log(`❌ NAP Error: ${errText}`);
                throw new Error(errText);
            }
            throw new Error('ไม่พบหน้ายืนยันข้อมูล');
        }
        log('✅ Confirm page — clicking ตกลง');

        await page.screenshot({ path: 'automation/screenshots/live_01_confirm.png' });

        // ===== Step 4: Click ตกลง =====
        await page.click('input[name="registerBtn"]');
        await page.waitForURL('**/createRRTTR.do', { timeout: 15000 });
        await page.waitForLoadState('networkidle').catch(() => {});
        await delay(1500);

        log('✅ RR Form loaded — filling...');
        await page.screenshot({ path: 'automation/screenshots/live_02_form_empty.png', fullPage: true });

        // ===== Step 5: Fill form =====

        // Risk Behavior
        for (const idx of RR_FORM.risk_behavior_indices) {
            await page.check(`#rrttr_risk_behavior_status_${idx}`).catch(() => {});
        }

        // Target Group
        for (const idx of RR_FORM.target_group_indices) {
            await page.check(`#rrttr_target_group_status_${idx}`).catch(() => {});
        }

        // Access Type
        await page.check(`#access_type_${RR_FORM.access_type}`).catch(() => {});

        // Pay by
        if (RR_FORM.pay_by) {
            await page.selectOption('#pay_by', RR_FORM.pay_by).catch(() => {});
        }

        // Phone
        if (RR_FORM.ref_tel) await page.fill('#ref_tel', RR_FORM.ref_tel).catch(() => {});

        // Occupation
        if (RR_FORM.occupation) {
            await page.selectOption('#occupation', RR_FORM.occupation).catch(() => {});
        }

        // Knowledge
        for (const idx of RR_FORM.knowledge_indices) {
            await page.check(`#rrttr_knowledge_status_${idx}`).catch(() => {});
        }

        // Place
        for (const idx of RR_FORM.place_indices) {
            await page.check(`#rrttr_place_status_${idx}`).catch(() => {});
        }

        // PPE
        for (const idx of RR_FORM.ppe_indices) {
            await page.check(`#rrttr_ppe_status_${idx}`).catch(() => {});
        }

        // Condom — unhide + fill
        await page.evaluate(() => {
            ['49', '52', '53', '54', '56'].forEach((size) => {
                document.querySelectorAll(`#lb_condom_amount_${size}_1, #lb_condom_amount_${size}_2`)
                    .forEach((e) => { e.style.display = 'inline'; });
                const input = document.querySelector(`#rrttr_condom_amount_${size}`);
                if (input) input.style.display = 'inline';
            });
        });
        for (const [size, amount] of Object.entries(RR_FORM.condom)) {
            if (amount > 0) {
                await page.fill(`#rrttr_condom_amount_${size}`, String(amount)).catch(() => {});
            }
        }

        // Lubricant — unhide separately
        if (RR_FORM.lubricant > 0) {
            await page.evaluate(() => {
                const el = document.querySelector('#rrttr_lubricant_amount');
                if (el) el.style.display = 'inline';
                const lb = document.querySelector('#lb_lubricant_amount');
                if (lb) lb.style.display = 'inline';
            });
            await page.fill('#rrttr_lubricant_amount', String(RR_FORM.lubricant)).catch(() => {});
        }

        // Next hcode
        if (RR_FORM.next_hcode) {
            await page.fill('#next_hcode', RR_FORM.next_hcode).catch(() => {});
        }

        // Forward services
        for (const [svc, val] of Object.entries(RR_FORM.forwards)) {
            if (val) await page.check(`#${svc}_forward_${val}`).catch(() => {});
        }

        log('✅ Form filled');
        await page.screenshot({ path: 'automation/screenshots/live_03_form_filled.png', fullPage: true });

        // ===== Step 6: SAVE (กดบันทึกจริง!) =====
        log('💾 Clicking บันทึก (SAVE)...');
        await page.click('input[name="confirmBtn"]');
        await page.waitForLoadState('networkidle', { timeout: 30000 }).catch(() => {});
        await delay(3000);

        await page.screenshot({ path: 'automation/screenshots/live_04_after_save.png', fullPage: true });

        // ===== Step 7: Preview page — click ตกลง to ACTUALLY save =====
        log('Preview page loaded — clicking ตกลง to confirm save...');

        // Find and click confirm button on preview page
        const confirmClicked = await (async () => {
            // Try various selectors for the final confirm button
            const selectors = [
                'input[name="confirmBtn"]',
                'input[value="ตกลง"]',
                'input[value="บันทึก"]',
                'button:has-text("ตกลง")',
            ];
            for (const sel of selectors) {
                const el = await page.$(sel);
                if (el && await el.isVisible()) {
                    await el.click();
                    return sel;
                }
            }
            return null;
        })();

        if (confirmClicked) {
            log(`Clicked final confirm: ${confirmClicked}`);
        } else {
            // Dump buttons for debugging
            const buttons = await page.evaluate(() =>
                Array.from(document.querySelectorAll('input[type="button"], input[type="submit"], button'))
                    .map(el => ({ tag: el.tagName, name: el.name, value: el.value, type: el.type, visible: el.offsetParent !== null }))
            );
            console.log('Available buttons:', JSON.stringify(buttons, null, 2));
            log('⚠️ Could not find confirm button on preview page');
        }

        await page.waitForLoadState('networkidle', { timeout: 30000 }).catch(() => {});
        await delay(3000);

        await page.screenshot({ path: 'automation/screenshots/live_05_final_result.png', fullPage: true });

        // ===== Step 8: Extract RR Code =====
        const rrCode = await page.evaluate(() => {
            const el = document.querySelector('div.font22 > b');
            return el ? el.textContent.trim() : null;
        });

        if (rrCode) {
            log(`🎉 SUCCESS! RR Code: ${rrCode}`);
        } else {
            // Try to find any result text
            const pageText = await page.evaluate(() => {
                return document.body?.innerText?.substring(0, 1000) || '';
            });
            log(`Result page text: ${pageText.substring(0, 300)}`);

            // Check for errors
            const errText = await page.evaluate(() => {
                const el = document.querySelector('table.alert td.text, .error-message');
                return el ? el.textContent.trim() : null;
            });
            if (errText) {
                log(`❌ Error after save: ${errText}`);
            }
        }

        // ===== Save network log =====
        const logPath = 'automation/screenshots/network_log.json';
        fs.writeFileSync(logPath, JSON.stringify(networkLog, null, 2), 'utf-8');
        log(`📋 Network log saved: ${logPath} (${networkLog.length} requests)`);

        // ===== Print summary of POST requests =====
        console.log('\n' + '='.repeat(60));
        console.log('  NETWORK ANALYSIS — POST Requests to NAP Plus');
        console.log('='.repeat(60));

        const postRequests = networkLog.filter((e) => e.method === 'POST');
        for (const req of postRequests) {
            console.log(`\n  [Step ${req.step}] POST ${req.url}`);
            console.log(`  Status: ${req.responseStatus || '?'}`);
            if (req.postData) {
                console.log(`  Body: ${req.postData.substring(0, 500)}`);
            }
            console.log(`  Cookies: ${(req.headers?.cookie || '').substring(0, 100)}`);
        }

        console.log('\n' + '='.repeat(60));
        console.log(`  RESULT: ${rrCode ? `✅ RR Code = ${rrCode}` : '❌ No RR code found'}`);
        console.log('='.repeat(60));

        // Keep open briefly for inspection
        await delay(5000);
    } catch (error) {
        console.error(`\n❌ Fatal error: ${error.message}`);
        await page.screenshot({ path: 'automation/screenshots/live_error.png', fullPage: true });

        // Save HTML for debugging
        const html = await page.content();
        fs.writeFileSync('automation/screenshots/live_error.html', html);

        // Still save network log
        fs.writeFileSync(
            'automation/screenshots/network_log.json',
            JSON.stringify(networkLog, null, 2),
            'utf-8'
        );
    } finally {
        await browser.close();
    }
}

run();
