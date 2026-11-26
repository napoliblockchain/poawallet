import test from 'node:test';
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { spawn } from 'node:child_process';
import { once } from 'node:events';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { WebSocket } from 'ws';
import { sessionNotifications, originAllowed } from '../src/session.js';

const origin = 'https://wallet.test';
const fixture = { userId: 1, countedUnread: 2, htmlTitle: 'Unread', htmlContent: '<li>Test</li>' };

test('session validation rejects anonymous, redirects and malformed backend responses', async () => {
    await assert.rejects(sessionNotifications('http://backend', ''), /Authentication/);
    await assert.rejects(sessionNotifications('http://backend', 'PHPSESSID=test', async () => ({ ok: false })), /failure/);
    await assert.rejects(sessionNotifications('http://backend', 'PHPSESSID=test', async () => ({ ok: true, json: async () => ({ ...fixture, userId: '1' }) })), /Invalid/);
    const result = await sessionNotifications('http://backend', 'PHPSESSID=test', async (url, options) => {
        assert.equal(options.redirect, 'error');
        assert.equal(options.headers.Cookie, 'PHPSESSID=test');
        assert.ok(!url.includes('user_id'));
        return { ok: true, json: async () => fixture };
    });
    assert.equal(result, fixture);
});

test('origin policy rejects missing/foreign origins and accepts explicit or same origin', () => {
    assert.equal(originAllowed(undefined, 'wallet.test', []), false);
    assert.equal(originAllowed('https://evil.test', 'wallet.test', []), false);
    assert.equal(originAllowed(origin, 'wallet.test', []), true);
    assert.equal(originAllowed(origin, 'internal:14800', [origin]), true);
});

test('websocket session auth, forged ids, expiry and read-only receive', async t => {
    let expired = false;
    let mutations = 0;
    const backend = createServer((req, res) => {
        if (req.method !== 'GET') mutations++;
        if (expired || !['PHPSESSID=one', 'PHPSESSID=two'].includes(req.headers.cookie)) {
            res.writeHead(403); res.end(); return;
        }
        assert.equal(req.url, '/index.php?r=backend/notify');
        res.setHeader('Content-Type', 'application/json');
        res.end(JSON.stringify({ ...fixture, userId: req.headers.cookie === 'PHPSESSID=one' ? 1 : 2 }));
    });
    backend.listen(0, '127.0.0.1');
    await once(backend, 'listening');
    t.after(() => backend.close());
    const probe = createServer();
    probe.listen(0, '127.0.0.1'); await once(probe, 'listening');
    const port = probe.address().port;
    await new Promise(r => probe.close(r));
    const daemon = spawn(process.execPath, ['src/index.js'], {
        cwd: new URL('..', import.meta.url),
        env: { ...process.env, NOTIFY_WS_PORT: String(port), NOTIFY_BACKEND_URL: `http://127.0.0.1:${backend.address().port}`, NOTIFY_ALLOWED_ORIGINS: origin, REDIS_URL: '' },
        stdio: ['ignore', 'pipe', 'pipe'],
    });
    t.after(() => daemon.kill());
    await new Promise((resolve, reject) => {
        const timer = setTimeout(() => reject(new Error('Daemon startup timeout')), 5000);
        daemon.stdout.on('data', data => { if (data.toString().includes('listening')) { clearTimeout(timer); resolve(); } });
        daemon.once('exit', code => { clearTimeout(timer); reject(new Error(`Daemon exited ${code}`)); });
    });
    async function connect(cookie, suppliedOrigin = origin) {
        const ws = new WebSocket(`ws://127.0.0.1:${port}`, { headers: { Origin: suppliedOrigin, ...(cookie ? { Cookie: cookie } : {}) } });
        t.after(() => ws.terminate());
        await once(ws, 'open');
        return ws;
    }
    async function request(ws, data) {
        const reply = once(ws, 'message');
        ws.send(JSON.stringify(data));
        return JSON.parse((await reply)[0].toString());
    }
    const anonymous = await connect();
    assert.equal((await request(anonymous, { action: 'subscribe', user_id: 1 })).type, 'error_response');
    const forged = await connect('PHPSESSID=one');
    assert.equal((await request(forged, { action: 'subscribe', user_id: 2 })).type, 'error_response');
    const first = await connect('PHPSESSID=one');
    const second = await connect('PHPSESSID=two');
    assert.equal((await request(first, { action: 'subscribe' })).type, 'subscribed_response');
    assert.equal((await request(second, { action: 'getNotifications' })).type, 'error_response');
    assert.equal((await request(second, { action: 'subscribe', user_id: 2 })).type, 'subscribed_response');
    for (let i = 0; i < 2; i++) {
        const packet = await request(first, { action: 'getNotifications', user_id: 2 });
        assert.equal(packet.type, 'endnotifications_response');
        assert.equal(packet.html.countedUnread, 2);
    }
    assert.equal(mutations, 0);
    expired = true;
    const closed = once(first, 'close');
    first.send(JSON.stringify({ action: 'getNotifications' }));
    assert.equal((await closed)[0], 1008);
    const alien = await connect('PHPSESSID=one', 'https://evil.test');
    assert.equal((await once(alien, 'close'))[0], 1008);
});

test('frontend receive keeps unread badge and never acknowledges read automatically', () => {
    const html = new Map(); let pushes = 0;
    const $ = selector => {
        if (typeof selector === 'function') { selector(); return; }
        return {
            html(value) { html.set(selector, value); return this; }, append() { return this; },
            fadeIn() { return this; }, show() {}, hide() {}, addClass() {}, removeClass() {},
        };
    };
    class Socket { constructor() { this.readyState = 0; } }
    Socket.OPEN = 1; Socket.CONNECTING = 0;
    const context = {
        $, window: { location: { href: 'https://wallet.test/index.php' } },
        document: { getElementById: () => null }, yiiGlobalOptions: { WebSocketServerAddress: '/ws-notify' },
        WebSocket: Socket, URL, console: { log() {} }, navigator: {},
        displayPushNotification: () => pushes++,
        fetch: () => { throw new Error('Receive attempted HTTP write'); },
    };
    vm.runInNewContext(readFileSync(new URL('../../web/js/ws-websocket.js', import.meta.url), 'utf8'), context);
    const packet = { type: 'notifications_response', model_id: 7, pushMessage: { title: 'Test' }, html: { countedUnread: 2 } };
    context.notifyHandle.showNotification(packet);
    context.notifyHandle.showNotification(packet);
    assert.equal(html.get('#quantity_notify'), 2);
    assert.equal(pushes, 1);
    assert.equal(context.websocket.setNotified, undefined);
});
