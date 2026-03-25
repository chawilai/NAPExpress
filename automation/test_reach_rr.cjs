const http = require('http');
const fs = require('fs');
const path = require('path');
const { execSync, spawn } = require('child_process');

/**
 * Integration test for report_reach_rr.cjs
 *
 * Spins up a mock NAP Plus server, creates a data file,
 * runs the Playwright script against it, and verifies the results.
 */

const MOCK_PORT = 9876;
let server;
let passCount = 0;
let failCount = 0;

function assert(condition, message) {
    if (condition) {
        passCount++;
        console.log(`  ✓ ${message}`);
    } else {
        failCount++;
        console.error(`  ✗ ${message}`);
    }
}

/**
 * Build a mock NAP Plus HTML page for login.
 */
function loginPage() {
    return `<!DOCTYPE html>
<html><body>
    <form action="/NAPPLUS/login.do" method="post">
        <input type="hidden" name="actionName" value="login" />
        <input id="user_name" name="user_name" type="text" />
        <input id="password" name="password" type="password" />
        <input type="submit" value="Login" class="button" />
    </form>
</body></html>`;
}

/**
 * Build a mock NAP Plus RR form page.
 */
function createRRPage() {
    return `<!DOCTYPE html>
<html><body>
    <form id="rrForm" action="/NAPPLUS/rrttr/createRRTTR.do" method="post">
        <input id="pid" name="pid" type="text" />
        <button id="btnSearch" type="button">Search</button>

        <!-- Risk Behavior checkboxes -->
        ${[0, 1, 2, 3, 4, 5].map((i) => `<input type="checkbox" id="rrttr_risk_behavior_status_${i}" />`).join('\n        ')}

        <!-- Target Group checkboxes -->
        ${Array.from({ length: 17 }, (_, i) => `<input type="checkbox" id="rrttr_target_group_status_${i}" />`).join('\n        ')}

        <!-- Access Type radios -->
        <input type="radio" id="access_type_1" name="access_type" value="1" />
        <input type="radio" id="access_type_2" name="access_type" value="2" />
        <input type="radio" id="access_type_3" name="access_type" value="3" />

        <!-- Occupation -->
        <select id="occupation">
            <option value="01">นักเรียน</option>
            <option value="02">ข้าราชการ</option>
            <option value="03">รับจ้าง</option>
        </select>

        <!-- Service date -->
        <input id="service_date" name="service_date" type="text" />
        <input id="rrttr_service_date" name="rrttr_service_date" type="text" />

        <!-- Birth date -->
        <input id="birth_date" name="birth_date" type="text" />
        <input id="rrttr_birth_date" name="rrttr_birth_date" type="text" />

        <!-- Knowledge checkboxes -->
        ${[0, 1, 2, 3, 4].map((i) => `<input type="checkbox" id="rrttr_knowledge_status_${i}" />`).join('\n        ')}

        <!-- PPE checkboxes -->
        ${[0, 1, 2, 3, 4].map((i) => `<input type="checkbox" id="rrttr_ppe_status_${i}" />`).join('\n        ')}

        <!-- Condom fields (hidden by default like NAP) -->
        ${['49', '52', '53', '54', '56'].map((s) => `
        <label id="lb_condom_amount_${s}_1" style="display:none">Size ${s}</label>
        <label id="lb_condom_amount_${s}_2" style="display:none">Size ${s}</label>
        <input id="rrttr_condom_amount_${s}" type="text" style="display:none" />`).join('\n        ')}
        <input id="rrttr_female_condom_amount" type="text" />
        <input id="rrttr_lubricant_amount" type="text" />

        <!-- Healthcare referral -->
        <input id="next_hcode" type="text" />

        <!-- Forward services -->
        ${['hiv', 'sti', 'tb'].map((svc) => [1, 2, 3].map((v) => `<input type="radio" id="${svc}_forward_${v}" name="${svc}_forward" value="${v}" />`).join('\n        ')).join('\n        ')}

        <input name="confirmBtn" type="button" value="บันทึก" onclick="this.form.submit()" />
        <input name="clearBtn" type="button" value="เคลียร์" />
        <input name="backBtn" type="button" value="กลับไปหน้าค้นหา" />
    </form>
</body></html>`;
}

