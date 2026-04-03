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
    createVCT: 'https://dmis.nhso.go.th/NAPPLUS/vct/createVCT.do?actionName=load',
    createVCTBase: 'https://dmis.nhso.go.th/NAPPLUS/vct/createVCT.do',
    createHivLab: 'https://dmis.nhso.go.th/NAPPLUS/hivLabRequest/createHivLabRequest.do?actionName=load',
    createHivLabBase: 'https://dmis.nhso.go.th/NAPPLUS/hivLabRequest/createHivLabRequest.do',
};

const VCT_KP_MAP = {
    'MSM': 0, 'PWID': 1, 'ANC': 2, 'TGW': 3, 'PWUD': 4,
    'คลอดจากแม่ติดเชื้อเอชไอวี': 5, 'TGM': 6, 'Partner of KP': 7,
    'บุคลากรทางการแพทย์': 8, 'TGSW': 9, 'Partner of PLHIV': 10,
    'nPEP': 11, 'MSW': 12, 'Prisoners': 13, 'General Population': 14,
    'FSW': 15, 'Migrant': 16, 'สามี/คู่ของหญิงตั้งครรภ์': 17,
    // CAREMAT aliases
    'Female': 14, 'Male': 14, 'TG': 3, 'PWID-Male': 1, 'PWID-Female': 1,
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

// ============================================================
// Callback Helper — send callback to CAREMAT directly from script
// ============================================================

async function sendCallback(callbackUrl, payload) {
    if (!callbackUrl) return null;
    try {
        const res = await fetch(callbackUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(payload),
            signal: AbortSignal.timeout(15000),
        });
        const data = await res.json().catch(() => ({}));
        console.log(`[Callback] ${res.status} → ${JSON.stringify(data)}`);
        return data;
    } catch (e) {
        console.error(`[Callback] Error: ${e.message}`);
        return null;
    }
}

function buildVctCallback(item, fy, vctCode) {
    return {
        form_type: 'VCT',
        source_id: item.source_id,
        source: item.source,
        uic: item.uic || null,
        id_card: item.id_card,
        kp: item.kp,
        fy: fy,
        nap_vct_code: vctCode,
        vct_nap_status: 'true',
        nap_staff: item.cbs || 'AutoNAP',
        nap_comment: 'AutoNAP',
        status: 'success',
        row_id: item.row_id,
    };
}

function buildLabCallback(item, fy, labCode) {
    return {
        form_type: 'VCT',
        source_id: item.source_id,
        source: item.source,
        uic: item.uic || null,
        id_card: item.id_card,
        kp: item.kp,
        fy: fy,
        nap_code: labCode,
        nap_lab_code: labCode,
        nap_status: 'true',
        nap_staff: item.cbs || 'AutoNAP',
        nap_comment: 'AutoNAP',
        status: 'success',
        row_id: item.row_id,
    };
}

