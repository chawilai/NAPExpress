const { chromium } = require('playwright');
const fs = require('fs');

/**
 * NAP Reach RR Playwright Automation Script
 *
 * Reads job data from a JSON file (--dataFile), logs into NAP Plus,
 * fills the Reach RR form for each record using pre-computed rr_form data
 * from CAREMAT API, and writes results to a _results.json file.
 *
 * Usage: node automation/report_reach_rr.cjs --dataFile=/path/to/job_data.json
 *
 * Based on proven nap_playwright.js from playwright-test project.
 */

const NAP_URLS = {
    login: 'https://dmis.nhso.go.th/NAPPLUS/login.jsp',
    createRR: 'https://dmis.nhso.go.th/NAPPLUS/rrttr/createRRTTR.do?actionName=load',
    createRRBase: 'https://dmis.nhso.go.th/NAPPLUS/rrttr/createRRTTR.do',
};

const RETRY_COUNT = 1;
const RECORD_DELAY_MS = 1500;

// ============================================================
// Helpers
// ============================================================

function parseArgs() {
    const args = process.argv.slice(2);
    const dataFile = args.find((a) => a.startsWith('--dataFile='))?.split('=')[1];

    if (!dataFile) {
        console.error('Usage: node report_reach_rr.cjs --dataFile=/path/to/data.json');
        process.exit(1);
    }

    return { dataFile };
}

function readJobData(dataFile) {
    if (!fs.existsSync(dataFile)) {
        console.error(`Data file not found: ${dataFile}`);
        process.exit(1);
    }

    return JSON.parse(fs.readFileSync(dataFile, 'utf-8'));
}

function writeResults(dataFile, results) {
    const resultsFile = dataFile.replace('_data.json', '_results.json');
    fs.writeFileSync(resultsFile, JSON.stringify({ rows: results }, null, 2), 'utf-8');
    console.log(`Results written to: ${resultsFile}`);
}

function log(jobId, msg) {
    console.log(`[Job #${jobId}] ${msg}`);
}

async function delay(ms) {
    return new Promise((r) => setTimeout(r, ms));
}

// ============================================================
// Login
// ============================================================

async function loginToNap(page, credentials, jobId) {
    log(jobId, 'Navigating to NAP Plus login...');
    await page.goto(NAP_URLS.login, { waitUntil: 'domcontentloaded', timeout: 30000 });

    await page.fill('input[name="user_name"]', credentials.username);
    await page.fill('input[name="password"]', credentials.password);
    await page.click('input[type="submit"]');

    await page.waitForURL('**/NAPPLUS/login.do', { timeout: 15000 });
    log(jobId, 'Login successful');
}

// ============================================================
// Search & Confirm Person
// ============================================================

async function searchAndConfirmPerson(page, rr_form, jobId) {
    // Navigate to create RR form (with actionName=load)
    await page.goto(NAP_URLS.createRR, { waitUntil: 'domcontentloaded', timeout: 15000 });
    await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});

    // Fill service date + PID
    await page.fill('input[name="rrttrDate"]', rr_form.rrttrDate);
    await page.fill('input[name="pid"]', rr_form.pid);

    // Click "เพิ่มข้อมูลให้บริการ"
    await page.click('text=เพิ่มข้อมูลให้บริการ');
    await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});

    // Check if we're on the confirmation page
    const isConfirmPage = await page.isVisible('text=แสดงข้อมูลสิทธิการรักษา');

    if (!isConfirmPage) {
        // Check for error message
        const errorMsg = await page.evaluate(() => {
            const td = document.querySelector('table.alert td.text');
            return td ? td.textContent.trim() : null;
        });

        if (errorMsg) {
            throw new Error(`NAP error: ${errorMsg}`);
        }

        throw new Error('ไม่พบหน้ายืนยันข้อมูลสิทธิการรักษา');
    }

    log(jobId, `  Confirmed person: PID ${rr_form.pid}`);

    // Click "ตกลง" to proceed to the RR form
    await page.click('input[name="registerBtn"]');
    await page.waitForURL(NAP_URLS.createRRBase, { timeout: 15000 });
    await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});

    log(jobId, '  RR form loaded');
}

// ============================================================
// Fill RR Form — uses rr_form from CAREMAT API directly
// ============================================================

