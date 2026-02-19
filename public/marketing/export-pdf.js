const puppeteer = require('puppeteer');
const path = require('path');

async function exportToPDF(htmlFile, outputFile, format) {
    const browser = await puppeteer.launch();
    const page = await browser.newPage();

    // Load the HTML file
    const filePath = path.join(__dirname, htmlFile);
    await page.goto(`file://${filePath}`, { waitUntil: 'networkidle0' });

    // Wait for fonts to load
    await page.evaluateHandle('document.fonts.ready');

    // Set the page size based on format
    const dimensions = {
        'A3': { width: '297mm', height: '420mm' },
        'A5': { width: '148mm', height: '210mm' }
    };

    const size = dimensions[format] || dimensions['A5'];

    // Generate PDF
    await page.pdf({
        path: outputFile,
        width: size.width,
        height: size.height,
        printBackground: true,
        margin: { top: 0, right: 0, bottom: 0, left: 0 }
    });

    await browser.close();
    console.log(`✓ Exported: ${outputFile}`);
}

// Get command line arguments
const args = process.argv.slice(2);

if (args.length === 0) {
    // Default: export both
    (async () => {
        await exportToPDF('flyer.html', 'StudySprint-Flyer-A5.pdf', 'A5');
        await exportToPDF('poster.html', 'StudySprint-Poster-A3.pdf', 'A3');
        console.log('\n✓ All exports complete!');
    })();
} else if (args[0] === 'flyer') {
    exportToPDF('flyer.html', 'StudySprint-Flyer-A5.pdf', 'A5');
} else if (args[0] === 'poster') {
    exportToPDF('poster.html', 'StudySprint-Poster-A3.pdf', 'A3');
}
