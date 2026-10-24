<?php
// Wallet secrets: values bound to the user, PIN verified server-side, seed revealed only with the wallet password.
// Calls the real WalletController actions. SQLite in memory, array cache, test encryption keys only.
define('YII_DEBUG', false);
define('YII_ENABLE_ERROR_HANDLER', false);
error_reporting(E_ALL & ~E_DEPRECATED);
$root = getenv('NAPAY_TEST_APP_ROOT') ?: dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
require $root . '/vendor/yiisoft/yii2/Yii.php';

use app\components\WebApp;

class SecretsTestIdentity implements yii\web\IdentityInterface
{
    public $username;
    public function __construct(private int $id) { $this->username = "user$id"; }
    public function getId() { return $this->id; }
    public function getAuthKey() { return 'fixture'; }
    public function validateAuthKey($key) { return $key === 'fixture'; }
    public static function findIdentity($id) { return null; }
    public static function findIdentityByAccessToken($token, $type = null) { return null; }
}
class SecretsTestRequest extends yii\web\Request
{
    public $verb = 'POST';
    public function getMethod() { return $this->verb; }
}

new yii\web\Application([
    'id' => 'wallet-secrets-test', 'basePath' => $root, 'runtimePath' => '/tmp/napay-secrets-runtime',
    'components' => [
        'request' => ['class' => SecretsTestRequest::class, 'cookieValidationKey' => 'fixture-only', 'enableCsrfCookie' => false,
            'scriptFile' => $root . '/web/index.php', 'scriptUrl' => '/index.php'],
        'user' => ['identityClass' => SecretsTestIdentity::class, 'enableSession' => false],
        'session' => ['class' => yii\web\Session::class, 'savePath' => '/tmp'],
        'db' => ['class' => yii\db\Connection::class, 'dsn' => 'sqlite::memory:'],
        'cache' => ['class' => yii\caching\ArrayCache::class],
    ],
    'params' => [
        'encryption' => ['secret_key' => 'test-key-only', 'secret_iv' => 'test-iv-only', 'encrypt_method' => 'AES-256-CBC'],
        'crypto.acceptLegacy' => true,
        'user.rememberMeDuration' => 3600,
    ],
]);
Yii::$app->db->createCommand('CREATE TABLE logs (id INTEGER PRIMARY KEY, timestamp INTEGER, remote_address TEXT, browser TEXT, description TEXT)')->execute();

$checks = 0;
function check($condition, $label) {
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
}
function asUser($id) {
    Yii::$app->user->setIdentity(new SecretsTestIdentity($id));
}
function call($action, $post = [], $verb = 'POST', $csrf = true) {
    $request = Yii::$app->request;
    $request->verb = $verb;
    $request->setBodyParams($post);
    $_POST = $post;
    $request->headers->remove('X-CSRF-Token');
    if ($csrf) {
        $request->headers->set('X-CSRF-Token', $request->getCsrfToken());
    }
    Yii::$app->response->statusCode = 200;
    $controller = new app\controllers\WalletController('wallet', Yii::$app);
    Yii::$app->controller = $controller;
    return $controller->runAction($action);
}
function expectHttp($code, $fn, $label) {
    try {
        $fn();
    } catch (yii\web\HttpException $e) {
        check($e->statusCode === $code, "$label (HTTP $code, got {$e->statusCode})");
        return;
    }
    check(false, "$label (expected HTTP $code)");
}

// --- WebApp: formato legato all'utente ---
$bound = WebApp::encryptForUser('1234', 7);
check(str_starts_with($bound, 'v2.'), 'bound value has v2 prefix');
check(WebApp::decryptForUser($bound, 7) === ['value' => '1234', 'legacy' => false], 'owner decrypts bound value');
check(WebApp::decryptForUser($bound, 8) === null, 'other user cannot decrypt bound value');
check(WebApp::decryptForUser('v2.' . substr($bound, 4), 7) === null, 'tampered value rejected');
check(WebApp::decryptForUser('', 7) === null && WebApp::decryptForUser(null, 7) === null, 'empty value rejected');
$legacy = WebApp::encrypt('1234');
check(WebApp::decryptForUser($legacy, 7) === ['value' => '1234', 'legacy' => true], 'legacy value accepted during migration');
Yii::$app->params['crypto.acceptLegacy'] = false;
check(WebApp::decryptForUser($legacy, 7) === null, 'legacy value rejected when migration is closed');
Yii::$app->params['crypto.acceptLegacy'] = true;

