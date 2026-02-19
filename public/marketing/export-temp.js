const puppeteer = require('puppeteer');
const path = require('path');

(async () => {
    const browser = await puppeteer.launch();
    const page = await browser.newPage();
    const filePath = path.resolve(__dirname, 'poster.html').replace(/\\/g, '/');
    await page.goto('file:///' + filePath, { waitUntil: 'networkidle0' });
    await page.evaluateHandle('document.fonts.ready');
    await page.pdf({
        path: 'StudySprint-Poster-A3-v2.pdf',
        width: '297mm',
        height: '420mm',
        printBackground: true,
        margin: { top: 0, right: 0, bottom: 0, left: 0 }
    });
    await browser.close();
    console.log('Exported: StudySprint-Poster-A3-v2.pdf');
})();
