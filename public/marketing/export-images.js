const puppeteer = require('puppeteer');
const path = require('path');

async function exportToImage(htmlFile, outputFile, format, width, height) {
    const browser = await puppeteer.launch();
    const page = await browser.newPage();

    // Set viewport to match the poster/flyer dimensions (at 2x for high DPI)
    const scale = 2; // 2x resolution for crisp images
    await page.setViewport({
        width: Math.round(width * scale),
        height: Math.round(height * scale),
        deviceScaleFactor: scale
    });

    // Load the HTML file
    const filePath = path.resolve(__dirname, htmlFile).replace(/\\/g, '/');
    await page.goto('file:///' + filePath, { waitUntil: 'networkidle0' });

    // Wait for fonts to load
    await page.evaluateHandle('document.fonts.ready');

    // Take screenshot
    await page.screenshot({
        path: outputFile,
        type: format === 'jpg' ? 'jpeg' : 'png',
        quality: format === 'jpg' ? 95 : undefined,
        fullPage: false,
        clip: {
            x: 0,
            y: 0,
            width: Math.round(width * scale),
            height: Math.round(height * scale)
        }
    });

    await browser.close();
    console.log(`✓ Exported: ${outputFile}`);
}

// Dimensions in pixels (at 96 DPI, then scaled 2x)
// A3: 297mm x 420mm = 1123px x 1587px at 96 DPI
// A5: 148mm x 210mm = 559px x 794px at 96 DPI

const args = process.argv.slice(2);
const format = args[0] || 'png'; // png or jpg

(async () => {
    console.log(`\nExporting to ${format.toUpperCase()}...\n`);

    // Export poster (A3)
    await exportToImage(
        'poster.html',
        `StudySprint-Poster-A3.${format}`,
        format,
        1123, // A3 width in px
        1587  // A3 height in px
    );

    // Export flyer (A5)
    await exportToImage(
        'flyer.html',
        `StudySprint-Flyer-A5.${format}`,
        format,
        559,  // A5 width in px
        794   // A5 height in px
    );

    console.log(`\n✓ All images exported!\n`);
})();
