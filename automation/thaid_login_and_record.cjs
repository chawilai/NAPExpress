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

async function fillAndSubmitRecord(page, rrForm, dryRun = false) {
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

    // Debug: dump actual checkbox IDs on the form
    const formDebug = await page.evaluate(() => {
        const allCheckboxes = [...document.querySelectorAll('input[type="checkbox"]')];
        const targetCbs = allCheckboxes.filter(el => el.name?.includes('target_group'));
        const riskCbs = allCheckboxes.filter(el => el.name?.includes('risk_behavior'));
        return {
            url: location.href,
            totalCheckboxes: allCheckboxes.length,
            targetGroups: targetCbs.map(el => ({ id: el.id, name: el.name, visible: el.offsetParent !== null })),
            riskBehaviors: riskCbs.map(el => ({ id: el.id, name: el.name, visible: el.offsetParent !== null })),
        };
    });
    console.log(`[DEBUG] Form checkboxes: ${JSON.stringify(formDebug)}`);

    // Fill form fields via DOM — using evaluate() to bypass visibility issues
    await page.evaluate((rf) => {
        const clickCheckbox = (id) => {
            const el = document.getElementById(id);
            if (el) {
                el.checked = true;
                el.dispatchEvent(new Event('change', { bubbles: true }));
                el.dispatchEvent(new Event('click', { bubbles: true }));
            }
        };

        const setRadio = (name, value) => {
            const el = document.querySelector(`input[name="${name}"][value="${value}"]`);
            if (el) el.click();
        };

        const setSelect = (id, value) => {
            const el = document.getElementById(id);
            if (el) { el.value = value; el.dispatchEvent(new Event('change', { bubbles: true })); }
        };

        // Risk behaviors
        for (const idx of rf.risk_behavior_indices || []) {
            clickCheckbox(`rrttr_risk_behavior_status_${idx}`);
        }

        // Target groups
        for (const idx of rf.target_group_indices || []) {
            clickCheckbox(`rrttr_target_group_status_${idx}`);
        }

        // Access type
        if (rf.access_type) setRadio('access_type', rf.access_type);

        // Pay by — default NHSO
        setSelect('pay_by', rf.pay_by || '1');

        // Occupation
        if (rf.occupation) setSelect('occupation', rf.occupation);

        // SW work type — นอกสถานบริการ for SW/FSW/MSW/TGSW
        const isSw = (rf.risk_behavior_indices || []).includes(2)
            || [9, 12, 15].some(i => (rf.target_group_indices || []).includes(i));
        if (isSw) setRadio('sw_type', '2');

        // Knowledge — always check all 5
        for (let i = 0; i < 5; i++) clickCheckbox(`rrttr_knowledge_status_${i}`);

        // Places
        for (const idx of rf.place_indices || []) clickCheckbox(`rrttr_place_status_${idx}`);

        // PPE
        for (const idx of rf.ppe_indices || []) clickCheckbox(`rrttr_ppe_status_${idx}`);
    }, rrForm);

    // Debug: verify checkboxes were checked
    const afterFill = await page.evaluate((rf) => {
        const check = (prefix, indices) => indices.map(i => {
            const el = document.getElementById(`${prefix}_${i}`);
            return { i, checked: el?.checked ?? 'NOT FOUND' };
        });
        return {
            risk: check('rrttr_risk_behavior_status', rf.risk_behavior_indices || []),
            target: check('rrttr_target_group_status', rf.target_group_indices || []),
            knowledge: check('rrttr_knowledge_status', [0,1,2,3,4]),
            targetGroupHidden: document.querySelector('input[name="target_group"]')?.value || 'NO FIELD',
        };
    }, rrForm);
    console.log(`[DEBUG] After fill: ${JSON.stringify(afterFill)}`);

    // Text fields that need Playwright fill (to trigger input events properly)
    if (rrForm.ref_tel) await page.fill('#ref_tel', rrForm.ref_tel).catch(() => {});

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

    // Dry run: capture what was selected, then go back for next record
    if (dryRun) {
        const report = await page.evaluate(() => {
            const checked = (prefix, count) => {
                const items = [];
                for (let i = 0; i < count; i++) {
                    const el = document.querySelector(`#${prefix}_${i}`);
                    if (el && el.checked) {
                        const nameEl = document.querySelector(`input[name="${prefix.replace('_status', '_name')}_${i}"]`);
                        items.push({ index: i, name: nameEl ? nameEl.value : `index ${i}` });
                    }
                }
                return items;
            };

            const radioVal = (name) => {
                const el = document.querySelector(`input[name="${name}"]:checked`);
                return el ? el.value : null;
            };

            const selectVal = (id) => {
                const el = document.querySelector(`#${id}`);
                if (!el) return null;
                return { value: el.value, text: el.options?.[el.selectedIndex]?.text || el.value };
            };

            const inputVal = (id) => {
                const el = document.querySelector(`#${id}`);
                return el ? el.value : '';
            };

            return {
                risk_behaviors: checked('rrttr_risk_behavior_status', 6),
                target_groups: checked('rrttr_target_group_status', 18),
                access_type: radioVal('access_type'),
                occupation: selectVal('occupation'),
                pay_by: selectVal('pay_by'),
                knowledge: checked('rrttr_knowledge_status', 5),
                place: checked('rrttr_place_status', 5),
                ppe: checked('rrttr_ppe_status', 5),
                condom_49: inputVal('rrttr_condom_amount_49'),
                condom_52: inputVal('rrttr_condom_amount_52'),
                condom_53: inputVal('rrttr_condom_amount_53'),
                condom_54: inputVal('rrttr_condom_amount_54'),
                condom_56: inputVal('rrttr_condom_amount_56'),
                female_condom: inputVal('rrttr_female_condom_amount'),
                lubricant: inputVal('rrttr_lubricant_amount'),
                next_hcode: inputVal('next_hcode'),
                ref_tel: inputVal('ref_tel'),
                hiv_forward: radioVal('hiv_forward'),
                sti_forward: radioVal('sti_forward'),
                tb_forward: radioVal('tb_forward'),
                hcv_forward: radioVal('hcv_forward'),
                methadone_forward: radioVal('methadone_forward'),
            };
        });

        await page.screenshot({ path: `automation/screenshots/dryrun_${rrForm.pid}.png`, fullPage: true });

        // Go back to search page for next record
        const backBtn = await page.$('input[name="backBtn"]');
        if (backBtn) await backBtn.click().catch(() => {});
        await delay(500);

        return { dryRun: true, report };
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
    const { ablyKey, ablyChannel, items, callbackUrl, dryRun } = jobData;

    const ably = createAblyPublisher(ablyKey, ablyChannel);
    const total = items?.length || 0;
    const isDryRun = !!dryRun;

    log(jobId, `Starting ThaiID login flow (${total} records)${isDryRun ? ' [DRY RUN — จะไม่กดบันทึก]' : ''}`);

    // ============================================================
    // Phase 1: HEADED browser — login via ThaiID
    // (GDCC Security blocks headless on iam.nhso.go.th)
    // ============================================================
    log(jobId, 'Opening headed browser for ThaiID login...');
    const loginBrowser = await chromium.launch({
        headless: false,
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-blink-features=AutomationControlled'],
    });

    const loginContext = await loginBrowser.newContext({
        viewport: { width: 1280, height: 900 },
        locale: 'th-TH',
        timezoneId: 'Asia/Bangkok',
    });
    loginContext.setDefaultTimeout(20000);
    const loginPage = await loginContext.newPage();

    loginPage.on('dialog', async dlg => {
        log(jobId, `Dialog: "${dlg.message()}"`);
        await dlg.accept();
    });

    const results = [];

    try {
        // Login via ThaiID (headed)
        const loginOk = await loginViaThaiId(loginPage, ably, jobId);

        if (!loginOk) {
            for (const item of items || []) {
                results.push({ id_card: item.id_card, success: false, nap_code: null, error: 'Login failed — ThaiID scan timeout' });
            }
            await loginBrowser.close();
            await writeResults(dataFile, results);
            return;
        }

        // Extract cookies from headed browser
        const cookies = await loginContext.cookies();
        log(jobId, `Extracted ${cookies.length} cookies from login session`);

        // Save cookies to file for DirectHTTP (PHP) to use
        const cookieFile = dataFile.replace('.json', '_cookies.json');
        fs.writeFileSync(cookieFile, JSON.stringify(cookies, null, 2));
        log(jobId, `Cookies saved to: ${cookieFile}`);

        // Close headed browser — login done
        await loginBrowser.close();
        log(jobId, 'Headed browser closed');

        // ============================================================
        // Phase 2: HEADLESS browser — fill forms with session cookies
        // (dmis.nhso.go.th is NOT blocked by GDCC in headless)
        // ============================================================
        await ably?.publish('job:preparing', {
            jobId, total,
            message: `📋 กำลังเตรียมข้อมูล ${total} รายการ... (headless mode)`,
        }, 1000);

        const workBrowser = await chromium.launch({
            headless: !isDryRun, // headed only for dry run inspection
            args: ['--no-sandbox', '--disable-setuid-sandbox'],
        });

        const workContext = await workBrowser.newContext({
            viewport: { width: 1280, height: 900 },
            locale: 'th-TH',
            timezoneId: 'Asia/Bangkok',
        });

        // Inject session cookies from login
        await workContext.addCookies(cookies);
        workContext.setDefaultTimeout(20000);

        const page = await workContext.newPage();
        page.on('dialog', async dlg => {
            log(jobId, `Dialog: "${dlg.message()}"`);
            await dlg.accept();
        });

        log(jobId, 'Headless browser ready with session cookies');

        // Process records
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

                if (!isDryRun) {
                    await ably?.publish('job:record:submitting', {
                        jobId, index: i + 1, total,
                        message: `💾 กำลังบันทึก... (${i + 1}/${total})`,
                    }, 500);
                }

                const rrCode = await fillAndSubmitRecord(page, rrForm, isDryRun);

                if (isDryRun && rrCode?.dryRun) {
                    const report = rrCode.report;
                    log(jobId, `  Record ${i + 1}: DRY RUN — form filled`);

                    // Build readable report
                    const lines = [
                        `📋 รายงาน DRY RUN (${i + 1}/${total}) | ${uic} | PID: ${pidMasked}`,
                        ``,
                        `พฤติกรรมเสี่ยง: ${report.risk_behaviors.map(r => r.name).join(', ') || '-'}`,
                        `กลุ่มเป้าหมาย: ${report.target_groups.map(r => r.name).join(', ') || '-'}`,
                        `ช่องทางเข้าถึง: ${report.access_type === '1' ? 'ใน DIC' : report.access_type === '2' ? 'นอก DIC' : report.access_type === '3' ? 'Social Media' : '-'}`,
                        `อาชีพ: ${report.occupation?.text || '-'} (${report.occupation?.value || '-'})`,
                        `แหล่งเงิน: ${report.pay_by?.text || '-'}`,
                        `ความรู้: ${report.knowledge.map(r => r.name).join(', ') || '-'}`,
                        `สถานที่: ${report.place.map(r => r.name).join(', ') || '-'}`,
                        `PPE: ${report.ppe.map(r => r.name).join(', ') || '-'}`,
                        `ถุงยาง: 49=${report.condom_49||0} 52=${report.condom_52||0} 53=${report.condom_53||0} 54=${report.condom_54||0} 56=${report.condom_56||0}`,
                        `ถุงยางหญิง: ${report.female_condom || 0} | สารหล่อลื่น: ${report.lubricant || 0}`,
                        `หน่วยบริการ: ${report.next_hcode || '-'}`,
                        `โทร: ${report.ref_tel || '-'}`,
                        `ส่งต่อ: HIV=${report.hiv_forward||'-'} STI=${report.sti_forward||'-'} TB=${report.tb_forward||'-'} HCV=${report.hcv_forward||'-'} Methadone=${report.methadone_forward||'-'}`,
                    ];

                    console.log('\n' + lines.join('\n') + '\n');

                    results.push({
                        id_card: item.id_card,
                        uic,
                        success: true,
                        nap_code: 'DRY_RUN',
                        error: null,
                        report,
                    });

                    await ably?.publish('job:record:report', {
                        jobId, index: i + 1, total, uic, pidMasked,
                        report,
                        message: lines.join('\n'),
                    }, 500);

                    // Continue to next record (don't break!)
                } else if (rrCode) {
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
        await workBrowser.close();
    } catch (error) {
        log(jobId, `Fatal: ${error.message}`);
        await ably?.publish('job:error', {
            jobId,
            message: `❌ เกิดข้อผิดพลาด: ${error.message}`,
        });
        try { await loginBrowser.close(); } catch {}
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
