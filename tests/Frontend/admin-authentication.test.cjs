const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function scriptFrom(view, marker) {
    const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin', view), 'utf8');
    return [...source.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/gi)]
        .map(match => match[1]).find(script => script.includes(marker));
}

const loginScript = scriptFrom('login.blade.php', 'async function doLogin()');
const layoutScript = scriptFrom('layouts/admin.blade.php', 'window.adminAuthReady');
const identityMarkup = ['sidebar'].map(partial => fs.readFileSync(
    path.join(__dirname, `../../resources/views/admin/partials/${partial}.blade.php`), 'utf8',
)).join('\n');
const response = (status, data = { id: 0, name: 'Fixture Admin', role: 'admin' }) => ({
    status, ok: status >= 200 && status < 300, json: async () => data,
});

function browser(script, fetchImpl, adminToken = 'fixture-admin-session') {
    const elements = new Map();
    const element = id => {
        if (!elements.has(id)) {
            const classes = new Set();
            const listeners = new Map();
            elements.set(id, {
                hidden: ['admin-shell', 'authRetry'].includes(id), style: {}, value: '', textContent: '', disabled: false,
                classList: { add: name => classes.add(name), remove: name => classes.delete(name), contains: name => classes.has(name) },
                addEventListener: (event, callback) => listeners.set(event, callback),
                click: () => listeners.get('click')?.(), appendChild() {},
            });
        }
        return elements.get(id);
    };
    const storage = new Map([['token', 'fixture-customer-session'], ['user', '{"name":"Fixture Customer"}']]);
    if (adminToken) {
        storage.set('admin_token', adminToken);
        storage.set('admin_account', '{"name":"Cached Admin","role":"admin"}');
    }
    const requests = [];
    const selectorElements = selector => [...identityMarkup.matchAll(/class="([^"]+)"/g)]
        .filter(match => match[1].split(/\s+/).includes(selector.slice(1)))
        .map((match, index) => element(`${selector}-${index}`));
    const context = {
        document: { getElementById: element, querySelectorAll: selectorElements, querySelector: () => ({ content: '' }),
            addEventListener() {}, createElement: () => ({ style: {} }) },
        localStorage: { getItem: key => storage.get(key) ?? null, setItem: (key, value) => storage.set(key, value), removeItem: key => storage.delete(key) },
        location: { href: '/admin', reloads: 0, replace(value) { this.href = value; }, reload() { this.reloads++; } },
        fetch: async (endpoint, options) => { requests.push({ endpoint, options }); return fetchImpl(endpoint, options); },
    };
    context.window = context;
    vm.createContext(context);
    vm.runInContext(script, context);
    return { context, storage, element, requests };
}

function customerIsPreserved(storage) {
    assert.equal(storage.get('token'), 'fixture-customer-session');
    assert.equal(storage.get('user'), '{"name":"Fixture Customer"}');
}

test('No token: Dashboard stays hidden and redirects to Login', async () => {
    const page = browser(layoutScript, () => { throw new Error('Unexpected request'); }, null);
    assert.equal(await page.context.adminAuthReady, false);
    assert.equal(page.element('admin-shell').hidden, true);
    assert.equal(page.context.location.href, '/admin/login');
    assert.equal(page.requests.length, 0);
    customerIsPreserved(page.storage);
});

for (const status of [401, 403]) {
    test(`Verification ${status}: clear only Admin state and keep Dashboard hidden`, async () => {
        const page = browser(layoutScript, async () => response(status));
        assert.equal(await page.context.adminAuthReady, false);
        assert.equal(page.element('admin-shell').hidden, true);
        assert.equal(page.element('authOverlay').classList.contains('hide'), false);
        for (const key of ['admin_token', 'admin_account']) assert.equal(page.storage.has(key), false);
        assert.equal(page.context.location.href, '/admin/login');
        customerIsPreserved(page.storage);
    });
}

for (const [label, fetchImpl] of [
    ['network failure', async () => { throw new TypeError('Fixture network failure'); }],
    ['server failure', async () => response(500)],
    ['rate limit', async () => response(429)],
    ['malformed JSON', async () => ({ ok: true, status: 200, json: async () => { throw new SyntaxError('Fixture JSON failure'); } })],
    ['incorrect role response', async () => response(200, { role: 'user' })],
]) {
    test(`Verification ${label}: retain token, lock UI, offer retry and block business API`, async () => {
        const page = browser(layoutScript, fetchImpl);
        assert.equal(await page.context.adminAuthReady, false);
        assert.equal(page.storage.get('admin_token'), 'fixture-admin-session');
        assert.equal(page.element('admin-shell').hidden, true);
        assert.equal(page.element('authOverlay').classList.contains('hide'), false);
        assert.equal(page.element('authRetry').hidden, false);
        await assert.rejects(page.context.adminApi('/dashboard'), /chưa được xác minh/);
        assert.equal(page.requests.length, 1);
        page.element('authRetry').click();
        assert.equal(page.context.location.reloads, 1);
        customerIsPreserved(page.storage);
    });
}

