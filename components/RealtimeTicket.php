<?php

namespace app\components;

use Yii;

/**
 * Ticket per il flusso in tempo reale /events del servizio wallet-realtime (SIP-029 passo 2).
 * Formato "v1.<utente>.<scadenza>.<firma>", firma HMAC-SHA256 base64url con la chiave condivisa
 * con il servizio Go (file REALTIME_KEY_FILE, 64 caratteri esadecimali). Cookie HttpOnly limitato a /events.
 */
class RealtimeTicket
{
    public const COOKIE = 'wallet_rt';
    /** Validità del ticket: il servizio chiude il flusso alla scadenza e il browser ne chiede uno nuovo. */
    public const LIFETIME = 600;

    /** Chiave condivisa, null se il tempo reale non è configurato. */
    public static function key(): ?string
    {
        $file = trim((string) getenv('REALTIME_KEY_FILE'));
        if ($file === '' || !is_readable($file)) {
            return null;
        }
        $key = trim((string) file_get_contents($file));
        return preg_match('/^[0-9a-f]{64}$/D', $key) === 1 ? $key : null;
    }

    public static function sign(string $key, int $userId, int $expires): string
    {
        $payload = 'v1.' . $userId . '.' . $expires;
        return $payload . '.' . rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, $key, true)), '+/', '-_'), '=');
    }

    /** Header Set-Cookie del ticket: valore fuori dalla validazione dei cookie Yii, letto dal servizio Go. */
    public static function cookie(string $ticket, int $maxAge): string
    {
        return self::COOKIE . '=' . $ticket . '; Max-Age=' . $maxAge . '; Path=/events; Secure; HttpOnly; SameSite=Strict';
    }

    /** Emette il ticket per l'utente collegato; false se il tempo reale non è configurato. */
    public static function issue(int $userId): bool
    {
        $key = self::key();
        if ($key === null || $userId <= 0) {
            return false;
        }
        Yii::$app->response->headers->add('Set-Cookie', self::cookie(self::sign($key, $userId, time() + self::LIFETIME), self::LIFETIME));
        return true;
    }

    /** Al logout: il browser non conserva un ticket valido per l'utente uscito. */
    public static function clear(): void
    {
        Yii::$app->response->headers->add('Set-Cookie', self::cookie('', 0));
    }
}