async function fillRrForm(page, rr_form) {
    // 1. Risk Behavior checkboxes
    for (const idx of rr_form.risk_behavior_indices || []) {
        const el = await page.$(`#rrttr_risk_behavior_status_${idx}`);
        if (el && !(await el.isChecked())) await el.check();
    }

    // 2. Target Group checkboxes
    for (const idx of rr_form.target_group_indices || []) {
        const el = await page.$(`#rrttr_target_group_status_${idx}`);
        if (el && !(await el.isChecked())) await el.check();
    }

    // 3. Partner with (select)
    if (rr_form.partner_with) {
        await page.selectOption('#partner_with', String(rr_form.partner_with)).catch(() => {});
    }

    // 4. Access Type (radio)
    const accessType = rr_form.access_type || '2';
    await page.check(`#access_type_${accessType}`).catch(() => {});

    // 5. Social Media (if access_type = 3)
    if (String(accessType) === '3' && rr_form.social_media) {
        await page.selectOption('#social_media', String(rr_form.social_media)).catch(() => {});
    }

    // 6. Phone & Email
    if (rr_form.ref_tel) await page.fill('#ref_tel', rr_form.ref_tel).catch(() => {});
    if (rr_form.ref_email) await page.fill('#ref_email', rr_form.ref_email).catch(() => {});

    // 7. Occupation (select — code from API, e.g. "06")
    if (rr_form.occupation) {
        await page.selectOption('#occupation', rr_form.occupation).catch(() => {});
    }

    // 8. Knowledge checkboxes
    for (const idx of rr_form.knowledge_indices || []) {
        const el = await page.$(`#rrttr_knowledge_status_${idx}`);
        if (el && !(await el.isChecked())) await el.check();
    }

    // 9. Place checkboxes
    for (const idx of rr_form.place_indices || []) {
        const el = await page.$(`#rrttr_place_status_${idx}`);
        if (el && !(await el.isChecked())) await el.check();
    }

    // 10. PPE checkboxes
    for (const idx of rr_form.ppe_indices || []) {
        const el = await page.$(`#rrttr_ppe_status_${idx}`);
        if (el && !(await el.isChecked())) await el.check();
    }

    // 11. Condom sizes — unhide first, then fill
    await page.evaluate(() => {
        const sizes = ['49', '52', '53', '54', '56'];
        sizes.forEach((size) => {
            document
                .querySelectorAll(`#lb_condom_amount_${size}_1, #lb_condom_amount_${size}_2`)
                .forEach((e) => { e.style.display = 'inline'; });
            const input = document.querySelector(`#rrttr_condom_amount_${size}`);
            if (input) input.style.display = 'inline';
        });
    });

    const condom = rr_form.condom || {};
    for (const [size, amount] of Object.entries(condom)) {
        if (amount > 0) {
            await page.fill(`#rrttr_condom_amount_${size}`, String(amount)).catch(() => {});
        }
    }

    // 12. Female condom
    if (rr_form.female_condom > 0) {
        await page.evaluate(() => {
            const el = document.querySelector('#rrttr_female_condom_amount');
            if (el) el.style.display = 'inline';
            const lb = document.querySelector('#lb_female_condom_amount');
            if (lb) lb.style.display = 'inline';
        });
        await page.fill('#rrttr_female_condom_amount', String(rr_form.female_condom)).catch(() => {});
    }

    // 13. Lubricant — unhide separately (proven pattern from nap_playwright.js)
    if (rr_form.lubricant > 0) {
        await page.evaluate(() => {
            const el = document.querySelector('#rrttr_lubricant_amount');
            if (el) el.style.display = 'inline';
            const lb = document.querySelector('#lb_lubricant_amount');
            if (lb) lb.style.display = 'inline';
        });
        await page.fill('#rrttr_lubricant_amount', String(rr_form.lubricant)).catch(() => {});
    }

    // 14. Healthcare referral (next_hcode)
    if (rr_form.next_hcode) {
        await page.fill('#next_hcode', rr_form.next_hcode).catch(() => {});
    }

    // 15. Forward services — HIV, STI, TB (required for all)
    const forwards = rr_form.forwards || {};
    for (const [service, value] of Object.entries(forwards)) {
        if (value) {
            await page.check(`#${service}_forward_${value}`).catch(() => {});
        }
    }
}

// ============================================================
// Submit & Extract RR Code
// ============================================================

