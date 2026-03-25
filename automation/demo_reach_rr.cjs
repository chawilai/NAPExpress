const { chromium } = require('playwright');

/**
 * Demo: NAP Reach RR form filling (HEADED — visible browser)
 * Fills all fields but STOPS before saving.
 */

const NAP_URLS = {
    login: 'https://dmis.nhso.go.th/NAPPLUS/login.jsp',
    createRR: 'https://dmis.nhso.go.th/NAPPLUS/rrttr/createRRTTR.do',
};

const CREDENTIALS = {
    username: '65127054298057',
    password: 'mpluscmi003',
};

const FORM_DATA = {
    pid: '1550700153989',
    rrttrDate: '04/03/2569',
    birth_date_thai: '16/07/2542',
    risk_behavior_indices: [1],
    target_group_indices: [0],
    access_type: '2',
    occupation: '06',
    condom: { '49': 0, '52': 20, '53': 0, '54': 20, '56': 20 },
    lubricant: 20,
    female_condom: 0,
    next_hcode: '41936',
    knowledge_indices: [0, 1, 2],
    place_indices: [0, 1, 2],
    ppe_indices: [0, 2],
    forwards: { hiv: 2, sti: 2, tb: 2 },
    ref_tel: '0617978524',
    hiv_test: { tested: false },
};

function log(step, msg) {
    console.log(`[Step ${step}] ${msg}`);
}