test('Business requests wait until /me succeeds; then show Admin and use one account key', async () => {
    let finishVerification;
    const page = browser(layoutScript, endpoint => endpoint === '/api/admin/me'
        ? new Promise(resolve => { finishVerification = resolve; })
        : Promise.resolve(response(200, { fixture: true })));
    const businessRequest = page.context.adminApi('/dashboard');
    assert.equal(page.element('admin-shell').hidden, true);
    assert.equal(page.requests.length, 1);
    finishVerification(response(200));
    assert.equal(await page.context.adminAuthReady, true);
    await businessRequest;
    assert.equal(page.element('admin-shell').hidden, false);
    assert.equal(page.element('authOverlay').classList.contains('hide'), true);
    assert.equal(JSON.parse(page.storage.get('admin_account')).name, 'Fixture Admin');
    for (const [selector, expected] of [['.js-admin-name', 'Fixture Admin'], ['.js-admin-role', 'admin'], ['.js-avatar-initials', 'FA']]) {
        const rendered = page.context.document.querySelectorAll(selector);
        assert.ok(rendered.length > 0, `Missing identity elements for ${selector}`);
        assert.ok(rendered.every(element => element.textContent === expected), `Incorrect identity rendered for ${selector}`);
    }
    assert.deepEqual(page.requests.map(request => request.endpoint), ['/api/admin/me', '/api/admin/dashboard']);
    customerIsPreserved(page.storage);
});

test('Expired session during a business request locks Dashboard and redirects', async () => {
    const page = browser(layoutScript, async endpoint => response(endpoint === '/api/admin/me' ? 200 : 401));
    await page.context.adminAuthReady;
    await page.context.adminApi('/dashboard');
    assert.equal(page.element('admin-shell').hidden, true);
    assert.equal(page.storage.has('admin_token'), false);
    assert.equal(page.context.location.href, '/admin/login');
    customerIsPreserved(page.storage);
});

test('Logout calls Admin endpoint and removes only Admin state', async () => {
    const page = browser(layoutScript, async () => response(200));
    await page.context.adminAuthReady;
    await page.context.adminLogout();
    assert.equal(page.requests.at(-1).endpoint, '/api/admin/logout');
    assert.equal(page.requests.at(-1).options.method, 'POST');
    assert.equal(page.storage.has('admin_token'), false);
    customerIsPreserved(page.storage);
});

test('Empty Login fields do not send an API request', async () => {
    const page = browser(loginScript, () => { throw new Error('Unexpected request'); }, null);
    await page.context.doLogin();
    assert.equal(page.requests.length, 0);
    assert.equal(page.element('error-box').classList.contains('show'), true);
});

for (const status of [401, 422, 500]) {
    test(`Login ${status}: display error and do not create an Admin session`, async () => {
        const page = browser(loginScript, async () => response(status, { message: 'Fixture error' }), null);
        page.element('email').value = 'fixture@example.invalid';
        page.element('password').value = 'fixture-password';
        await page.context.doLogin();
        assert.equal(page.element('error-box').classList.contains('show'), true);
        assert.equal(page.storage.has('admin_token'), false);
        assert.equal(page.element('btn-login').disabled, false);
        customerIsPreserved(page.storage);
    });
}

test('Successful Login stores Admin account, submits correct JSON and redirects', async () => {
    const page = browser(loginScript, async () => response(200, {
        token: 'fixture-admin-session', admin: { id: 0, name: 'Fixture Admin', role: 'admin' },
    }), null);
    page.element('email').value = '  fixture@example.invalid  ';
    page.element('password').value = 'fixture-password';
    await page.context.doLogin();
    assert.equal(page.requests[0].endpoint, '/api/admin/login');
    assert.deepEqual(JSON.parse(page.requests[0].options.body), { email: 'fixture@example.invalid', password: 'fixture-password' });
    assert.equal(page.storage.get('admin_token'), 'fixture-admin-session');
    assert.equal(JSON.parse(page.storage.get('admin_account')).role, 'admin');
    assert.equal(page.context.location.href, '/admin');
    customerIsPreserved(page.storage);
});

test('Malformed successful Login response cannot store a session', async () => {
    const page = browser(loginScript, async () => response(200, { admin: { role: 'admin' } }), null);
    page.element('email').value = 'fixture@example.invalid';
    page.element('password').value = 'fixture-password';
    await page.context.doLogin();
    assert.equal(page.storage.has('admin_token'), false);
    assert.equal(page.element('error-box').classList.contains('show'), true);
});
