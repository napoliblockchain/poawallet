<?php
// Ticket del flusso /events (SIP-029 passo 2): formato identico al servizio Go, cookie limitato e sicuro,
// endpoint solo POST con CSRF per utente collegato, cookie cancellato al logout.
define('YII_DEBUG', false);
define('YII_ENABLE_ERROR_HANDLER', false);
error_reporting(E_ALL & ~E_DEPRECATED);
$root = getenv('NAPAY_TEST_APP_ROOT') ?: dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
require $root . '/vendor/yiisoft/yii2/Yii.php';

use app\components\RealtimeTicket;

class RealtimeTestIdentity implements yii\web\IdentityInterface
{
    public function __construct(private int $id) {}
    public function getId() { return $this->id; }
    public function getAuthKey() { return 'fixture'; }
    public function validateAuthKey($key) { return $key === 'fixture'; }
    public static function findIdentity($id) { return null; }
    public static function findIdentityByAccessToken($token, $type = null) { return null; }
}

$checks = 0;
function check($condition, string $label): void {
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
}
function fresh(): void {
    Yii::$app->set('response', new yii\web\Response());
}
function cookies(): array {
    return Yii::$app->response->headers->get('Set-Cookie', [], false);
}

new yii\web\Application([
    'id' => 'realtime-ticket-test', 'basePath' => $root, 'runtimePath' => '/tmp/napay-realtime-runtime',
    'components' => [
        'request' => ['cookieValidationKey' => 'fixture-only', 'scriptFile' => $root . '/web/index.php', 'scriptUrl' => '/index.php'],
        'user' => ['identityClass' => RealtimeTestIdentity::class, 'enableSession' => false],
    ],
]);
$key = str_repeat('ab', 32);
$file = tempnam('/tmp', 'rt-key');

// stesso vettore di realtime/internal/push/push_test.go
check(RealtimeTicket::sign($key, 42, 1800000600) === 'v1.42.1800000600.5Uz7MhL_qrtNgrRb0r3sVOqOUPYpPQGH5TzzrOPbuHM', 'ticket identical to the Go service format');

putenv('REALTIME_KEY_FILE');
check(RealtimeTicket::key() === null, 'no key file: realtime disabled');
foreach (['short' => 'abcd', 'uppercase' => strtoupper($key), 'not hex' => str_repeat('zz', 32)] as $label => $value) {
    file_put_contents($file, $value);
    putenv('REALTIME_KEY_FILE=' . $file);
    check(RealtimeTicket::key() === null, "invalid key refused: $label");
}
file_put_contents($file, $key . "\n");
check(RealtimeTicket::key() === $key, 'valid key read, trailing newline ignored');

fresh();
check(RealtimeTicket::issue(0) === false && cookies() === [], 'no ticket without a user');
$before = time();
check(RealtimeTicket::issue(7) === true, 'ticket issued for the user');
$cookie = cookies()[0] ?? '';
check(preg_match('/^wallet_rt=(v1\.7\.(\d+)\.[A-Za-z0-9_-]{43}); Max-Age=600; Path=\/events; Secure; HttpOnly; SameSite=Strict$/D', $cookie, $m) === 1, 'cookie limited to /events, Secure, HttpOnly, SameSite=Strict');
check((int) $m[2] >= $before + 600 && (int) $m[2] <= time() + 600 && $m[1] === RealtimeTicket::sign($key, 7, (int) $m[2]), 'ticket expires in 10 minutes with a valid signature');

fresh();
RealtimeTicket::clear();
check(cookies() === ['wallet_rt=; Max-Age=0; Path=/events; Secure; HttpOnly; SameSite=Strict'], 'clear removes the cookie');

// endpoint: POST, CSRF, utente collegato
$_SERVER['REQUEST_METHOD'] = 'POST';
Yii::$app->user->setIdentity(new RealtimeTestIdentity(9));
$controller = new app\controllers\RealtimeController('realtime', Yii::$app);
fresh();
try {
    $controller->runAction('ticket');
    check(false, 'POST without CSRF token refused');
} catch (yii\web\BadRequestHttpException $e) {
    check(cookies() === [], 'POST without CSRF token refused');
}
Yii::$app->request->enableCsrfValidation = false;
fresh();
$controller->runAction('ticket');
check(Yii::$app->response->statusCode === 204 && str_starts_with(cookies()[0] ?? '', 'wallet_rt=v1.9.'), 'logged user gets 204 and the ticket of their own id');
$_SERVER['REQUEST_METHOD'] = 'GET';
fresh();
try {
    $controller->runAction('ticket');
    check(false, 'GET refused');
} catch (yii\web\MethodNotAllowedHttpException $e) {
    check(cookies() === [], 'GET refused');
}
$_SERVER['REQUEST_METHOD'] = 'POST';
putenv('REALTIME_KEY_FILE');
fresh();
try {
    $controller->runAction('ticket');
    check(false, 'missing key: 503');
} catch (yii\web\HttpException $e) {
    check($e->statusCode === 503 && cookies() === [], 'missing key: 503, no cookie');
}

// logout: il browser non conserva il ticket
fresh();
(new app\controllers\SiteController('site', Yii::$app))->runAction('logout');
check(in_array('wallet_rt=; Max-Age=0; Path=/events; Secure; HttpOnly; SameSite=Strict', cookies(), true) && Yii::$app->user->isGuest, 'logout clears the realtime cookie');

unlink($file);
echo 'PASS ' . $checks . " realtime ticket checks\n";
