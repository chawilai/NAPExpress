const { chromium } = require('playwright');
const fs = require('fs');
const Ably = require('ably');

/**
 * ThaiID Login + NAP RR Recording Script
 *
 * Flow:
 * 1. Open NAP Plus → redirects to Keycloak SSO
 * 2. Click ThaiD button → redirects to imauth.bora.dopa.go.th
 * 3. Capture QR code (base64) → publish to Ably channel
 * 4. Wait for user to scan ThaiID on phone
 * 5. Detect login success (URL changes to dmis.nhso.go.th)
 * 6. Navigate to RR form → fill records → submit
 *
 * Usage: node automation/thaid_login_and_record.cjs --jobId=xxx --dataFile=/path/to/data.json
 *
 * The dataFile should contain: { ablyKey, ablyChannel, items: [...] }
 * After login, the script reads items and processes them.
 */

const NAP_URLS = {
    createRR: 'https://dmis.nhso.go.th/NAPPLUS/rrttr/createRRTTR.do?actionName=load',
    createRRBase: 'https://dmis.nhso.go.th/NAPPLUS/rrttr/createRRTTR.do',
};

const THAID_SELECTORS = {
    thaidButton: '#social-thaid, a[id="social-thaid"]',
    qrImage: 'img[src^="data:image"]',
    loginForm: '#kc-form-login, form[action*="openid-connect"]',
};

function parseArgs() {
    const args = process.argv.slice(2);
    const jobId = args.find(a => a.startsWith('--jobId='))?.split('=')[1];
    const dataFile = args.find(a => a.startsWith('--dataFile='))?.split('=')[1];

    if (!jobId || !dataFile) {
        console.error('Usage: node thaid_login_and_record.cjs --jobId=xxx --dataFile=/path/to/data.json');
        process.exit(1);
    }

    return { jobId, dataFile };
}

function readDataFile(dataFile) {
    if (!fs.existsSync(dataFile)) {
        console.error(`Data file not found: ${dataFile}`);
        process.exit(1);
    }
    return JSON.parse(fs.readFileSync(dataFile, 'utf-8'));
}

function log(jobId, msg) {
    console.log(`[Job ${jobId}] ${msg}`);
}

async function delay(ms) {
    return new Promise(r => setTimeout(r, ms));
}

// ============================================================
// Ably Helper
// ============================================================

function createAblyPublisher(ablyKey, channelName) {
    if (!ablyKey || !channelName) return null;

    const ably = new Ably.Rest(ablyKey);
    const channel = ably.channels.get(channelName);

    return {
        publish: async (event, data, delayMs = 0) => {
            try {
                await channel.publish(event, data);
                if (delayMs > 0) await delay(delayMs);
            } catch (e) {
                console.error(`Ably error [${event}]:`, e.message);
            }
        },
    };
}

// ============================================================
// Step 1: Login via ThaiID
// ============================================================