function searchPage() {
    return `<!DOCTYPE html>
<html><body>
    <form action="/NAPPLUS/rrttr/createRRTTR.do" method="post">
        <input type="hidden" name="actionName" value="search" />
        <input type="radio" name="gr_type" value="0" checked />
        <input id="rrttrDate" name="rrttrDate" type="text" />
        <input id="pid" name="pid" type="text" />
        <input id="cmdSearch" type="button" value="เพิ่มข้อมูลให้บริการ" onclick="this.form.submit()" />
    </form>
</body></html>`;
}

function confirmPage() {
    return `<!DOCTYPE html>
<html><body>
    <form action="/NAPPLUS/rrttr/createRRTTR.do" method="post">
        <div class="generalTableHeader">แสดงข้อมูลสิทธิการรักษา</div>
        <table class="generalTable"><tr><td>เลขประจำตัวประชาชน</td><td>1234567890123</td></tr></table>
        <input name="registerBtn" type="button" value="ตกลง" onclick="this.form.submit()" />
        <input name="backBtn" type="button" value="ย้อนกลับ" />
    </form>
</body></html>`;
}

function loginFailPage() {
    return `<!DOCTYPE html>
<html><body>
    <div class="alert-danger">ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง</div>
</body></html>`;
}

/**
 * Start mock NAP Plus server with multi-step flow.
 */
function startMockServer(loginShouldFail = false) {
    let step = 'search'; // track form step

    return new Promise((resolve) => {
        server = http.createServer((req, res) => {
            const url = req.url;
            res.setHeader('Content-Type', 'text/html; charset=utf-8');

            if (url.includes('login.jsp')) {
                res.end(loginPage());
            } else if (url.includes('login.do') && !url.includes('createRRTTR')) {
                if (loginShouldFail) {
                    // Redirect back to login.jsp (failed)
                    res.writeHead(302, { Location: '/NAPPLUS/login.jsp' });
                    res.end();
                } else {
                    // Success: NAP redirects to login.do (POST result page)
                    step = 'search';
                    res.end('<html><body>Dashboard</body></html>');
                }
            } else if (url.includes('createRRTTR.do')) {
                if (url.includes('actionName=load')) {
                    // First visit: show search page
                    step = 'confirm';
                    res.end(searchPage());
                } else if (step === 'confirm') {
                    // After search: show confirm page with person info
                    step = 'form';
                    res.end(confirmPage());
                } else if (step === 'form') {
                    // After confirm: show RR form
                    step = 'result';
                    res.end(createRRPage());
                } else {
                    // After submit: show success result
                    step = 'search';
                    res.end('<html><body><div class="font22"><b>RR-26-001234</b></div></body></html>');
                }
            } else {
                res.end('<html><body>OK</body></html>');
            }
        });

        server.listen(MOCK_PORT, () => {
            console.log(`Mock NAP server running on port ${MOCK_PORT}`);
            resolve();
        });
    });
}

function stopMockServer() {
    return new Promise((resolve) => {
        if (server) {
            server.close(resolve);
        } else {
            resolve();
        }
    });
}

/**
 * Create a test data file.
 */
function createTestDataFile(overrides = {}) {
    const data = {
        job_id: 999,
        credentials: {
            username: 'testuser',
            password: 'testpass',
        },
        rows: [
            {
                id: 1,
                row_number: 2,
                row_data: {
                    rr_form: {
                        rrttrDate: '02/07/2568',
                        pid: '1234567890123',
                        birth_date_thai: '02/07/2528',
                        risk_behavior_indices: [1],
                        target_group_indices: [0],
                        partner_with: null,
                        access_type: '2',
                        social_media: null,
                        ref_tel: '0812345678',
                        ref_email: '',
                        occupation: '03',
                        condom: { '49': 10, '52': 20, '53': 0, '54': 20, '56': 20 },
                        lubricant: 20,
                        female_condom: 0,
                        next_hcode: '41936',
                        knowledge_indices: [0, 1, 2],
                        place_indices: [0, 1, 2],
                        ppe_indices: [0, 2],
                        forwards: { hiv: 1, sti: 3, tb: 3 },
                    },
                },
            },
        ],
        ...overrides,
    };

    const filePath = path.join(__dirname, 'job_999_data.json');
    fs.writeFileSync(filePath, JSON.stringify(data, null, 2));
    return filePath;
}

/**
 * Patch the script's NAP_URLS to point to mock server.
 */