async function submitAndGetResult(page) {
    // Click "บันทึก" (confirmBtn)
    await page.click('input[name="confirmBtn"]');
    await page.waitForLoadState('networkidle', { timeout: 30000 }).catch(() => {});
    await delay(2000);

    // Try to extract RR code from result page (div.font22 > b)
    const rrCode = await page.evaluate(() => {
        const el = document.querySelector('div.font22 > b');
        return el ? el.textContent.trim() : null;
    });

    if (rrCode) {
        return { success: true, napCode: rrCode, error: null };
    }

    // Check for error
    const errorText = await page.evaluate(() => {
        const el = document.querySelector('table.alert td.text, .error-message, .alert-danger');
        return el ? el.textContent.trim() : null;
    });

    if (errorText) {
        // Check for duplicate
        if (errorText.includes('ซ้ำ') || errorText.includes('duplicate') || errorText.includes('มีข้อมูลแล้ว')) {
            return { success: false, napCode: null, error: `ข้อมูลซ้ำ: ${errorText}` };
        }

        return { success: false, napCode: null, error: errorText };
    }

    // Check if success page has different structure
    const pageText = await page.evaluate(() => document.body?.innerText?.substring(0, 500) || '');
    if (pageText.includes('บันทึกข้อมูลเรียบร้อย') || pageText.includes('สำเร็จ')) {
        return { success: true, napCode: 'SUCCESS', error: null };
    }

    return { success: false, napCode: null, error: 'ไม่สามารถระบุผลลัพธ์ได้' };
}

// ============================================================
// Process Single Record
// ============================================================

async function processRecord(page, row, jobId) {
    const { id, row_number, row_data } = row;
    const rr_form = row_data.rr_form || row_data;

    for (let attempt = 1; attempt <= RETRY_COUNT + 1; attempt++) {
        try {
            log(jobId, `  [Row ${row_number}] Attempt ${attempt}...`);

            // Search person & confirm
            await searchAndConfirmPerson(page, rr_form, jobId);

            // Fill form
            await fillRrForm(page, rr_form);

            // Submit
            const result = await submitAndGetResult(page);

            if (result.success) {
                log(jobId, `  [Row ${row_number}] Success: ${result.napCode}`);
                return { id, row_number, success: true, nap_code: result.napCode, error: null };
            }

            // Don't retry duplicates
            if (result.error && (result.error.includes('ซ้ำ') || result.error.includes('มีข้อมูลแล้ว'))) {
                log(jobId, `  [Row ${row_number}] Duplicate: ${result.error}`);
                return { id, row_number, success: false, nap_code: null, error: result.error };
            }

            if (attempt <= RETRY_COUNT) {
                log(jobId, `  [Row ${row_number}] Failed, retrying...`);
                await delay(2000);
            } else {
                return { id, row_number, success: false, nap_code: null, error: result.error };
            }
        } catch (error) {
            log(jobId, `  [Row ${row_number}] Error: ${error.message}`);

            if (attempt > RETRY_COUNT) {
                return { id, row_number, success: false, nap_code: null, error: error.message };
            }

            await delay(2000);
        }
    }
}

// ============================================================
// Main
// ============================================================

async function run() {
    const { dataFile } = parseArgs();
    const jobData = readJobData(dataFile);

    log(jobData.job_id, `Starting Reach RR automation...`);
    log(jobData.job_id, `Records to process: ${jobData.rows.length}`);

    const browser = await chromium.launch({
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-blink-features=AutomationControlled'],
    });

    const context = await browser.newContext({
        viewport: { width: 1280, height: 800 },
        locale: 'th-TH',
        timezoneId: 'Asia/Bangkok',
        geolocation: { latitude: 13.7563, longitude: 100.5018 },
        permissions: ['geolocation'],
    });
    context.setDefaultTimeout(15000);

    const page = await context.newPage();

    // Handle dialogs
    page.on('dialog', async (dlg) => {
        log(jobData.job_id, `Dialog: ${dlg.type()} — "${dlg.message()}"`);
        await dlg.accept();
    });

    const results = [];

    try {
        // Login once
        await loginToNap(page, jobData.credentials, jobData.job_id);

        // Process each row
        for (let i = 0; i < jobData.rows.length; i++) {
            const row = jobData.rows[i];
            log(jobData.job_id, `Processing record ${i + 1}/${jobData.rows.length}...`);

            const result = await processRecord(page, row, jobData.job_id);
            results.push(result);

            if (i < jobData.rows.length - 1) {
                await delay(RECORD_DELAY_MS);
            }
        }

        const successCount = results.filter((r) => r.success).length;
        const failedCount = results.filter((r) => !r.success).length;
        log(jobData.job_id, `Completed: ${successCount} success, ${failedCount} failed`);
    } catch (error) {
        log(jobData.job_id, `Fatal error: ${error.message}`);

        // Mark remaining unprocessed rows as failed
        const processedIds = new Set(results.map((r) => r.id));
        for (const row of jobData.rows) {
            if (!processedIds.has(row.id)) {
                results.push({
                    id: row.id,
                    row_number: row.row_number,
                    success: false,
                    nap_code: null,
                    error: `Automation stopped: ${error.message}`,
                });
            }
        }
    } finally {
        await browser.close();
    }

    writeResults(dataFile, results);
}

run().catch((error) => {
    console.error('Unhandled error:', error);
    process.exit(1);
});