// --- decrypt non esiste piu ---
check((new app\controllers\WalletController('wallet', Yii::$app))->createAction('decrypt') === null, 'wallet/decrypt removed');
check((new app\controllers\WizardController('wizard', Yii::$app))->createAction('decrypt') === null, 'wizard/decrypt removed');
check((new app\controllers\WizardController('wizard', Yii::$app))->createAction('crypt') === null, 'wizard/crypt duplicate removed');

// --- crypt: valori legati all'utente loggato ---
asUser(7);
$crypted = call('crypt', ['pass' => 'wallet-password', 'seed' => 'seed words']);
check(WebApp::decryptForUser($crypted['cryptedpass'], 7)['value'] === 'wallet-password', 'crypt binds password to user');
check(WebApp::decryptForUser($crypted['cryptedseed'], 8) === null, 'crypted seed not usable by another user');

// --- verify-pin ---
$pin = WebApp::encryptForUser('54321', 7);
$ok = call('verify-pin', ['crypted_pin' => $pin, 'pin' => '54321']);
check($ok['valid'] === true && $ok['upgraded'] === null, 'correct PIN valid, no upgrade needed');
check(!array_key_exists('pin', $ok) && !in_array('54321', $ok, true), 'PIN never returned');
check(call('verify-pin', ['crypted_pin' => $pin, 'pin' => '00000'])['valid'] === false, 'wrong PIN rejected');
asUser(8);
check(call('verify-pin', ['crypted_pin' => $pin, 'pin' => '54321'])['valid'] === false, 'other user PIN ciphertext rejected');
asUser(7);
$upgrade = call('verify-pin', ['crypted_pin' => WebApp::encrypt('54321'), 'pin' => '54321']);
check($upgrade['valid'] === true && WebApp::decryptForUser($upgrade['upgraded'], 7)['value'] === '54321', 'legacy PIN valid and upgraded to bound format');
expectHttp(400, fn() => call('verify-pin', ['crypted_pin' => $pin, 'pin' => '54321'], 'POST', false), 'verify-pin requires CSRF token');
expectHttp(405, fn() => call('verify-pin', [], 'GET'), 'verify-pin requires POST');
asUser(9);
$pin9 = WebApp::encryptForUser('11111', 9);
for ($i = 0; $i < 5; $i++) {
    call('verify-pin', ['crypted_pin' => $pin9, 'pin' => '99999']);
}
$locked = call('verify-pin', ['crypted_pin' => $pin9, 'pin' => '11111']);
check(Yii::$app->response->statusCode === 429 && $locked['valid'] === false, 'PIN locked after 5 failures, even with the right PIN');

// --- reveal-seed ---
asUser(7);
$wallet = ['cryptedseed' => WebApp::encryptForUser('seed words', 7), 'prv_pas' => WebApp::encryptForUser('wallet-password', 7)];
$seed = call('reveal-seed', $wallet + ['password' => 'wallet-password']);
check($seed['success'] === true && $seed['seed'] === 'seed words', 'seed revealed with correct wallet password');
check($seed['upgraded'] === ['cryptedseed' => null, 'prv_pas' => null], 'no upgrade for bound values');
check((int) Yii::$app->db->createCommand("SELECT COUNT(*) FROM logs WHERE description LIKE 'Master seed revealed%'")->queryScalar() === 1, 'seed reveal written to audit log');
$wrong = call('reveal-seed', $wallet + ['password' => 'guess']);
check($wrong['success'] === false && !isset($wrong['seed']), 'wrong password: no seed');
check(call('reveal-seed', ['cryptedseed' => $wallet['cryptedseed'], 'password' => 'wallet-password'])['success'] === false, 'missing stored password: no seed');
asUser(8);
check(call('reveal-seed', $wallet + ['password' => 'wallet-password'])['success'] === false, 'other user cannot reveal seed with stolen ciphertexts');
asUser(7);
$legacySeed = call('reveal-seed', ['cryptedseed' => WebApp::encrypt('seed words'), 'prv_pas' => WebApp::encrypt('wallet-password'), 'password' => 'wallet-password']);
check($legacySeed['success'] === true
    && WebApp::decryptForUser($legacySeed['upgraded']['cryptedseed'], 7)['value'] === 'seed words'
    && WebApp::decryptForUser($legacySeed['upgraded']['prv_pas'], 7)['value'] === 'wallet-password', 'legacy seed and password upgraded to bound format');
expectHttp(400, fn() => call('reveal-seed', $wallet + ['password' => 'wallet-password'], 'POST', false), 'reveal-seed requires CSRF token');

echo 'PASS ' . $checks . " wallet secrets checks\n";
