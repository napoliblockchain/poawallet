<?php
// Record-level authorization (IDOR): a logged user can load only their own records through findModel().
// SQLite in memory, two users; calls the real controllers' findModel().
define('YII_DEBUG', false);
define('YII_ENABLE_ERROR_HANDLER', false);
error_reporting(E_ALL & ~E_DEPRECATED);
$root = getenv('NAPAY_TEST_APP_ROOT') ?: dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
require $root . '/vendor/yiisoft/yii2/Yii.php';

class OwnershipTestIdentity implements yii\web\IdentityInterface
{
    public function __construct(private int $id) {}
    public function getId() { return $this->id; }
    public function getAuthKey() { return 'fixture'; }
    public function validateAuthKey($key) { return $key === 'fixture'; }
    public static function findIdentity($id) { return null; }
    public static function findIdentityByAccessToken($token, $type = null) { return null; }
}

new yii\web\Application([
    'id' => 'ownership-test', 'basePath' => $root, 'runtimePath' => '/tmp/napay-ownership-runtime',
    'components' => [
        'request' => ['cookieValidationKey' => 'fixture-only', 'scriptFile' => $root . '/web/index.php', 'scriptUrl' => '/index.php'],
        'user' => ['identityClass' => OwnershipTestIdentity::class, 'enableSession' => false],
        'db' => ['class' => yii\db\Connection::class, 'dsn' => 'sqlite::memory:'],
    ],
]);
$db = Yii::$app->db;
foreach ([
    'CREATE TABLE mp_users (id INTEGER PRIMARY KEY, username TEXT)',
    'CREATE TABLE mp_wallets (id INTEGER PRIMARY KEY, id_user INTEGER, wallet_address TEXT, blocknumber TEXT)',
    'CREATE TABLE transactions (id INTEGER PRIMARY KEY, id_user INTEGER, type TEXT, id_smart_contract INTEGER, status TEXT, token_price REAL, token_received REAL, invoice_timestamp INTEGER, expiration_timestamp INTEGER, from_address TEXT, to_address TEXT, blocknumber TEXT, txhash TEXT, message TEXT)',
    'CREATE TABLE mp_notifications_readers (id INTEGER PRIMARY KEY, id_user INTEGER, id_notification INTEGER, alreadyread INTEGER)',
    'CREATE TABLE blockchains (id INTEGER PRIMARY KEY, id_user INTEGER, denomination TEXT, chain_id TEXT, url TEXT, symbol TEXT, url_block_explorer TEXT, zerogas INTEGER)',
    'CREATE TABLE smart_contract (id INTEGER PRIMARY KEY, id_user INTEGER, id_blockchain INTEGER, id_contract_type INTEGER, denomination TEXT, smart_contract_address TEXT, decimals INTEGER, symbol TEXT)',
] as $sql) {
    $db->createCommand($sql)->execute();
}
// user 1 = Alice (0xaaa...), user 2 = Bob (0xbbb...), user 3 = Carol without wallet
$alice = '0x' . str_repeat('a', 40);
$bob = '0x' . str_repeat('b', 40);
$db->createCommand()->batchInsert('mp_users', ['id', 'username'], [[1, 'alice'], [2, 'bob'], [3, 'carol']])->execute();
$db->createCommand()->batchInsert('mp_wallets', ['id_user', 'wallet_address', 'blocknumber'], [[1, $alice, '0x0'], [2, strtoupper($bob), '0x0']])->execute();
$db->createCommand()->batchInsert('transactions', ['id', 'id_user', 'type', 'status', 'from_address', 'to_address'], [
    [10, 1, 'token', 'complete', $alice, $bob],                        // Alice -> Bob, registered by Alice
    [11, 2, 'token', 'complete', $bob, '0x' . str_repeat('c', 40)],    // Bob -> third party
])->execute();
$db->createCommand()->batchInsert('mp_notifications_readers', ['id', 'id_user', 'id_notification', 'alreadyread'], [[20, 1, 1, 0], [21, 2, 1, 0]])->execute();
$db->createCommand()->batchInsert('blockchains', ['id', 'id_user', 'denomination', 'chain_id', 'url', 'symbol'], [[30, 1, 'A', '1', 'http://a', 'A'], [31, 2, 'B', '1', 'http://b', 'B']])->execute();
$db->createCommand()->batchInsert('smart_contract', ['id', 'id_user', 'id_blockchain', 'id_contract_type', 'denomination', 'smart_contract_address', 'decimals', 'symbol'], [[40, 1, 30, 1, 'A', $alice, 2, 'A'], [41, 2, 31, 1, 'B', $bob, 2, 'B']])->execute();

$checks = 0;
function check($condition, $label) {
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
}
function load($controllerClass, $id, $userId) {
    Yii::$app->user->setIdentity(new OwnershipTestIdentity($userId));
    $controller = new $controllerClass('test', Yii::$app);
    $method = new ReflectionMethod($controller, 'findModel');
    try {
        return $method->invoke($controller, $id);
    } catch (yii\web\NotFoundHttpException $e) {
        return null;
    }
}
$tx = app\controllers\TransactionsController::class;
check(load($tx, 10, 1) !== null, 'transaction: author sees it');
check(load($tx, 10, 2) !== null, 'transaction: recipient sees it (address match, case-insensitive)');
check(load($tx, 10, 3) === null, 'transaction: unrelated user without wallet denied');
check(load($tx, 11, 1) === null, 'transaction: other user transaction denied');

$users = app\controllers\UsersController::class;
check(load($users, 1, 1) !== null, 'user: own profile');
check(load($users, 2, 1) === null, 'user: other profile denied');

$messages = app\controllers\MessagesController::class;
check(load($messages, 20, 1) !== null, 'message: own notification');
check(load($messages, 21, 1) === null, 'message: other user notification denied');

// controller doppioni /settings/* rimossi: reti e token solo da BlockchainsController e SmartcontractsController
check(!class_exists('app\\controllers\\settings\\BlockchainsController') && !class_exists('app\\controllers\\settings\\TokensController'), 'duplicate settings controllers removed');

foreach ([app\controllers\BlockchainsController::class => [30, 31], app\controllers\SmartcontractsController::class => [40, 41]] as $class => [$own, $other]) {
    check(load($class, $own, 1) !== null, "$class: own record");
    check(load($class, $other, 1) === null, "$class: other user record denied");
}

echo 'PASS ' . $checks . " ownership checks\n";
