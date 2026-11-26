// Yii remains the authority for session validity and notification ownership.
export async function sessionNotifications(backendUrl, cookie, fetchImpl = fetch) {
    if (typeof cookie !== 'string' || !cookie) throw new Error('Authentication required');
    const response = await fetchImpl(`${backendUrl.replace(/\/$/, '')}/index.php?r=backend/notify`, {
        headers: { Cookie: cookie, Accept: 'application/json' },
        redirect: 'error',
        signal: AbortSignal.timeout(5000),
    });
    if (!response.ok) throw new Error('Authentication or backend failure');
    const data = await response.json();
    if (!Number.isSafeInteger(data.userId) || data.userId <= 0
        || !Number.isSafeInteger(data.countedUnread) || data.countedUnread < 0) {
        throw new Error('Invalid session response');
    }
    return data;
}

export function originAllowed(origin, host, allowedOrigins) {
    if (typeof origin !== 'string') return false;
    try {
        const url = new URL(origin);
        if (!['http:', 'https:'].includes(url.protocol)) return false;
        return allowedOrigins.length ? allowedOrigins.includes(origin) : url.host === host;
    } catch {
        return false;
    }
}

export function notificationPacket(data) {
    return {
        type: 'endnotifications_response',
        html: {
            countedUnread: data.countedUnread,
            playAlarm: data.playAlarm,
            playSound: data.playSound,
            title: data.htmlTitle,
            content: data.htmlContent,
        },
    };
}
