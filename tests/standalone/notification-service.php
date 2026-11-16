<?php
// Isolated test for NotificationService: SQLite in memory, fake Redis, local push endpoint.
// Never loads production configuration or secrets.
// Run: php tests/standalone/notification-service.php
define('YII_DEBUG', false);
define('YII_ENABLE_ERROR_HANDLER', false);
error_reporting(E_ALL & ~E_DEPRECATED);
$root = getenv('NAPAY_TEST_APP_ROOT') ?: dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
require $root . '/vendor/yiisoft/yii2/Yii.php';

use app\components\NotificationService;

class FakeRedis extends yii\base\Component
{
    public $published = [];
    public $fail = false;

    public function publish($channel, $message)
    {
        if ($this->fail) {
            throw new RuntimeException('redis down');
        }
        $this->published[] = [$channel, $message];
        return 1;
    }
}

$app = new yii\console\Application([
    'id' => 'notification-service-test', 'basePath' => $root, 'runtimePath' => '/tmp/napay-ns-runtime',
    'components' => [
        'db' => ['class' => yii\db\Connection::class, 'dsn' => 'sqlite::memory:'],
        'redis' => ['class' => FakeRedis::class],
    ],
    'params' => [
        'vapid' => ['public_key' => null, 'private_key' => null, 'subject' => 'mailto:test@example.com'],
        'notify.channel' => 'poawallet:notify',
    ],
]);
$db = $app->db;
$db->createCommand('CREATE TABLE mp_notifications (id INTEGER PRIMARY KEY, type TEXT, status TEXT, description TEXT, url TEXT, timestamp INTEGER, price REAL)')->execute();
$db->createCommand('CREATE TABLE mp_notifications_readers (id INTEGER PRIMARY KEY, id_user INTEGER, id_notification INTEGER, alreadyread INTEGER)')->execute();
$db->createCommand('CREATE TABLE mp_users (id INTEGER PRIMARY KEY)')->execute();
$db->createCommand()->batchInsert('mp_users', ['id'], [[7], [8]])->execute();
$db->createCommand('CREATE TABLE mp_subscriptions (id INTEGER PRIMARY KEY, id_user INTEGER, type TEXT, browser TEXT, endpoint TEXT, auth TEXT, p256dh TEXT)')->execute();

$checks = 0;
function check($condition, $label) {
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
}
function b64url($bin) { return rtrim(strtr(base64_encode($bin), '+/', '-_'), '='); }
function notification($userId) {
    return ['id_user' => $userId, 'type' => 'token', 'status' => 'complete',
        'description' => 'You received 5 TTS', 'url' => '/index.php?r=transactions/view&id=x', 'timestamp' => 1700000000, 'price' => 5];
}

// 1. senza VAPID: notifica salvata, nessuna push, realtime pubblicato
$result = NotificationService::notify(notification(7));
check($result === [], 'no VAPID -> empty payload');
check((int) $db->createCommand('SELECT COUNT(*) FROM mp_notifications')->queryScalar() === 1, 'notification saved');
$reader = $db->createCommand('SELECT * FROM mp_notifications_readers')->queryOne();
check((int) $reader['id_user'] === 7 && (int) $reader['alreadyread'] === 0, 'reader saved as unread for user');
check(count($app->redis->published) === 1, 'realtime published once');
[$channel, $message] = $app->redis->published[0];
$msg = json_decode($message, true);
check($channel === 'poawallet:notify', 'realtime channel');
check($msg['user_id'] === 7 && $msg['notification']['title'] === 'Napay Wallet', 'realtime payload has user and title');
check(!isset($msg['notification']['description']), 'realtime payload carries no transaction details');

// 1b. utente inesistente: nessun salvataggio parziale, nessuna eccezione
$before = (int) $db->createCommand('SELECT COUNT(*) FROM mp_notifications_readers')->queryScalar();
check(NotificationService::notify(notification(999)) === [], 'unknown user -> empty payload');
check((int) $db->createCommand('SELECT COUNT(*) FROM mp_notifications_readers')->queryScalar() === $before, 'unknown user -> no reader row');
$db->createCommand('DELETE FROM mp_notifications WHERE id NOT IN (SELECT id_notification FROM mp_notifications_readers)')->execute();
$app->redis->published = [];

// 2. Redis in errore: la notifica viene comunque salvata, nessuna eccezione
$app->redis->fail = true;
NotificationService::notify(notification(7));
check((int) $db->createCommand('SELECT COUNT(*) FROM mp_notifications')->queryScalar() === 2, 'saved even if redis fails');
$app->redis->fail = false;

// 3. senza componente redis: realtime saltato senza errori
$app->set('redis', null);
$app->clear('redis');
NotificationService::notify(notification(7));
check((int) $db->createCommand('SELECT COUNT(*) FROM mp_notifications')->queryScalar() === 3, 'saved without redis component');

// 4. con VAPID: endpoint locale che risponde 410 (iscrizione scaduta) -> iscrizione rimossa
$vapid = Minishlink\WebPush\VAPID::createVapidKeys();
$app->params['vapid'] = ['public_key' => $vapid['publicKey'], 'private_key' => $vapid['privateKey'], 'subject' => 'mailto:test@example.com'];
$router = sys_get_temp_dir() . '/ns-push-router.php';
file_put_contents($router, '<?php http_response_code(strpos($_SERVER["REQUEST_URI"], "/gone") === 0 ? 410 : 201);');
$port = 18000 + random_int(0, 999);
$server = proc_open([PHP_BINARY, '-S', "127.0.0.1:{$port}", $router], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
usleep(400000);
$ec = openssl_pkey_get_details(openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]))['ec'];
$keys = ['p256dh' => b64url("\x04" . $ec['x'] . $ec['y']), 'auth' => b64url(random_bytes(16))];
$db->createCommand()->batchInsert('mp_subscriptions', ['id_user', 'type', 'browser', 'endpoint', 'auth', 'p256dh'], [
    [7, 'wallet', 'test', "http://127.0.0.1:{$port}/ok", $keys['auth'], $keys['p256dh']],
    [7, 'wallet', 'test', "http://127.0.0.1:{$port}/gone", $keys['auth'], $keys['p256dh']],
    [8, 'wallet', 'test', "http://127.0.0.1:{$port}/ok", $keys['auth'], $keys['p256dh']],
])->execute();
$payload = NotificationService::notify(notification(7));
proc_terminate($server);
check(($payload['openUrl'] ?? null) === '/index.php?r=transactions/view&id=x', 'payload openUrl at top level (service worker)');
check(($payload['data']['openUrl'] ?? null) === '/index.php?r=transactions/view&id=x', 'payload data.openUrl');
check($payload['body'] === 'You received 5 TTS', 'payload body');
$endpoints = $db->createCommand('SELECT endpoint FROM mp_subscriptions WHERE id_user = 7')->queryColumn();
check($endpoints === ["http://127.0.0.1:{$port}/ok"], 'expired subscription removed, valid kept');
check((int) $db->createCommand('SELECT COUNT(*) FROM mp_subscriptions WHERE id_user = 8')->queryScalar() === 1, 'other users untouched');

echo 'PASS ' . $checks . " notification service checks\n";