function buildErrorCallback(item, fy, error, vctCode) {
    return {
        form_type: 'VCT',
        source_id: item.source_id,
        source: item.source,
        uic: item.uic || null,
        id_card: item.id_card,
        kp: item.kp,
        fy: fy,
        nap_vct_code: vctCode || null,
        nap_status: 'true',
        nap_staff: item.cbs || 'AutoNAP',
        nap_comment: (error || '') + ' AutoNAP',
        status: 'success',
        row_id: item.row_id,
    };
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

async function loginViaThaiId(page, ably, jobId, startUrl = NAP_URLS.createRR) {
    log(jobId, 'Navigating to NAP Plus (will redirect to SSO)...');
    await ably?.publish('job:start', {
        jobId,
        message: '🔐 เริ่มระบบ AutoNAP — กำลังเปิดหน้า Login',
    }, 500);

    await page.goto(startUrl, { waitUntil: 'networkidle', timeout: 30000 });

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
        message: '⏳ รอการสแกน ThaiD... (หมดเวลาใน 1 นาที)',
    });

    // Wait for redirect back to NAP Plus (login success)
    try {
        await page.waitForURL(url => {
            const u = url.toString();
            return u.includes('dmis.nhso.go.th') || u.includes('NAPPLUS');
        }, { timeout: 60000 }); // 1 minute timeout

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
            message: '❌ หมดเวลา — ไม่ได้สแกน ThaiD ภายใน 1 นาที',
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

    // Debug: log rrForm before passing to evaluate
    console.log(`[DEBUG] rrForm.risk_behavior_indices: ${JSON.stringify(rrForm.risk_behavior_indices)}`);
    console.log(`[DEBUG] rrForm.target_group_indices: ${JSON.stringify(rrForm.target_group_indices)}`);
    console.log(`[DEBUG] rrForm keys: ${Object.keys(rrForm).join(', ')}`);

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
    const fillDebug = await page.evaluate((rf) => {
        const received = {
            riskIndices: rf.risk_behavior_indices,
            targetIndices: rf.target_group_indices,
            typeofRisk: typeof rf.risk_behavior_indices,
            typeofTarget: typeof rf.target_group_indices,
            allKeys: Object.keys(rf),
        };
        return received;
    }, rrForm);
    console.log(`[DEBUG] evaluate received: ${JSON.stringify(fillDebug)}`);

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
// Step 2b: Fill and submit VCT form
// ============================================================

async function fillAndSubmitVCT(page, item, dryRun = false) {
    const serviceDate = item.service_date;
    const pid = item.id_card;
    const kp = item.kp;
    const uic = item.uic || '';
    const kpIndex = VCT_KP_MAP[kp];

    if (kpIndex === undefined) {
        throw new Error(`Unknown KP: ${kp} — ไม่พบใน VCT_KP_MAP`);
    }

    // Navigate to VCT create page
    await page.goto(NAP_URLS.createVCT, { waitUntil: 'domcontentloaded', timeout: 15000 });
    await page.waitForLoadState('networkidle').catch(() => {});

    if (page.url().includes('iam.nhso.go.th')) {
        throw new Error('Session expired — redirected to login');
    }

    // Fill search: date + PID
    await page.fill('input[name="vct_date"]', serviceDate);
    await page.fill('input[name="pid"]', pid);
    await page.click('input#cmdSearch');
    await page.waitForLoadState('networkidle').catch(() => {});
    await delay(1500);

    // Check for error on search page
    const currentUrl = page.url();
    if (currentUrl.includes('actionName=load')) {
        const errText = await page.evaluate(() => {
            const td = document.querySelector('table.alert td.text');
            return td ? td.textContent.trim() : null;
        });
        throw new Error(errText || 'ไม่สามารถเข้าฟอร์ม VCT ได้');
    }

    // Confirm page: click ตกลง (same as RR — shows patient rights info)
    const confirmRegBtn = await page.$('input[value="ตกลง"]');
    if (confirmRegBtn && await confirmRegBtn.isVisible()) {
        console.log('[VCT] Confirm page detected — clicking ตกลง');
        await confirmRegBtn.click();
        await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
        await delay(1500);
    }

    // After confirm, NAP Plus may return to search page — check and re-fill if needed
    if (page.url().includes('actionName=load')) {
        console.log('[VCT] Back on search page after confirm — re-filling date + PID');
        await page.fill('input[name="vct_date"]', serviceDate);
        await page.fill('input[name="pid"]', pid);

        // Debug: verify values were filled
        const filledDate = await page.inputValue('input[name="vct_date"]');
        const filledPid = await page.inputValue('input[name="pid"]');
        console.log(`[VCT] Filled: date=${filledDate} pid=${filledPid}`);

        await page.click('input#cmdSearch');
        await page.waitForLoadState('networkidle').catch(() => {});
        await delay(1500);

        // Check for NAP Plus error alert (e.g. duplicate VCT date)
        const alertText = await page.evaluate(() => {
            const alert = document.querySelector('table.alert td.text');
            return alert?.textContent?.trim() || null;
        });

        if (alertText) {
            throw new Error(alertText);
        }

        // Check for another confirm page
        const confirmBtn2 = await page.$('input[value="ตกลง"]');
        if (confirmBtn2 && await confirmBtn2.isVisible()) {
            await confirmBtn2.click();
            await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
            await delay(1500);
        }
    }

    // Verify we're on the VCT form (not search page)
    if (page.url().includes('actionName=load')) {
        const alertText = await page.evaluate(() => {
            const alert = document.querySelector('table.alert td.text');
            return alert?.textContent?.trim() || null;
        });
        throw new Error(alertText || 'ไม่สามารถเข้าฟอร์ม VCT ได้');
    }

    // Fill VCT form via DOM
    await page.evaluate((data) => {
        const clickCheckbox = (id) => {
            const el = document.getElementById(id);
            if (el) {
                el.checked = true;
                el.dispatchEvent(new Event('change', { bubbles: true }));
                el.dispatchEvent(new Event('click', { bubbles: true }));
            }
        };

        const clickRadio = (name, value) => {
            const el = document.querySelector(`input[name="${name}"][value="${value}"]`);
            if (el) el.click();
        };

        // 1. ช่องทางมารับบริการ: RRTTR (index 7)
        clickCheckbox('vct_receive_from_status_7');

        // 2. UIC (if from RRTTR)
        if (data.uic) {
            const uicInput = document.getElementById('rrtr_uic');
            if (uicInput) uicInput.value = data.uic;
        }

        // 3. ประเมินความเสี่ยง: มีพฤติกรรมเสี่ยง
        clickRadio('risk_flag', 'Y');

        // 4. กลุ่มผู้มารับบริการ (KP)
        clickCheckbox(`vct_target_group_status_${data.kpIndex}`);

        // 5. ปัจจัยเสี่ยง: มีเพศสัมพันธ์โดยไม่ป้องกัน (always)
        clickCheckbox('vct_risk_factor_status_0');
        if (data.kp === 'PWID') {
            clickCheckbox('vct_risk_factor_status_3');
        }

        // 6. Pre-test: ทำ
        clickRadio('pre_test_status', 'Y');

        // 7. Pre-test type: PICT (1)
        clickRadio('pre_test_type', '1');

        // 8. Pre-test method: รายบุคคล (2)
        clickRadio('pre_test_method', '2');

        // 9. Post-test: ทำ
        clickRadio('post_test_status', 'Y');

        // 10. Couple counseling: ไม่มีคู่ (3)
        clickRadio('post_test_couple_result_status', '3');

        // 11. STI — check if any STI results exist
        const sti = data.sti || {};
        const hasStiResult = sti.syphilis || sti.ct || sti.ng;

        if (hasStiResult) {
            // ส่งต่อ (1) → ได้รับการตรวจ (Y)
            const stiForward = document.querySelector('#post_test_sti_1');
            if (stiForward) {
                stiForward.click();
                if (typeof doclick_post_test_sti === 'function') {
                    doclick_post_test_sti('1', true);
                }
            }
            // ได้รับการตรวจ
            const stiChecked = document.querySelector('#post_test_sti_send_2');
            if (stiChecked) {
                stiChecked.click();
                if (typeof doclick_post_test_sti_send === 'function') {
                    doclick_post_test_sti_send('Y', true);
                }
            }
            // Syphilis
            if (sti.syphilis) {
                clickCheckbox('post_test_sti_syphilis');
                if (typeof doclick_post_test_sti_syphilis === 'function') {
                    doclick_post_test_sti_syphilis('Y', true);
                }
                // ผลบวก → เลือก TPHA
                if (sti.syphilis === 'R') {
                    clickCheckbox('post_test_sti_syphilis_vdrl');
                }
            }
            // Gonorrhea
            if (sti.ng) {
                clickCheckbox('post_test_sti_gonorrhea');
            }
            // Chlamydia
            if (sti.ct) {
                clickCheckbox('post_test_sti_chlamydia');
            }
        } else {
            // ไม่ส่งต่อ (2)
            const stiRadio = document.querySelector('#post_test_sti_2');
            if (stiRadio) {
                stiRadio.click();
                if (typeof doclick_post_test_sti === 'function') {
                    doclick_post_test_sti('2', true);
                }
            }
        }

        // 12. เมทาโดน: ไม่ได้รับ (2)
        clickRadio('post_test_methadone', '2');

        // 13. ถุงยาง: รับ — must trigger onclick handler to show amount fields
        const condomY = document.querySelector('input[name="condom_receive_y"]');
        if (condomY) {
            condomY.checked = true;
            condomY.click();
            // Trigger NAP Plus's condomCheckboxControl if available
            if (typeof condomCheckboxControl === 'function') {
                condomCheckboxControl(condomY);
            }
        }
    }, { kp, kpIndex, uic, sti: item.sti || {} });

    // STI sub-option: only needed when "ไม่ส่งต่อ" was selected (no STI results)
    const sti = item.sti || {};
    const hasStiResult = sti.syphilis || sti.ct || sti.ng;
    if (!hasStiResult) {
        await delay(300);
        await page.evaluate(() => {
            const stiNotFwd = document.querySelector('#post_test_sti_not_forward_1');
            if (stiNotFwd) {
                stiNotFwd.disabled = false;
                stiNotFwd.click();
                if (typeof doclick_post_test_sti_not_forward === 'function') {
                    doclick_post_test_sti_not_forward('1', true);
                }
            }
        });
    }

    // Fill date fields via Playwright fill
    await page.fill('#pre_test_date', serviceDate).catch(() => {});
    await page.fill('#post_test_date', serviceDate).catch(() => {});

    // Condom — unhide fields + set values via DOM (NAP Plus hides them by default)
    // Use item values if provided, otherwise default 10 per size
    const condomDefaults = {
        '49': item.condom_49 || '10',
        '52': item.condom_52 || '10',
        '53': item.condom_53 || '10',
        '54': item.condom_54 || '10',
        '56': item.condom_56 || '10',
    };
    const lubricantAmount = item.lubricant || '10';

    await delay(500);
    await page.evaluate((data) => {
        // Unhide ALL condom-related elements + set values
        ['49', '52', '53', '54', '56'].forEach(s => {
            const inp = document.querySelector(`#condom_amount_${s}`);
            if (inp) {
                inp.style.display = 'inline';
                inp.removeAttribute('disabled');
                inp.value = data.condoms[s];
                inp.dispatchEvent(new Event('change', { bubbles: true }));
            }
            const label = document.querySelector(`#label_${s}`);
            if (label) label.style.display = 'inline';
        });
        const lubInput = document.querySelector('#lubricant_amount');
        if (lubInput) {
            lubInput.style.display = 'inline';
            lubInput.removeAttribute('disabled');
            lubInput.value = data.lubricant;
            lubInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
        const lubLabel = document.querySelector('#label_lubricant');
        if (lubLabel) lubLabel.style.display = 'inline';
    }, { condoms: condomDefaults, lubricant: lubricantAmount });

    // Also try Playwright fill as backup
    for (const [size, amount] of Object.entries(condomDefaults)) {
        await page.fill(`#condom_amount_${size}`, String(amount)).catch(() => {});
    }
    await page.fill('#lubricant_amount', String(lubricantAmount)).catch(() => {});

    if (dryRun) {
        await page.screenshot({ path: `automation/screenshots/dryrun_vct_${pid}.png`, fullPage: true });
        return { dryRun: true, vct_code: 'DRY_RUN' };
    }

    // Check what buttons exist
    const buttons = await page.evaluate(() => {
        const btns = [...document.querySelectorAll('input[type="button"], button')];
        return btns.map(b => ({ id: b.id, name: b.name, value: b.value, visible: b.offsetParent !== null }));
    });
    console.log(`[VCT] Buttons on page: ${JSON.stringify(buttons)}`);

    // Submit: click บันทึก (triggers confirmation dialog → form submit → confirm page)
    await page.click('input#cmdPreview');
    await page.waitForLoadState('networkidle', { timeout: 30000 }).catch(() => {});
    await delay(2000);

    // After บันทึก: may land on confirm page with ตกลง button
    // Keep clicking ตกลง until we reach the VCT ID result page
    for (let attempt = 0; attempt < 3; attempt++) {
        const confirmBtn = await page.$('input[value="ตกลง"]');
        if (confirmBtn && await confirmBtn.isVisible()) {
            await confirmBtn.click();
            await page.waitForLoadState('networkidle', { timeout: 30000 }).catch(() => {});
            await delay(2000);
        } else {
            break;
        }
    }

    // Extract VCT ID — retry a few times (page may still be loading)
    let vctCode = null;
    for (let attempt = 0; attempt < 3; attempt++) {
        vctCode = await page.evaluate(() => {
            // Look for VCT ID pattern in page text
            const text = document.body.innerText;
            const match = text.match(/V\d{2}-\d+-\d+/);
            if (match) return match[0];

            // Fallback: look in table cells
            const tds = [...document.querySelectorAll('td')];
            const labelTd = tds.find(td => td.textContent.includes('VCT ID'));
            if (labelTd) {
                const nextTd = labelTd.nextElementSibling;
                return nextTd ? nextTd.textContent.trim() : null;
            }
            return null;
        });

        if (vctCode) break;
        await delay(2000);
    }

    return vctCode;
}

// ============================================================
// Step 2c: Fill and submit HIV Lab Request
// ============================================================

async function fillAndSubmitLabRequest(page, item, dryRun = false) {
    const pid = item.id_card;
    const labDate = item.hiv_labreq_date || item.service_date;

    await page.goto(NAP_URLS.createHivLab, { waitUntil: 'domcontentloaded', timeout: 15000 });
    await page.waitForLoadState('networkidle').catch(() => {});

    if (page.url().includes('iam.nhso.go.th')) {
        throw new Error('Session expired — redirected to login (lab request)');
    }

    // Select ANTIHIV
    await page.selectOption('#hiv_lab_type', '1');
    await delay(500);

    // Fill PID + date
    await page.fill('input[name="pid"]', pid);
    await page.fill('input[name="hiv_labreq_date"]', labDate);

    // Click "เพิ่มข้อมูลการส่งตรวจ HIV"
    await page.click('input#cmdSearch');
    await page.waitForLoadState('networkidle').catch(() => {});
    await delay(1500);

    if (dryRun) {
        await page.screenshot({ path: `automation/screenshots/dryrun_lab_${pid}.png`, fullPage: true });
        return 'DRY_RUN_LAB';
    }

    // Click บันทึก
    await page.click('input#cmdPreview');
    await page.waitForLoadState('networkidle').catch(() => {});
    await delay(2000);

    // Check for "ใช้สิทธิเกิน 2 ครั้ง" confirmation
    const needsConfirm = await page.isVisible('input[name="confirmPid"]').catch(() => false);
    if (needsConfirm) {
        console.log('[Lab] Over-limit confirmation — filling PID');
        await page.fill('input[name="confirmPid"]', pid);
        await page.click('input[name="btnSubmit"]');
        await page.waitForLoadState('networkidle').catch(() => {});
        await delay(2000);
    }

    // Click ตกลง on any confirm pages (loop like VCT)
    for (let attempt = 0; attempt < 3; attempt++) {
        const confirmBtn = await page.$('input[value="ตกลง"]');
        if (confirmBtn && await confirmBtn.isVisible()) {
            console.log('[Lab] Confirm page — clicking ตกลง');
            await confirmBtn.click();
            await page.waitForLoadState('networkidle', { timeout: 30000 }).catch(() => {});
            await delay(2000);
        } else {
            break;
        }
    }

    // Extract lab code — retry a few times
    let labCode = null;
    for (let attempt = 0; attempt < 3; attempt++) {
        labCode = await page.evaluate(() => {
            const text = document.body.innerText;
            // Look for ANTIHIV pattern
            const match = text.match(/ANTIHIV-[\d-]+/);
            if (match) return match[0];

            // Fallback: look in table cells
            const tds = [...document.querySelectorAll('td')];
            const labelTd = tds.find(td => td.textContent.includes('เลขที่ใบส่งตรวจทางห้องปฏิบัติการ'));
            if (labelTd) {
                const nextTd = labelTd.nextElementSibling;
                return nextTd ? nextTd.textContent.trim() : null;
            }
            return null;
        });

        if (labCode) break;
        console.log(`[Lab] Code not found yet (attempt ${attempt + 1}/3) — waiting...`);
        await delay(2000);
    }

    console.log(`[Lab] Extracted code: ${labCode}`);
    return labCode;
}

// ============================================================
// Main
// ============================================================

async function run() {
    const { jobId, dataFile } = parseArgs();
    const jobData = readDataFile(dataFile);
    const { ablyKey, ablyChannel, items, callbackUrl, dryRun, formType } = jobData;

    const { callbackUrl: cbUrl, fy: jobFy } = jobData;
    const ably = createAblyPublisher(ablyKey, ablyChannel);
    const total = items?.length || 0;
    const isDryRun = !!dryRun;
    const isVCT = (formType || 'RR').toUpperCase() === 'VCT';
    const formLabel = isVCT ? 'VCT' : 'RR';

    log(jobId, `Starting ThaiID login flow — ${formLabel} (${total} records)${isDryRun ? ' [DRY RUN]' : ''}`);

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
        const startUrl = isVCT ? NAP_URLS.createVCT : NAP_URLS.createRR;
        const loginOk = await loginViaThaiId(loginPage, ably, jobId, startUrl);

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
            const uic = item.uic || '';
            const pidMasked = 'xxxx' + (item.id_card || '').slice(-4);

            await ably?.publish('job:record:processing', {
                jobId, index: i + 1, total, pidMasked, uic,
                message: `📄 กำลังบันทึก ${formLabel} (${i + 1}/${total}) | ${uic} | PID: ${pidMasked}`,
            }, 300);

            await ably?.publish('job:record:searching', {
                jobId, index: i + 1, total,
                message: `🔍 กำลังค้นหาข้อมูลบุคคล... (${i + 1}/${total})`,
            }, 800);

            try {
                await ably?.publish('job:record:filling', {
                    jobId, index: i + 1, total,
                    message: `✏️ กำลังกรอกข้อมูล ${formLabel}... (${i + 1}/${total})`,
                }, 500);

                if (!isDryRun) {
                    await ably?.publish('job:record:submitting', {
                        jobId, index: i + 1, total,
                        message: `💾 กำลังบันทึก ${formLabel}... (${i + 1}/${total})`,
                    }, 500);
                }

                if (isVCT) {
                    // === VCT Flow (2-step callback) ===
                    let vctCode = item.nap_vct_code || null;
                    let labCode = null;
                    const skipVCT = !!vctCode;

                    // Step 1: Record VCT
                    if (skipVCT) {
                        log(jobId, `  Record ${i + 1}: VCT code exists (${vctCode}) — skipping VCT`);
                        await ably?.publish('job:record:filling', {
                            jobId, index: i + 1, total,
                            message: `⏭️ มี VCT ID แล้ว (${vctCode}) — ข้ามไปทำ Request Lab`,
                        }, 300);
                    } else {
                        try {
                            const vctResult = await fillAndSubmitVCT(page, item, isDryRun);
                            if (isDryRun && vctResult?.dryRun) {
                                vctCode = 'DRY_RUN';
                            } else {
                                vctCode = vctResult;
                            }
                        } catch (vctErr) {
                            // Check if error contains VCT code (duplicate case)
                            const errMsg = vctErr.message || '';
                            const vctMatch = errMsg.match(/V\d{2}-\d+-\d+/);
                            if (vctMatch) {
                                vctCode = vctMatch[0];
                                log(jobId, `  Record ${i + 1}: VCT ซ้ำ แต่ได้ code ${vctCode}`);
                            } else {
                                // Send error callback
                                if (!isDryRun && cbUrl) {
                                    await sendCallback(cbUrl, buildErrorCallback(item, jobFy, errMsg, null));
                                }
                                throw vctErr; // re-throw to outer catch
                            }
                        }
                    }

                    // Callback #1: VCT code
                    if (vctCode && !isDryRun) {
                        log(jobId, `  Record ${i + 1}: VCT=${vctCode} — sending VCT callback`);
                        await ably?.publish('job:record:success', {
                            jobId, index: i + 1, total, napCode: vctCode, uic,
                            message: `✅ บันทึกสำเร็จ (${i + 1}/${total}) | PID: ${pidMasked} | VCT: ${vctCode}`,
                        }, 300);
                        if (cbUrl) {
                            await sendCallback(cbUrl, buildVctCallback(item, jobFy, vctCode));
                        }
                    }

                    // Step 2: Request Lab (if needed)
                    if (vctCode && item.request_lab) {
                        try {
                            await ably?.publish('job:record:filling', {
                                jobId, index: i + 1, total,
                                message: `🔬 กำลังบันทึก Request Lab HIV... (${i + 1}/${total})`,
                            }, 500);
                            if (!isDryRun) {
                                labCode = await fillAndSubmitLabRequest(page, item, false);
                            } else {
                                labCode = await fillAndSubmitLabRequest(page, item, true);
                            }
                            log(jobId, `  Record ${i + 1}: Lab = ${labCode}`);

                            // Callback #2: Lab code
                            if (labCode && !isDryRun && cbUrl) {
                                await ably?.publish('job:record:success', {
                                    jobId, index: i + 1, total, labCode, uic, pidMasked,
                                    message: `✅ Lab สำเร็จ (${i + 1}/${total}) | PID: ${pidMasked} | ANTIHIV: ${labCode}`,
                                }, 300);
                                await sendCallback(cbUrl, buildLabCallback(item, jobFy, labCode));
                            }
                        } catch (labErr) {
                            log(jobId, `  Record ${i + 1}: Lab error = ${labErr.message}`);
                            await ably?.publish('job:record:failed', {
                                jobId, index: i + 1, total, error: labErr.message, uic,
                                message: `❌ Lab ล้มเหลว (${i + 1}/${total}) | ${labErr.message}`,
                            }, 300);
                        }
                    }

                    // Record result
                    if (vctCode) {
                        results.push({
                            id_card: item.id_card, success: true,
                            nap_code: vctCode, nap_lab_code: labCode,
                            error: null,
                        });
                    } else {
                        results.push({
                            id_card: item.id_card, success: false,
                            nap_code: null, nap_lab_code: null,
                            error: 'ไม่พบรหัส VCT',
                        });
                        await ably?.publish('job:record:failed', {
                            jobId, index: i + 1, total, error: 'ไม่พบรหัส VCT', uic,
                            message: `❌ ล้มเหลว (${i + 1}/${total}) | ไม่พบรหัส VCT`,
                        }, 300);
                    }
                } else {
                    // === RR Flow (existing) ===
                    const rrForm = item.rr_form;

                    const rrCode = await fillAndSubmitRecord(page, rrForm, isDryRun);

                    if (isDryRun && rrCode?.dryRun) {
                        const report = rrCode.report;
                        log(jobId, `  Record ${i + 1}: DRY RUN — form filled`);

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
                            id_card: item.id_card, uic, success: true,
                            nap_code: 'DRY_RUN', nap_lab_code: null,
                            error: null, report,
                        });

                        await ably?.publish('job:record:report', {
                            jobId, index: i + 1, total, uic, pidMasked,
                            report, message: lines.join('\n'),
                        }, 500);
                    } else if (rrCode) {
                        log(jobId, `  Record ${i + 1}: ${rrCode}`);
                        results.push({ id_card: item.id_card, success: true, nap_code: rrCode, nap_lab_code: null, error: null });
                        await ably?.publish('job:record:success', {
                            jobId, index: i + 1, total, napCode: rrCode, uic,
                            message: `✅ สำเร็จ (${i + 1}/${total}) | ${rrCode}`,
                        }, 300);
                    } else {
                        results.push({ id_card: item.id_card, success: false, nap_code: null, nap_lab_code: null, error: 'ไม่พบรหัส RR' });
                        await ably?.publish('job:record:failed', {
                            jobId, index: i + 1, total, error: 'ไม่พบรหัส RR', uic,
                            message: `❌ ล้มเหลว (${i + 1}/${total}) | ไม่พบรหัส RR`,
                        }, 300);
                    }
                }
            } catch (err) {
                log(jobId, `  Record ${i + 1} error: ${err.message}`);
                results.push({ id_card: item.id_card, success: false, nap_code: null, nap_lab_code: null, error: err.message });
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