async function loginViaThaiId(page, ably, jobId) {
    log(jobId, 'Navigating to NAP Plus (will redirect to SSO)...');
    await ably?.publish('job:start', {
        jobId,
        message: '🔐 เริ่มระบบ AutoNAP — กำลังเปิดหน้า Login',
    }, 500);

    await page.goto(NAP_URLS.createRR, { waitUntil: 'networkidle', timeout: 30000 });

    // Check if redirected to Keycloak
    if (!page.url().includes('iam.nhso.go.th')) {
        // Already logged in? Check if we're on NAP form
        if (page.url().includes('dmis.nhso.go.th')) {
            log(jobId, 'Already logged in!');
            await ably?.publish('job:login:success', {
                jobId,
                message: '✅ เข้าสู่ระบบแล้ว (session เดิม)',
            }, 500);
            return true;
        }
        throw new Error(`Unexpected redirect: ${page.url()}`);
    }

    log(jobId, 'On Keycloak SSO page — clicking ThaiD...');
    await ably?.publish('job:connecting', {
        jobId,
        message: '🌐 กำลังเชื่อมต่อระบบ สปสช...',
    }, 1000);

    // Click ThaiD button
    const thaidBtn = await page.$(THAID_SELECTORS.thaidButton);
    if (!thaidBtn) {
        throw new Error('ThaiD button not found on login page');
    }

    await thaidBtn.click();
    await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
    await delay(2000);

    log(jobId, `ThaiD page URL: ${page.url()}`);

    // Capture QR code image (base64)
    const qrData = await page.evaluate(() => {
        const img = document.querySelector('img[src^="data:image"]');
        if (!img) return null;
        return {
            src: img.src,
            width: img.offsetWidth,
            height: img.offsetHeight,
        };
    });

    if (!qrData) {
        throw new Error('QR code image not found on ThaiD page');
    }

    log(jobId, `QR code captured: ${qrData.width}x${qrData.height}`);

    // Extract ref code from page
    const refCode = await page.evaluate(() => {
        const text = document.body.innerText;
        const match = text.match(/หมายเลขอ้างอิง\s*:\s*(\w+)/);
        return match ? match[1] : null;
    });

    // Publish QR code to Ably — nhsoForReach.php will display this
    await ably?.publish('job:thaid:qr', {
        jobId,
        qrImage: qrData.src,
        refCode: refCode || '',
        message: '📱 กรุณาสแกน QR Code ด้วยแอป ThaiD',
        hint: 'เปิดแอป ThaiD บนมือถือ → สแกน QR Code → ยืนยันตัวตน',
    });

    log(jobId, `QR code sent via Ably (ref: ${refCode || 'N/A'})`);
    log(jobId, 'Waiting for ThaiID scan...');

    await ably?.publish('job:thaid:waiting', {
        jobId,
        message: '⏳ รอการสแกน ThaiD... (หมดเวลาใน 2 นาที)',
    });

    // Wait for redirect back to NAP Plus (login success)
    try {
        await page.waitForURL(url => {
            const u = url.toString();
            return u.includes('dmis.nhso.go.th') || u.includes('NAPPLUS');
        }, { timeout: 120000 }); // 2 minutes timeout

        log(jobId, `Login success! URL: ${page.url()}`);
        await ably?.publish('job:login:success', {
            jobId,
            message: '✅ Login สำเร็จ — ThaiD ยืนยันตัวตนแล้ว',
        }, 500);

        return true;
    } catch (e) {
        log(jobId, 'ThaiID scan timeout');
        await ably?.publish('job:login:failed', {
            jobId,
            message: '❌ หมดเวลา — ไม่ได้สแกน ThaiD ภายใน 2 นาที',
        });
        return false;
    }
}

// ============================================================
// Step 2: Fill and submit RR form (reused from report_reach_rr.cjs)
// ============================================================

