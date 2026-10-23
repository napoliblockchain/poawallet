import 'dotenv/config';
import { WebSocketServer } from 'ws';
import Redis from 'ioredis';
import { sessionNotifications, originAllowed, notificationPacket } from './session.js';

const PORT = parseInt(process.env.NOTIFY_WS_PORT || '14800', 10);
const REDIS_URL = process.env.REDIS_URL || null;
const CHANNEL = process.env.NOTIFY_CHANNEL || 'poawallet:notify';
const BACKEND_URL = process.env.NOTIFY_BACKEND_URL || 'http://wallet-web';
const ALLOWED_ORIGINS = (process.env.NOTIFY_ALLOWED_ORIGINS || '').split(',').map(s => s.trim()).filter(Boolean);

const sub = REDIS_URL ? new Redis(REDIS_URL) : null;

const wss = new WebSocketServer({ port: PORT, maxPayload: 8192 });

const C = {
    reset: '\x1b[0m',
    grey: '\x1b[90m',
    green: '\x1b[32m',
    yellow: '\x1b[33m',
    cyan: '\x1b[36m',
    red: '\x1b[31m',
};

function log(color, tag, msg) {
    const ts = new Date().toLocaleTimeString('it-IT', { hour12: false });
    console.log(`${C.grey}[${ts}]${C.reset} ${color}[${tag}]${C.reset} ${msg}`);
}

function safeSend(ws, payload) {
    try { ws.send(JSON.stringify(payload)); } catch { /* ignore */ }
}

wss.on('connection', (ws, req) => {
    const clientIp = req.socket.remoteAddress || 'unknown';

    if (!originAllowed(req.headers.origin, req.headers.host, ALLOWED_ORIGINS)) {
        log(C.red, 'REJECT', 'Origin not allowed');
        ws.close(1008, 'Origin not allowed');
        return;
    }

    log(C.green, 'CONN', `New connection from ${clientIp}`);
    ws.isAlive = true;
    ws.meta = { userId: null, ip: clientIp };
    ws.on('pong', () => { ws.isAlive = true; });

    ws.on('message', async (data) => {
        let msg; try { msg = JSON.parse(data.toString()); } catch { return; }
        if (!msg || typeof msg !== 'object' || Array.isArray(msg)) return;

        if (msg.action === 'subscribe') {
            try {
                const session = await sessionNotifications(BACKEND_URL, req.headers.cookie);
                // A claimed identity must match the identity resolved by Yii.
                const claimedId = msg.user_id ?? msg.userId;
                if (claimedId !== undefined && String(claimedId) !== String(session.userId)) {
                    throw new Error('Identity mismatch');
                }
                ws.meta.userId = String(session.userId);
                safeSend(ws, { type: 'subscribed_response' });
            } catch {
                ws.meta.userId = null;
                safeSend(ws, { type: 'error_response', message: 'Authentication required' });
                ws.close(1008, 'Authentication required');
            }
            return;
        }

        if (msg.action === 'getNotifications') {
            if (!ws.meta.userId) {
                safeSend(ws, { type: 'error_response', message: 'Missing subscription id' });
                return;
            }
            try {
                const data = await sessionNotifications(BACKEND_URL, req.headers.cookie);
                if (String(data.userId) !== ws.meta.userId) throw new Error('Session changed');
                safeSend(ws, notificationPacket(data));
            } catch (err) {
                log(C.red, 'ERR', 'Notification session validation failed');
                ws.meta.userId = null;
                safeSend(ws, { type: 'error_response', message: 'Unable to fetch notifications' });
                ws.close(1008, 'Session unavailable');
            }
            return;
        }

        if (msg.action === 'checkTrades') {
            safeSend(ws, {
                type: 'trades_response',
                success: false,
                response: {
                    message: 'Trades websocket flow not enabled in this server build',
                    transactions: [],
                },
            });
            return;
        }
    });

    ws.on('close', () => {
        if (ws.meta.userId) {
            log(C.grey, 'DISC', `User ${ws.meta.userId} disconnected from ${clientIp}`);
        }
        ws.meta.userId = null;
    });
    ws.on('error', (err) => {
        log(C.red, 'ERR', `WebSocket error from ${clientIp}: ${err.message}`);
    });
});

setInterval(() => {
    wss.clients.forEach((ws) => {
        if (ws.isAlive === false) return ws.terminate();
        ws.isAlive = false; ws.ping();
    });
}, 30000);

if (sub) {
    sub.subscribe(CHANNEL, () => {
        log(C.green, 'REDIS', `Subscribed to channel: ${CHANNEL}`);
    });

    sub.on('message', (_channel, message) => {
        let payload; try { payload = JSON.parse(message); } catch { return; }
        const targetUser = payload.user_id || payload.userId;
        if (!targetUser) return;

        let sent = 0;
        wss.clients.forEach((ws) => {
            if (ws.meta?.userId === String(targetUser)) {
                safeSend(ws, { type: 'newmessage_call' });
                sent++;
            }
        });

        if (sent > 0) {
            const title = payload.notification?.title || 'notification';
            log(C.cyan, 'PUSH', `"${title}" -> user ${targetUser} (${sent} client${sent > 1 ? 's' : ''})`);
        } else {
            log(C.yellow, 'SKIP', `"${payload?.notification?.title || 'notification'}" -> user ${targetUser} not connected`);
        }
    });
} else {
    log(C.yellow, 'INFO', 'Redis not configured, skipping channel subscription.');
}

log(C.green, 'START', `WebSocket server listening on port ${PORT}`);
log(C.grey, 'INFO', `Redis: ${REDIS_URL || 'disabled'} | Channel: ${CHANNEL}`);