async function delay(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

async function dumpFormElements(page, label) {
    const elements = await page.evaluate(() => {
        return Array.from(document.querySelectorAll('input, select, textarea, button')).map(el => ({
            tag: el.tagName,
            id: el.id || '',
            name: el.name || '',
            type: el.type || '',
            value: el.type === 'password' ? '***' : (el.value || '').substring(0, 40),
            visible: el.offsetParent !== null,
        })).filter(el => el.id || el.name);
    });
    console.log(`\n--- ${label} (${elements.length} elements) ---`);
    for (const el of elements) {
        const vis = el.visible ? '✓' : '✗';
        console.log(`  [${vis}] <${el.tag.toLowerCase()} id="${el.id}" name="${el.name}" type="${el.type}" value="${el.value}">`);
    }
    console.log('');
}

async function tryFill(page, selectors, value, label) {
    for (const sel of selectors) {
        const el = await page.$(sel);
        if (el) {
            await el.fill(value);
            log('', `  ✓ ${label}: filled ${sel} = "${value}"`);
            return true;
        }
    }
    log('', `  ⚠ ${label}: not found (tried ${selectors.join(', ')})`);
    return false;
}

async function tryCheck(page, sel, label) {
    const el = await page.$(sel);
    if (el) {
        const visible = await el.isVisible();
        if (!visible) {
            log('', `  ⚠ ${label} ${sel}: exists but hidden`);
            return false;
        }
        if (!(await el.isChecked())) await el.check();
        log('', `  ✓ ${label}: checked ${sel}`);
        return true;
    }
    log('', `  ⚠ ${label}: not found ${sel}`);
    return false;
}

async function tryClick(page, sel, label) {
    const el = await page.$(sel);
    if (el) {
        await el.click();
        log('', `  ✓ ${label}: clicked ${sel}`);
        return true;
    }
    log('', `  ⚠ ${label}: not found ${sel}`);
    return false;
}

async function run() {
    const browser = await chromium.launch({
        headless: false,
        slowMo: 200,
        args: ['--window-size=1400,900'],
    });

    const context = await browser.newContext({ viewport: { width: 1400, height: 900 } });
    const page = await context.newPage();
    page.setDefaultTimeout(15000);

    try {
        // ===== Step 1: Login =====
        log(1, 'Logging in to NAP Plus...');
        await page.goto(NAP_URLS.login, { waitUntil: 'networkidle' });
        await page.fill('#user_name', CREDENTIALS.username);
        await page.fill('#password', CREDENTIALS.password);
        await page.click('input[type="submit"]');
        await page.waitForLoadState('networkidle');
        await delay(1500);
        await page.screenshot({ path: 'automation/screenshots/01_after_login.png' });
        log(1, `Done — URL: ${page.url()}`);

        // ===== Step 2: Navigate to Create RR =====
        log(2, 'Opening Create RR page...');
        await page.goto(NAP_URLS.createRR, { waitUntil: 'networkidle' });
        await delay(1500);
        await page.screenshot({ path: 'automation/screenshots/02_search_page.png' });

        // Dump form structure before search
        await dumpFormElements(page, 'Search Page Form Elements');

        // ===== Step 3: Fill search form and submit =====
        log(3, 'Filling search form...');

        // 3a. Select radio gr_type = 0 (ค้นหาด้วยเลขบัตรประชาชน)
        const grRadio = await page.$('input[name="gr_type"][value="0"]');
        if (grRadio) {
            await grRadio.check();
            log(3, '  Selected gr_type = 0 (PID search)');
        }

        // 3b. Fill rrttrDate FIRST (required before search)
        await page.fill('#rrttrDate', FORM_DATA.rrttrDate);
        log(3, `  Filled rrttrDate = ${FORM_DATA.rrttrDate}`);
        await delay(300);

        // 3c. Fill PID
        await page.fill('#pid', FORM_DATA.pid);
        log(3, `  Filled PID = ${FORM_DATA.pid}`);
        await delay(300);

        await page.screenshot({ path: 'automation/screenshots/03a_before_search.png' });

        // 3d. Listen for dialogs (alert/confirm)
        page.on('dialog', async (dialog) => {
            log(3, `  Dialog: ${dialog.type()} — "${dialog.message()}"`);
            await dialog.accept();
        });

        // 3e. Click search
        log(3, 'Clicking #cmdSearch...');
        await page.click('#cmdSearch');

        // Wait for navigation or AJAX load
        try {
            await page.waitForLoadState('networkidle', { timeout: 10000 });
        } catch (e) {
            log(3, '  (networkidle timeout — page might use AJAX)');
        }
        await delay(3000);

        await page.screenshot({ path: 'automation/screenshots/03b_after_search.png', fullPage: true });
        log(3, `After search — URL: ${page.url()}`);
        log(3, 'Search complete — screenshot saved');

        // Dump confirmation page elements
        await dumpFormElements(page, 'Confirmation Page Elements');

        // ===== Step 3c: Check consent checkbox if exists, then click ตกลง =====
        log(3, 'Clicking ตกลง (confirm person)...');

        // Check consent checkbox if present
        const consentCheckbox = await page.$('input[type="checkbox"]');
        if (consentCheckbox) {
            const isChecked = await consentCheckbox.isChecked();
            if (!isChecked) {
                await consentCheckbox.check();
                log(3, '  Checked consent checkbox');
            }
        }

        await delay(300);

        // Click ตกลง (registerBtn)
        await page.click('input[name="registerBtn"]');
        log(3, 'Clicked ตกลง');

        try {
            await page.waitForLoadState('networkidle', { timeout: 15000 });
        } catch (e) {
            log(3, '  (networkidle timeout)');
        }
        await delay(3000);

        await page.screenshot({ path: 'automation/screenshots/03c_rr_form.png', fullPage: true });
        log(3, `RR Form loaded — URL: ${page.url()}`);

        // Dump the REAL RR form elements
        await dumpFormElements(page, 'Actual RR Form Elements');

        // ===== Step 4: Fill the RR form =====
        log(4, '=== Filling RR Form ===');

        // 4a. Service Date
        log(4, 'Service Date...');
        await tryFill(page, ['#rrttrDate', '#service_date', '#rrttr_service_date', 'input[name="rrttrDate"]'], FORM_DATA.rrttrDate, 'rrttrDate');

        // 4b. Birth Date
        log(4, 'Birth Date...');
        await tryFill(page, ['#birth_date', '#rrttr_birth_date', '#birthDate', 'input[name="birthDate"]'], FORM_DATA.birth_date_thai, 'birthDate');

        // 4c. Risk Behavior checkboxes
        log(4, 'Risk Behavior checkboxes...');
        for (const idx of FORM_DATA.risk_behavior_indices) {
            await tryCheck(page, `#rrttr_risk_behavior_status_${idx}`, `risk[${idx}]`);
        }

        // 4d. Target Group checkboxes
        log(4, 'Target Group checkboxes...');
        for (const idx of FORM_DATA.target_group_indices) {
            await tryCheck(page, `#rrttr_target_group_status_${idx}`, `target[${idx}]`);
        }

        // 4e. Access Type
        log(4, 'Access Type...');
        await tryClick(page, `#access_type_${FORM_DATA.access_type}`, `access_type=${FORM_DATA.access_type}`);

        // 4f. Occupation
        log(4, 'Occupation...');
        const occEl = await page.$('#occupation');
        if (occEl) {
            await page.selectOption('#occupation', FORM_DATA.occupation);
            log('', '  ✓ occupation: selected ' + FORM_DATA.occupation);
        } else {
            log('', '  ⚠ occupation: #occupation not found');
        }

        // 4g. Knowledge checkboxes
        log(4, 'Knowledge checkboxes...');
        for (const idx of FORM_DATA.knowledge_indices) {
            await tryCheck(page, `#rrttr_knowledge_status_${idx}`, `knowledge[${idx}]`);
        }

        // 4h. Place checkboxes
        log(4, 'Place checkboxes...');
        for (const idx of FORM_DATA.place_indices) {
            await tryCheck(page, `#rrttr_place_status_${idx}`, `place[${idx}]`);
        }

        // 4i. PPE checkboxes
        log(4, 'PPE checkboxes...');
        for (const idx of FORM_DATA.ppe_indices) {
            await tryCheck(page, `#rrttr_ppe_status_${idx}`, `ppe[${idx}]`);
        }

        // 4j. Condom fields — unhide first
        log(4, 'Condom fields (unhide + fill)...');
        await page.evaluate(() => {
            ['49', '52', '53', '54', '56'].forEach((size) => {
                document.querySelectorAll(`#lb_condom_amount_${size}_1, #lb_condom_amount_${size}_2`).forEach((el) => { el.style.display = 'inline'; });
                const input = document.querySelector(`#rrttr_condom_amount_${size}`);
                if (input) input.style.display = 'inline';
            });
        });
        for (const [size, amount] of Object.entries(FORM_DATA.condom)) {
            if (amount > 0) {
                await tryFill(page, [`#rrttr_condom_amount_${size}`], String(amount), `condom_${size}`);
            }
        }
        if (FORM_DATA.female_condom > 0) {
            await tryFill(page, ['#rrttr_female_condom_amount'], String(FORM_DATA.female_condom), 'female_condom');
        }
        if (FORM_DATA.lubricant > 0) {
            await tryFill(page, ['#rrttr_lubricant_amount'], String(FORM_DATA.lubricant), 'lubricant');
        }

        // 4k. Healthcare referral
        log(4, 'Healthcare referral...');
        await tryFill(page, ['#next_hcode'], FORM_DATA.next_hcode, 'next_hcode');

        // 4l. Forward services
        log(4, 'Forward services...');
        for (const [svc, val] of Object.entries(FORM_DATA.forwards)) {
            if (val) await tryClick(page, `#${svc}_forward_${val}`, `${svc}_forward=${val}`);
        }

        // 4m. Phone
        log(4, 'Phone...');
        await tryFill(page, ['#ref_tel', '#rrttr_ref_tel', 'input[name="refTel"]'], FORM_DATA.ref_tel, 'ref_tel');

        await delay(1000);
        await page.screenshot({ path: 'automation/screenshots/04_form_filled.png', fullPage: true });

        // ===== DONE =====
        console.log('\n' + '='.repeat(50));
        console.log('  ✅ Form filled — STOPPED before Save');
        console.log('  📸 Screenshots in automation/screenshots/');
        console.log('  🖥  Browser open for inspection');
        console.log('  Press Ctrl+C to close');
        console.log('='.repeat(50) + '\n');

        await page.waitForTimeout(600000);
    } catch (error) {
        console.error('\n❌ Error:', error.message);
        await page.screenshot({ path: 'automation/screenshots/error.png', fullPage: true });

        // Save page HTML for debugging
        const fs = require('fs');
        const html = await page.content();
        fs.writeFileSync('automation/screenshots/error_page.html', html);

        await dumpFormElements(page, 'Elements at error time');

        console.log('Browser stays open. Press Ctrl+C to close.');
        await page.waitForTimeout(600000);
    }
}

run();
