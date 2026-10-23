<?php

namespace app\components;

use app\models\Notifications;
use app\models\NotificationsReaders;
use app\models\PushSubscriptions;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Yii;
use yii\helpers\Json;

/**
 * Notifiche utente su tre canali:
 * - database (mp_notifications + mp_notifications_readers): elenco e badge in app
 * - web push (VAPID): notifica sul dispositivo anche ad app chiusa
 * - realtime (Redis -> ws-notify): aggiorna subito le pagine aperte
 *
 * Ogni canale e indipendente: un errore su push o realtime non blocca gli altri.
 */
class NotificationService
{
    private const LOG_CATEGORY = 'notification';

    /**
     * Salva la notifica e la invia su push e realtime.
     *
     * @param array $attributes id_user, type, status, description, url, timestamp, price
     * @param string $app tipo di iscrizione push (mp_subscriptions.type)
     * @return array payload push (vuoto se la push non e stata inviata)
     */
    public static function notify(array $attributes, string $app = 'wallet'): array
    {
        $userId = (int) $attributes['id_user'];
        unset($attributes['id_user']);

        $notification = new Notifications();
        $notification->attributes = $attributes;
        if (!$notification->insert()) {
            Yii::warning('Notification not saved: ' . Json::encode($notification->getErrors()), self::LOG_CATEGORY);
            return [];
        }

        $reader = new NotificationsReaders();
        $reader->id_user = $userId;
        $reader->id_notification = $notification->id;
        $reader->alreadyread = NotificationsReaders::STATUS_UNREAD;
        if (!$reader->insert()) {
            Yii::warning("Notification reader not saved for user {$userId}: " . Json::encode($reader->getErrors()), self::LOG_CATEGORY);
            return [];
        }

        $payload = self::buildPushPayload($notification, $app);

        $sent = 0;
        try {
            $sent = self::sendPush($userId, $payload, $app);
        } catch (\Throwable $e) {
            Yii::warning("Push failed for user {$userId}: {$e->getMessage()}", self::LOG_CATEGORY);
        }

        self::publishRealtime($userId, $notification);

        return $sent > 0 ? $payload : [];
    }

    /**
     * Invia il payload a tutte le iscrizioni push dell'utente; rimuove quelle scadute.
     *
     * @return int numero di invii riusciti
     */
    public static function sendPush(int $userId, array $payload, string $app = 'wallet'): int
    {
        $vapid = Settings::vapid();
        if ($vapid === null) {
            return 0;
        }

        $subscriptions = PushSubscriptions::find()
            ->where(['id_user' => $userId, 'type' => $app])
            ->all();
        if ($subscriptions === []) {
            return 0;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => $vapid->subject,
                'publicKey' => $vapid->public_key,
                'privateKey' => $vapid->private_key,
            ],
        ], [
            'TTL' => 64800,
        ]);

        $json = Json::encode($payload);
        foreach ($subscriptions as $subscription) {
            $webPush->queueNotification(Subscription::create([
                'endpoint' => $subscription->endpoint,
                'keys' => [
                    'p256dh' => $subscription->p256dh,
                    'auth' => $subscription->auth,
                ],
            ]), $json);
        }

        $sent = 0;
        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                $sent++;
                continue;
            }
            if ($report->isSubscriptionExpired()) {
                PushSubscriptions::deleteAll(['id_user' => $userId, 'endpoint' => $report->getEndpoint()]);
                continue;
            }
            Yii::warning("Push not delivered for user {$userId}: {$report->getReason()}", self::LOG_CATEGORY);
        }

        return $sent;
    }

    /**
     * Avvisa ws-notify (canale Redis) che l'utente ha una nuova notifica.
     * Senza componente redis configurato non fa nulla.
     */
    public static function publishRealtime(int $userId, Notifications $notification): void
    {
        if (!Yii::$app->has('redis')) {
            return;
        }

        try {
            Yii::$app->redis->publish(Yii::$app->params['notify.channel'], Json::encode([
                'user_id' => $userId,
                'notification' => [
                    'id' => $notification->id,
                    'title' => self::title($notification->type),
                    'type' => $notification->type,
                    'created_at' => (int) $notification->timestamp,
                ],
            ]));
        } catch (\Throwable $e) {
            Yii::warning("Realtime publish failed for user {$userId}: {$e->getMessage()}", self::LOG_CATEGORY);
        }
    }

    /**
     * Payload letto da web/service-worker.js (evento push).
     */
    private static function buildPushPayload(Notifications $notification, string $app): array
    {
        return [
            'title' => '[' . self::title($notification->type) . '] - ' . Yii::t('app', 'New message'),
            'body' => $notification->description,
            'icon' => 'src/images/icons/app-icon-96x96.png',
            'badge' => 'src/images/icons/app-icon-96x96.png',
            'image' => 'css/images/logo.png',
            'sound' => 'src/sounds/notify.mp3',
            'vibrate' => [200, 100, 200, 100, 200],
            'tag' => 'notify',
            'renotify' => true,
            // il service worker legge openUrl al primo livello
            'openUrl' => $notification->url,
            'data' => [
                'priority' => 'high',
                'openUrl' => $notification->url,
                'app' => $app,
            ],
            'actions' => [
                ['action' => 'openUrl', 'title' => Yii::t('app', 'Yes'), 'icon' => 'css/images/chk_on.png'],
                ['action' => 'close', 'title' => Yii::t('app', 'No'), 'icon' => 'css/images/chk_off.png'],
            ],
        ];
    }

    private static function title(?string $type): string
    {
        return in_array($type, ['token', 'contact'], true) ? 'Poa Wallet' : 'Wallet';
    }
}