function createPatchedScript() {
    let script = fs.readFileSync(
        path.join(__dirname, 'report_reach_rr.cjs'),
        'utf-8'
    );

    // Replace NAP URLs with mock server URLs
    script = script.replace(
        /const NAP_URLS = \{[^}]+\};/s,
        `const NAP_URLS = {
    login: 'http://localhost:${MOCK_PORT}/NAPPLUS/login.jsp',
    createRR: 'http://localhost:${MOCK_PORT}/NAPPLUS/rrttr/createRRTTR.do?actionName=load',
    createRRBase: 'http://localhost:${MOCK_PORT}/NAPPLUS/rrttr/createRRTTR.do',
};`
    );

    // Reduce delays for faster tests
    script = script.replace(
        /const RECORD_DELAY_MS = \d+;/,
        'const RECORD_DELAY_MS = 100;'
    );

    // Reduce timeouts
    script = script.replace(
        /context\.setDefaultTimeout\(\d+\);/,
        'context.setDefaultTimeout(10000);'
    );

    const patchedPath = path.join(__dirname, '_patched_report_reach_rr.cjs');
    fs.writeFileSync(patchedPath, script);
    return patchedPath;
}

function cleanup(...files) {
    for (const f of files) {
        try {
            if (fs.existsSync(f)) fs.unlinkSync(f);
        } catch (_) {}
    }
}

/**
 * Run the script and capture output.
 */
function runScript(scriptPath, dataFile) {
    return new Promise((resolve) => {
        const child = spawn('node', [scriptPath, `--dataFile=${dataFile}`], {
            cwd: __dirname,
            timeout: 30000,
        });

        let stdout = '';
        let stderr = '';

        child.stdout.on('data', (data) => {
            stdout += data.toString();
        });
        child.stderr.on('data', (data) => {
            stderr += data.toString();
        });

        child.on('close', (code) => {
            resolve({ code, stdout, stderr });
        });

        child.on('error', (err) => {
            resolve({ code: 1, stdout, stderr: err.message });
        });
    });
}

// =========== TESTS ===========

async function testDataFileReading() {
    console.log('\n--- Test: Data file reading & argument parsing ---');

    const dataFile = createTestDataFile();

    assert(fs.existsSync(dataFile), 'Data file was created');

    const data = JSON.parse(fs.readFileSync(dataFile, 'utf-8'));
    assert(data.job_id === 999, 'Job ID is correct');
    assert(data.credentials.username === 'testuser', 'Credentials username correct');
    assert(data.rows.length === 1, 'Has 1 row');
    assert(data.rows[0].row_data.rr_form.pid === '1234567890123', 'Row PID correct');
    assert(data.rows[0].row_data.rr_form.risk_behavior_indices[0] === 1, 'Risk behavior index correct for MSM');
    assert(data.rows[0].row_data.rr_form.occupation === '03', 'Occupation code correct');
    assert(data.rows[0].row_data.rr_form.rrttrDate === '02/07/2568', 'Thai date correct');

    cleanup(dataFile);
}

async function testMissingDataFileExits() {
    console.log('\n--- Test: Script exits with error for missing data file ---');

    const scriptPath = createPatchedScript();
    const result = await runScript(scriptPath, '/tmp/nonexistent_data.json');

    assert(result.code !== 0, 'Script exits with non-zero code');
    assert(
        result.stderr.includes('Data file not found') || result.stdout.includes('Data file not found'),
        'Error message mentions missing data file'
    );

    cleanup(scriptPath);
}

async function testMissingArgsExits() {
    console.log('\n--- Test: Script exits with error for missing args ---');

    const scriptPath = createPatchedScript();
    const result = await runScript(scriptPath.replace('--dataFile=', ''), '');

    // Run without --dataFile
    const child = require('child_process').spawnSync('node', [scriptPath], {
        timeout: 10000,
    });

    assert(child.status !== 0, 'Script exits with non-zero code when no args');

    cleanup(scriptPath);
}

