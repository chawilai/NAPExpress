const { chromium } = require('playwright');
const fs = require('fs');
const Ably = require('ably');

/**
 * ThaiID Login Only — captures QR, waits for scan, exports cookies.
 *
 * Usage: node automation/thaid_login_only.cjs --dataFile=/path/to/data.json
 *
 * Input dataFile: { ablyKey, ablyChannel, jobId }
 * Output: writes cookies to {dataFile}_cookies.json
 */

function parseArgs() {
    const args = process.argv.slice(2);
    const dataFile = args.find(a => a.startsWith('--dataFile='))?.split('=')[1];
    if (!dataFile) { console.error('Usage: --dataFile=...'); process.exit(1); }
    return { dataFile };
}

async function run() {
    const { dataFile } = parseArgs();
    const data = JSON.parse(fs.readFileSync(dataFile, 'utf-8'));
    const { ablyKey, ablyChannel, jobId } = data;

    // Ably publisher
    let ably = null;
    if (ablyKey && ablyChannel) {
        const ablyClient = new Ably.Rest(ablyKey);
        const channel = ablyClient.channels.get(ablyChannel);
        ably = {
            publish: async (event, payload, delayMs = 0) => {
                try {
                    await channel.publish(event, payload);
                    if (delayMs > 0) await new Promise(r => setTimeout(r, delayMs));
                } catch (e) { console.error(`Ably error: ${e.message}`); }
            },
        };
    }

    console.log(`[${jobId}] Opening headed browser for ThaiID login...`);

    const browser = await chromium.launch({
        headless: false,
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-blink-features=AutomationControlled'],
    });

    const context = await browser.newContext({
        viewport: { width: 1280, height: 900 },
        locale: 'th-TH',
        timezoneId: 'Asia/Bangkok',
    });
    context.setDefaultTimeout(20000);
    const page = await context.newPage();

    page.on('dialog', async dlg => {
        console.log(`Dialog: "${dlg.message()}"`);
        await dlg.accept();
    });

    try {
        // Navigate to NAP (will redirect to Keycloak)
        await ably?.publish('job:start', { jobId, message: '🔐 เริ่มระบบ AutoNAP — กำลังเปิดหน้า Login' }, 500);
        await ably?.publish('job:connecting', { jobId, message: '🌐 กำลังเชื่อมต่อระบบ สปสช...' }, 1000);

        await page.goto('https://dmis.nhso.go.th/NAPPLUS/rrttr/createRRTTR.do', {
            waitUntil: 'networkidle',
            timeout: 30000,
        });

        if (!page.url().includes('iam.nhso.go.th')) {
            // Already logged in
            console.log(`[${jobId}] Already logged in!`);
            const cookies = await context.cookies();
            fs.writeFileSync(dataFile.replace('.json', '_cookies.json'), JSON.stringify(cookies, null, 2));
            await ably?.publish('job:login:success', { jobId, message: '✅ เข้าสู่ระบบแล้ว (session เดิม)' }, 500);
            await browser.close();
            process.exit(0);
        }

        // Click ThaiD button
        const thaidBtn = await page.$('#social-thaid, a[id="social-thaid"]');
        if (!thaidBtn) throw new Error('ThaiD button not found');
        await thaidBtn.click();

        await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
        await new Promise(r => setTimeout(r, 2000));

        // Capture QR code
        const qrData = await page.evaluate(() => {
            const img = document.querySelector('img[src^="data:image"]');
            return img ? { src: img.src } : null;
        });

        const refCode = await page.evaluate(() => {
            const text = document.body.innerText;
            const match = text.match(/หมายเลขอ้างอิง\s*:\s*(\w+)/);
            return match ? match[1] : null;
        });

        if (qrData) {
            console.log(`[${jobId}] QR code captured (ref: ${refCode})`);
            await ably?.publish('job:thaid:qr', {
                jobId,
                qrImage: qrData.src,
                refCode: refCode || '',
                message: '📱 กรุณาสแกน QR Code ด้วยแอป ThaiD',
            });
            await ably?.publish('job:thaid:waiting', { jobId, message: '⏳ รอการสแกน ThaiD... (หมดเวลาใน 2 นาที)' });
        }

        // Wait for login success (redirect to dmis.nhso.go.th)
        await page.waitForURL(url => {
            const u = url.toString();
            return u.includes('dmis.nhso.go.th') || u.includes('NAPPLUS');
        }, { timeout: 120000 });

        console.log(`[${jobId}] Login success! Extracting cookies...`);
        await ably?.publish('job:login:success', { jobId, message: '✅ Login สำเร็จ — ThaiD ยืนยันตัวตนแล้ว' }, 500);

        // Export cookies
        const cookies = await context.cookies();
        const cookieFile = dataFile.replace('.json', '_cookies.json');
        fs.writeFileSync(cookieFile, JSON.stringify(cookies, null, 2));
        console.log(`[${jobId}] Cookies saved: ${cookieFile} (${cookies.length} cookies)`);

        await browser.close();
        process.exit(0);

    } catch (error) {
        console.error(`[${jobId}] Error: ${error.message}`);
        await ably?.publish('job:login:failed', { jobId, message: `❌ ${error.message}` });
        await browser.close();
        process.exit(1);
    }
}

run();
