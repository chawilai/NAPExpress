const { chromium } = require('playwright');
const fs = require('fs');

/**
 * Inspect ThaiID login page structure.
 * Opens headed browser so we can see the QR code page.
 */
(async () => {
    const browser = await chromium.launch({ headless: false, slowMo: 300 });
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    const page = await context.newPage();

    console.log('1. Going to NAP Plus...');
    await page.goto('https://dmis.nhso.go.th/NAPPLUS/rrttr/createRRTTR.do', {
        waitUntil: 'networkidle',
        timeout: 30000,
    });

    console.log(`2. Current URL: ${page.url()}`);
    await page.screenshot({ path: 'automation/screenshots/thaid_01_redirect.png', fullPage: true });

    // Handle any alerts
    page.on('dialog', async (dlg) => {
        console.log(`   Dialog: "${dlg.message()}"`);
        await dlg.accept();
    });

    // Check if redirected to iam.nhso.go.th
    if (page.url().includes('iam.nhso.go.th')) {
        console.log('3. Redirected to Keycloak SSO login page');

        // Dump all form elements
        const elements = await page.evaluate(() => {
            return Array.from(document.querySelectorAll('input, button, a, img, [role="button"]')).map(el => ({
                tag: el.tagName,
                id: el.id || '',
                name: el.name || '',
                type: el.type || '',
                text: (el.textContent || '').trim().substring(0, 60),
                href: el.href || '',
                src: el.src ? el.src.substring(0, 80) : '',
                className: (el.className || '').toString().substring(0, 60),
            }));
        });

        console.log('\n=== Login Page Elements ===');
        for (const el of elements) {
            const info = [el.id, el.name, el.text, el.href].filter(Boolean).join(' | ');
            console.log(`  <${el.tag.toLowerCase()} type="${el.type}" class="${el.className}"> ${info}`);
        }

        // Look for ThaiD button
        console.log('\n4. Looking for ThaiD button...');
        const thaidBtn = await page.$('text=ThaiD') || await page.$('img[alt*="ThaiD"]') || await page.$('a:has-text("ThaiD")') || await page.$('button:has-text("ThaiD")');

        if (thaidBtn) {
            console.log('   Found ThaiD button! Clicking...');
            await thaidBtn.click();
            await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});
            await new Promise(r => setTimeout(r, 3000));

            console.log(`5. After ThaiD click URL: ${page.url()}`);
            await page.screenshot({ path: 'automation/screenshots/thaid_02_qr_page.png', fullPage: true });

            // Look for QR code image
            const qrImages = await page.evaluate(() => {
                return Array.from(document.querySelectorAll('img, canvas, svg')).map(el => ({
                    tag: el.tagName,
                    id: el.id || '',
                    src: el.src ? el.src.substring(0, 100) : '',
                    alt: el.alt || '',
                    width: el.offsetWidth,
                    height: el.offsetHeight,
                    className: (el.className || '').toString().substring(0, 60),
                })).filter(el => el.width > 100 && el.height > 100);
            });

            console.log('\n=== Potential QR Images ===');
            for (const img of qrImages) {
                console.log(`  <${img.tag} id="${img.id}" class="${img.className}" ${img.width}x${img.height}>`);
                console.log(`    src: ${img.src}`);
                console.log(`    alt: ${img.alt}`);
            }

            // Dump all elements on QR page
            const qrPageElements = await page.evaluate(() => {
                return Array.from(document.querySelectorAll('input, button, a, img, canvas, iframe, [role="button"]')).map(el => ({
                    tag: el.tagName,
                    id: el.id || '',
                    name: el.name || '',
                    text: (el.textContent || '').trim().substring(0, 60),
                    src: el.src ? el.src.substring(0, 100) : '',
                    className: (el.className || '').toString().substring(0, 60),
                }));
            });

            console.log('\n=== QR Page All Elements ===');
            for (const el of qrPageElements) {
                const info = [el.id, el.name, el.text, el.src].filter(Boolean).join(' | ');
                console.log(`  <${el.tag.toLowerCase()} class="${el.className}"> ${info}`);
            }
        } else {
            console.log('   ThaiD button not found!');
            // Dump page for debugging
            const html = await page.content();
            fs.writeFileSync('automation/screenshots/thaid_login_page.html', html);
            console.log('   Saved HTML to thaid_login_page.html');
        }
    }

    console.log('\nBrowser stays open. Press Ctrl+C to close.');
    await page.waitForTimeout(300000);
})();