async function fillAndSubmitRecord(page, rrForm) {
    // Navigate to create RR
    await page.goto(NAP_URLS.createRR, { waitUntil: 'domcontentloaded', timeout: 15000 });
    await page.waitForLoadState('networkidle').catch(() => {});

    // Check if redirected to login (session expired)
    if (page.url().includes('iam.nhso.go.th')) {
        throw new Error('Session expired — redirected to login');
    }

    // Fill search: date + PID
    await page.fill('input[name="rrttrDate"]', rrForm.rrttrDate);
    await page.fill('input[name="pid"]', rrForm.pid);
    await page.click('text=เพิ่มข้อมูลให้บริการ');
    await page.waitForLoadState('networkidle').catch(() => {});
    await delay(1500);

    // Check confirm page
    const isConfirm = await page.isVisible('text=แสดงข้อมูลสิทธิการรักษา');
    if (!isConfirm) {
        const errText = await page.evaluate(() => {
            const td = document.querySelector('table.alert td.text');
            return td ? td.textContent.trim() : null;
        });
        throw new Error(errText || 'ไม่พบหน้ายืนยันข้อมูล');
    }

    // Click ตกลง
    await page.click('input[name="registerBtn"]');
    await page.waitForURL('**/createRRTTR.do', { timeout: 15000 });
    await page.waitForLoadState('networkidle').catch(() => {});
    await delay(1000);

    // Fill form fields
    for (const idx of rrForm.risk_behavior_indices || []) {
        await page.check(`#rrttr_risk_behavior_status_${idx}`).catch(() => {});
    }
    for (const idx of rrForm.target_group_indices || []) {
        await page.check(`#rrttr_target_group_status_${idx}`).catch(() => {});
    }
    if (rrForm.access_type) await page.check(`#access_type_${rrForm.access_type}`).catch(() => {});
    if (rrForm.pay_by) await page.selectOption('#pay_by', rrForm.pay_by).catch(() => {});
    if (rrForm.ref_tel) await page.fill('#ref_tel', rrForm.ref_tel).catch(() => {});
    if (rrForm.occupation) await page.selectOption('#occupation', rrForm.occupation).catch(() => {});

    for (const idx of rrForm.knowledge_indices || []) {
        await page.check(`#rrttr_knowledge_status_${idx}`).catch(() => {});
    }
    for (const idx of rrForm.place_indices || []) {
        await page.check(`#rrttr_place_status_${idx}`).catch(() => {});
    }
    for (const idx of rrForm.ppe_indices || []) {
        await page.check(`#rrttr_ppe_status_${idx}`).catch(() => {});
    }

    // Condom — unhide + fill
    await page.evaluate(() => {
        ['49', '52', '53', '54', '56'].forEach(s => {
            document.querySelectorAll(`#lb_condom_amount_${s}_1, #lb_condom_amount_${s}_2`).forEach(e => e.style.display = 'inline');
            const inp = document.querySelector(`#rrttr_condom_amount_${s}`);
            if (inp) inp.style.display = 'inline';
        });
    });
    for (const [size, amount] of Object.entries(rrForm.condom || {})) {
        if (amount > 0) await page.fill(`#rrttr_condom_amount_${size}`, String(amount)).catch(() => {});
    }
    if (rrForm.lubricant > 0) {
        await page.evaluate(() => {
            const el = document.querySelector('#rrttr_lubricant_amount');
            if (el) el.style.display = 'inline';
            const lb = document.querySelector('#lb_lubricant_amount');
            if (lb) lb.style.display = 'inline';
        });
        await page.fill('#rrttr_lubricant_amount', String(rrForm.lubricant)).catch(() => {});
    }
    if (rrForm.next_hcode) await page.fill('#next_hcode', rrForm.next_hcode).catch(() => {});

    for (const [svc, val] of Object.entries(rrForm.forwards || {})) {
        if (val) await page.check(`#${svc}_forward_${val}`).catch(() => {});
    }

    // Submit (preview)
    await page.click('input[name="confirmBtn"]');
    await page.waitForLoadState('networkidle', { timeout: 30000 }).catch(() => {});
    await delay(2000);

    // Confirm (final save)
    const confirmBtn = await page.$('input[value="ตกลง"]');
    if (confirmBtn && await confirmBtn.isVisible()) {
        await confirmBtn.click();
        await page.waitForLoadState('networkidle', { timeout: 30000 }).catch(() => {});
        await delay(2000);
    }

    // Extract RR code
    const rrCode = await page.evaluate(() => {
        const el = document.querySelector('div.font22 > b');
        return el ? el.textContent.trim() : null;
    });

    return rrCode;
}

// ============================================================
// Main
// ============================================================

