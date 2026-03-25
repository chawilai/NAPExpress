const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();
    await page.goto('https://dmis.nhso.go.th/NAPPLUS/login.jsp', { waitUntil: 'networkidle' });

    const title = await page.title();
    console.log('Page title:', title);

    // Check if there are frames/iframes
    const frames = page.frames();
    console.log('Frames count:', frames.length);
    for (const f of frames) {
        console.log('  Frame:', f.url());
    }

    // Dump all input fields
    const inputs = await page.evaluate(() => {
        return Array.from(document.querySelectorAll('input, select, button, textarea')).map(el => ({
            tag: el.tagName,
            id: el.id,
            name: el.name,
            type: el.type,
            className: el.className.substring(0, 80),
            placeholder: el.placeholder || '',
            value: el.type === 'password' ? '***' : (el.value || '').substring(0, 30),
        }));
    });
    console.log('\nAll form elements:');
    console.log(JSON.stringify(inputs, null, 2));

    // Get page HTML snippet around form
    const formHtml = await page.evaluate(() => {
        const form = document.querySelector('form');
        return form ? form.outerHTML.substring(0, 2000) : 'NO FORM FOUND';
    });
    console.log('\nForm HTML (first 2000 chars):');
    console.log(formHtml);

    await browser.close();
})();
