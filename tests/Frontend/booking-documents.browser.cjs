// Local file previews only. No HTTP server, database, email transport or external requests.
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { spawn, execFileSync } = require('node:child_process');
const { pathToFileURL } = require('node:url');
const root = path.resolve(__dirname, '../..');
const runtime = path.join(root, 'storage/app/private/aurel-document-preview');
const profile = path.join(runtime, 'chrome-profile-' + Date.now());
const chrome = spawn('C:/Program Files/Google/Chrome/Application/chrome.exe', [
    '--headless=new', '--remote-debugging-port=0', '--user-data-dir=' + profile,
    '--no-first-run', '--no-default-browser-check', '--disable-extensions',
    '--disable-background-networking', '--disable-sync', 'about:blank',
], { windowsHide: true, stdio: 'ignore' });
let ws, session, sequence = 0;
const pending = new Map();
const checks = [];
const errors = [];
const blocked = new Set();
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
function send(method, params = {}, sid = session) {
    return new Promise((resolve, reject) => {
        const id = ++sequence;
        const timer = setTimeout(() => { pending.delete(id); reject(new Error('CDP timeout: ' + method)); }, 15000);
        pending.set(id, { resolve, reject, timer });
        ws.send(JSON.stringify({ id, method, params, ...(sid ? { sessionId: sid } : {}) }));
    });
}
async function evaluate(expression) {
    const response = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
    if (response.exceptionDetails) throw new Error(response.exceptionDetails.text);
    return response.result.value;
}
async function navigate(name, width) {
    await send('Emulation.setDeviceMetricsOverride', { width, height: 1000, deviceScaleFactor: 1, mobile: false });
    const fileUrl = pathToFileURL(path.join(runtime, name + '.html')).href;
    await send('Page.navigate', { url: fileUrl });
    for (let attempt = 0; attempt < 80; attempt++) {
        if (await evaluate(`location.href === ${JSON.stringify(fileUrl)} && document.readyState === 'complete'`)) return;
        await delay(50);
    }
    throw new Error('Preview navigation timed out');
}
async function inspect(label) {
    const layout = await evaluate(`(() => ({
        width: innerWidth, contentWidth: document.documentElement.scrollWidth,
        imageLoaded: [...document.images].every(image => image.complete && image.naturalWidth > 0),
        heading: document.querySelector('h1')?.textContent,
        fullHeight: document.documentElement.scrollHeight,
        inlineCards: [...document.querySelectorAll('.email-card')].every(card => card.getAttribute('style').includes('padding:')),
        tinyText: [...document.querySelectorAll('.email-label,.email-value')].some(cell => parseFloat(getComputedStyle(cell).fontSize) < 13),
    }))()`);
    assert.ok(layout.contentWidth <= layout.width, label + ': no horizontal overflow');
    assert.equal(layout.imageLoaded, true, label + ': logo loaded');
    assert.equal(layout.inlineCards, true, label + ': inline card padding');
    assert.equal(layout.tinyText, false, label + ': readable field text');
    assert.equal(layout.heading, 'Thanh toán thành công');
    checks.push({ label, ...layout });
}
async function screenshot(name) {
    const result = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true });
    fs.writeFileSync(path.join(runtime, name + '.png'), Buffer.from(result.data, 'base64'));
}
(async () => {
    const portFile = path.join(profile, 'DevToolsActivePort');
    for (let attempt = 0; attempt < 120 && !fs.existsSync(portFile); attempt++) await delay(100);
    const active = fs.readFileSync(portFile, 'utf8').trim().split(/\r?\n/);
    ws = new WebSocket('ws://127.0.0.1:' + active[0] + active[1]);
    await new Promise((resolve, reject) => { ws.onopen = resolve; ws.onerror = reject; });
    ws.onmessage = event => {
        const message = JSON.parse(event.data);
        if (message.id) {
            const request = pending.get(message.id);
            if (!request) return;
            clearTimeout(request.timer); pending.delete(message.id);
            message.error ? request.reject(new Error(message.error.message)) : request.resolve(message.result);
        } else if (message.method === 'Runtime.exceptionThrown') errors.push(message.params.exceptionDetails.text);
        else if (message.method === 'Fetch.requestPaused') {
            const url = message.params.request.url;
            const allowed = url.startsWith(pathToFileURL(runtime + path.sep).href)
                || url === pathToFileURL(path.join(root, 'public/image/logothuonghieu.png')).href
                || url === 'about:blank' || url.startsWith('data:');
            if (!allowed) blocked.add(url);
            send(allowed ? 'Fetch.continueRequest' : 'Fetch.failRequest', allowed
                ? { requestId: message.params.requestId }
                : { requestId: message.params.requestId, errorReason: 'BlockedByClient' }, message.sessionId).catch(() => {});
        }
    };
    const target = await send('Target.createTarget', { url: 'about:blank' }, null);
    session = (await send('Target.attachToTarget', { targetId: target.targetId, flatten: true }, null)).sessionId;
    await send('Page.enable'); await send('Runtime.enable');
    await send('Fetch.enable', { patterns: [{ urlPattern: '*' }] });
    for (const width of [320, 375, 600, 768, 1024]) {
        await navigate('email', width); await inspect('Email ' + width + 'px');
        if (width === 375 || width === 1024) await screenshot('email-' + width);
    }
    await navigate('email-with-url', 375);
    assert.equal(await evaluate(`document.querySelector('.email-button')?.textContent === 'Xem chi tiết đặt phòng'`), true);
    await inspect('Valid CTA on mobile');
    await screenshot('email-with-url-mobile');
    await navigate('email-long', 320); await inspect('Long Vietnamese data at 320px');
    await screenshot('email-long-mobile');
    await navigate('email', 320);
    await evaluate(`document.querySelectorAll('style').forEach(style => style.remove())`);
    await inspect('Inline-only fallback at 320px');
    assert.deepEqual(errors, []);
    assert.deepEqual([...blocked], []);
    const pdfChecks = [];
    for (const name of ['invoice', 'invoice-long']) {
        const pdfPath = path.join(runtime, name + '.pdf');
        const pdf = fs.readFileSync(pdfPath).toString('latin1');
        const textPath = path.join(runtime, name + '-text.txt');
        execFileSync('pdftotext', ['-enc', 'UTF-8', '-layout', pdfPath, textPath], { windowsHide: true });
        const text = fs.readFileSync(textPath, 'utf8');
        const pages = text.split('\f').filter(page => page.trim());
        assert.ok(pdf.includes('/FontFile2'), 'Vietnamese fonts embedded in ' + name);
        assert.ok(pdf.includes('/Subtype /Image'), 'Brand logo embedded in ' + name);
        const mediaBox = /\/MediaBox\s*\[\s*0(?:\.0+)?\s+0(?:\.0+)?\s+([\d.]+)\s+([\d.]+)/.exec(pdf);
        assert.ok(mediaBox && Math.abs(Number(mediaBox[1]) - 595.28) < 1 && Math.abs(Number(mediaBox[2]) - 841.89) < 1, 'A4 dimensions');
        assert.ok(!text.includes('\uFFFD'), 'No missing Unicode characters in ' + name);
        for (const page of pages) {
            assert.ok(page.includes('THỬ NGHIỆM — KHÔNG CÓ GIÁ TRỊ THANH TOÁN'), 'Staging label on every page');
            assert.ok(page.includes('Chứng từ được tạo tự động'), 'Footer on every page');
        }
        if (name === 'invoice') {
            assert.equal(pages.length, 1);
            for (const expected of ['HÓA ĐƠN THANH TOÁN', 'Đã thanh toán', '200.000 VND', 'Sofitel Legend Metropole Hà Nội']) assert.ok(text.includes(expected), expected);
        } else {
            assert.ok(pages.length > 1);
            assert.ok(text.includes('Nguyễn Thị Ánh'), 'Vietnamese customer name survives PDF extraction');
            assert.ok(pages.at(-1).includes('14.100.000 VND'), 'Adjusted total appears on last page');
            assert.equal((text.match(/Dịch vụ tổng hợp \d+/g) || []).length, 70, 'All 70 items survive pagination');
            assert.equal((text.match(/14\.100\.000 VND/g) || []).length, 1, 'Grand total rendered exactly once');
        }
        pdfChecks.push({ name, pages: pages.length, embedded_fonts: true, embedded_logo: true, a4: true, vietnamese: true, footer_and_warning_on_every_page: true });
    }
    const report = { synthetic_only: true, external_requests: false, checks_passed: checks.length, checks, pdfChecks, errors };
    fs.writeFileSync(path.join(runtime, 'browser-results.json'), JSON.stringify(report, null, 2));
    console.log(JSON.stringify({ checks_passed: checks.length, external_requests: false, errors }));
})().catch(error => { console.error(error); process.exitCode = 1; }).finally(async () => {
    if (ws?.readyState === WebSocket.OPEN) {
        await send('Browser.close', {}, null).catch(() => {});
        ws.close();
    }
    chrome.kill();
});