async function run() {
    const { jobId, dataFile } = parseArgs();
    const jobData = readDataFile(dataFile);
    const { ablyKey, ablyChannel, items, callbackUrl } = jobData;

    const ably = createAblyPublisher(ablyKey, ablyChannel);
    const total = items?.length || 0;

    log(jobId, `Starting ThaiID login flow (${total} records to process)`);

    const browser = await chromium.launch({
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox'],
    });

    const context = await browser.newContext({
        viewport: { width: 1280, height: 900 },
        locale: 'th-TH',
        timezoneId: 'Asia/Bangkok',
    });
    context.setDefaultTimeout(20000);
    const page = await context.newPage();

    // Handle dialogs
    page.on('dialog', async dlg => {
        log(jobId, `Dialog: "${dlg.message()}"`);
        await dlg.accept();
    });

    const results = [];

    try {
        // Step 1: Login via ThaiID
        const loginOk = await loginViaThaiId(page, ably, jobId);

        if (!loginOk) {
            // Mark all as failed
            for (const item of items || []) {
                results.push({
                    id_card: item.id_card,
                    success: false,
                    nap_code: null,
                    error: 'Login failed — ThaiID scan timeout',
                });
            }
            await writeResults(dataFile, results);
            return;
        }

        // Step 2: Process records
        await ably?.publish('job:preparing', {
            jobId, total,
            message: `📋 กำลังเตรียมข้อมูล ${total} รายการ...`,
        }, 1000);

        for (let i = 0; i < (items || []).length; i++) {
            const item = items[i];
            const rrForm = item.rr_form;
            const uic = item.uic || '';
            const pidMasked = 'xxxx' + (item.id_card || '').slice(-4);

            await ably?.publish('job:record:processing', {
                jobId, index: i + 1, total, pidMasked, uic,
                message: `📄 กำลังบันทึก (${i + 1}/${total}) | ${uic} | PID: ${pidMasked}`,
            }, 300);

            await ably?.publish('job:record:searching', {
                jobId, index: i + 1, total,
                message: `🔍 กำลังค้นหาข้อมูลบุคคล... (${i + 1}/${total})`,
            }, 800);

            try {
                await ably?.publish('job:record:filling', {
                    jobId, index: i + 1, total,
                    message: `✏️ กำลังกรอกข้อมูลแบบฟอร์ม... (${i + 1}/${total})`,
                }, 500);

                await ably?.publish('job:record:submitting', {
                    jobId, index: i + 1, total,
                    message: `💾 กำลังบันทึก... (${i + 1}/${total})`,
                }, 500);

                const rrCode = await fillAndSubmitRecord(page, rrForm);

                if (rrCode) {
                    log(jobId, `  Record ${i + 1}: ${rrCode}`);
                    results.push({ id_card: item.id_card, success: true, nap_code: rrCode, error: null });
                    await ably?.publish('job:record:success', {
                        jobId, index: i + 1, total, napCode: rrCode, uic,
                        message: `✅ สำเร็จ (${i + 1}/${total}) | ${rrCode}`,
                    }, 300);
                } else {
                    results.push({ id_card: item.id_card, success: false, nap_code: null, error: 'ไม่พบรหัส RR' });
                    await ably?.publish('job:record:failed', {
                        jobId, index: i + 1, total, error: 'ไม่พบรหัส RR', uic,
                        message: `❌ ล้มเหลว (${i + 1}/${total}) | ไม่พบรหัส RR`,
                    }, 300);
                }
            } catch (err) {
                log(jobId, `  Record ${i + 1} error: ${err.message}`);
                results.push({ id_card: item.id_card, success: false, nap_code: null, error: err.message });
                await ably?.publish('job:record:failed', {
                    jobId, index: i + 1, total, error: err.message, uic,
                    message: `❌ ล้มเหลว (${i + 1}/${total}) | ${err.message}`,
                }, 300);
            }
        }

        // Summary
        const success = results.filter(r => r.success).length;
        const failed = results.filter(r => !r.success).length;

        await ably?.publish('job:summarizing', { jobId, message: '📊 กำลังสรุปผล...' }, 1000);
        await ably?.publish('job:complete', {
            jobId, total, success, failed,
            message: `📊 สรุป: สำเร็จ ${success} / ล้มเหลว ${failed} / ทั้งหมด ${total}`,
        });

        log(jobId, `Done: ${success} success, ${failed} failed`);
    } catch (error) {
        log(jobId, `Fatal: ${error.message}`);
        await ably?.publish('job:error', {
            jobId,
            message: `❌ เกิดข้อผิดพลาด: ${error.message}`,
        });
    } finally {
        await browser.close();
    }

    await writeResults(dataFile, results);
}

async function writeResults(dataFile, results) {
    const resultsFile = dataFile.replace('.json', '_results.json');
    fs.writeFileSync(resultsFile, JSON.stringify({ results }, null, 2));
    console.log(`Results: ${resultsFile}`);
}

run().catch(err => {
    console.error('Unhandled:', err);
    process.exit(1);
});