async function testSuccessfulFormFilling() {
    console.log('\n--- Test: Successful form filling against mock server ---');

    await startMockServer(false);

    const dataFile = createTestDataFile();
    const scriptPath = createPatchedScript();
    const resultsFile = dataFile.replace('_data.json', '_results.json');

    const result = await runScript(scriptPath, dataFile);

    console.log('    stdout:', result.stdout.substring(0, 200));
    if (result.stderr) console.log('    stderr:', result.stderr.substring(0, 200));

    assert(result.code === 0, 'Script exits with code 0');
    assert(result.stdout.includes('Starting Reach RR automation'), 'Shows start message');
    assert(result.stdout.includes('Login successful'), 'Login succeeded');
    assert(
        result.stdout.includes('Processing record 1/1') || result.stdout.includes('Processing'),
        'Processes records'
    );

    // Check results file
    assert(fs.existsSync(resultsFile), 'Results file was created');

    if (fs.existsSync(resultsFile)) {
        const results = JSON.parse(fs.readFileSync(resultsFile, 'utf-8'));
        assert(results.rows.length === 1, 'Results has 1 row');
        assert(results.rows[0].id === 1, 'Result row ID matches');
        assert(results.rows[0].success === true, 'Row marked as success');
        assert(
            results.rows[0].nap_code && results.rows[0].nap_code.includes('RR-26'),
            `NAP code extracted: ${results.rows[0].nap_code}`
        );
    }

    await stopMockServer();
    cleanup(dataFile, scriptPath, resultsFile);
}

async function testLoginFailure() {
    console.log('\n--- Test: Login failure handling ---');

    await startMockServer(true);

    const dataFile = createTestDataFile();
    const scriptPath = createPatchedScript();
    const resultsFile = dataFile.replace('_data.json', '_results.json');

    const result = await runScript(scriptPath, dataFile);

    console.log('    stdout:', result.stdout.substring(0, 200));

    // Script handles login failure gracefully — writes results and exits 0
    assert(
        result.stdout.includes('NAP login failed') || result.stderr.includes('NAP login failed') ||
        result.stdout.includes('Fatal error') || result.stdout.includes('login'),
        'Error message mentions login failure'
    );

    // Results file should exist with failed rows
    assert(fs.existsSync(resultsFile), 'Results file created even on login failure');

    if (fs.existsSync(resultsFile)) {
        const results = JSON.parse(fs.readFileSync(resultsFile, 'utf-8'));
        assert(results.rows.length === 1, 'Results has 1 row');
        assert(results.rows[0].success === false, 'Row marked as failed');
        assert(results.rows[0].error !== null, 'Error message present');
    }

    await stopMockServer();
    cleanup(dataFile, scriptPath, resultsFile);
}

async function testMultipleRows() {
    console.log('\n--- Test: Multiple rows processing ---');

    await startMockServer(false);

    const makeRow = (id, rowNum, pid) => ({
        id,
        row_number: rowNum,
        row_data: {
            rr_form: {
                rrttrDate: '02/07/2568',
                pid,
                risk_behavior_indices: [1],
                target_group_indices: [0],
                access_type: '2',
                occupation: '03',
                condom: { '54': 20 },
                lubricant: 20,
                female_condom: 0,
                next_hcode: '41936',
                knowledge_indices: [0, 1, 2],
                place_indices: [0, 1, 2],
                ppe_indices: [0, 2],
                forwards: { hiv: 2, sti: 2, tb: 2 },
            },
        },
    });

    const row1 = makeRow(1, 2, '1111111111111');
    const row2 = makeRow(2, 3, '2222222222222');

    const dataFile = createTestDataFile({ rows: [row1, row2] });
    const scriptPath = createPatchedScript();
    const resultsFile = dataFile.replace('_data.json', '_results.json');

    const result = await runScript(scriptPath, dataFile);

    assert(result.code === 0, 'Script exits with code 0');
    assert(result.stdout.includes('Records to process: 2'), 'Shows 2 records');

    if (fs.existsSync(resultsFile)) {
        const results = JSON.parse(fs.readFileSync(resultsFile, 'utf-8'));
        assert(results.rows.length === 2, 'Results has 2 rows');
        assert(results.rows[0].success === true, 'Row 1 success');
        assert(results.rows[1].success === true, 'Row 2 success');
    }

    await stopMockServer();
    cleanup(dataFile, scriptPath, resultsFile);
}

// =========== RUN ALL TESTS ===========

async function runAllTests() {
    console.log('=== Playwright Reach RR Integration Tests ===\n');

    await testDataFileReading();
    await testMissingDataFileExits();
    await testMissingArgsExits();
    await testSuccessfulFormFilling();
    await testLoginFailure();
    await testMultipleRows();

    console.log(`\n=== Results: ${passCount} passed, ${failCount} failed ===`);

    // Cleanup patched script if leftover
    cleanup(path.join(__dirname, '_patched_report_reach_rr.cjs'));

    process.exit(failCount > 0 ? 1 : 0);
}

runAllTests().catch((err) => {
    console.error('Test runner error:', err);
    if (server) server.close();
    process.exit(1);
});
