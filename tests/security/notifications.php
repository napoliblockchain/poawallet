<?php
// Isolated SQLite test: never loads production DB configuration or secrets.
define('YII_DEBUG', false);
define('YII_ENABLE_ERROR_HANDLER', false);
error_reporting(E_ALL & ~E_DEPRECATED);
$root = getenv('NAPAY_TEST_APP_ROOT') ?: dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
require $root . '/vendor/yiisoft/yii2/Yii.php';

class NotificationTestIdentity implements yii\web\IdentityInterface
{
    public function getId() { return 1; }
    public function getAuthKey() { return 'fixture'; }
    public function validateAuthKey($key) { return $key === 'fixture'; }
    public static function findIdentity($id) { return null; }
    public static function findIdentityByAccessToken($token, $type = null) { return null; }
}
class NotificationTestRequest extends yii\web\Request
{
    public $verb = 'GET';
    public function getMethod() { return $this->verb; }
}
$app = new yii\web\Application([
    'id' => 'notification-security-test', 'basePath' => $root, 'runtimePath' => '/tmp/napay-security-runtime',
    'components' => [
        'request' => ['class' => NotificationTestRequest::class, 'cookieValidationKey' => 'fixture-only',
            'enableCsrfCookie' => false, 'scriptFile' => $root . '/web/index.php', 'scriptUrl' => '/index.php'],
        'user' => ['identityClass' => NotificationTestIdentity::class, 'enableSession' => false],
        'db' => ['class' => yii\db\Connection::class, 'dsn' => 'sqlite::memory:'],
        'session' => ['class' => yii\web\Session::class, 'savePath' => '/tmp'],
    ],
]);
$db = $app->db;
$db->createCommand('CREATE TABLE mp_notifications_readers (id INTEGER PRIMARY KEY, id_user INTEGER, id_notification INTEGER, alreadyread INTEGER)')->execute();
$db->createCommand('CREATE TABLE mp_wallets (id INTEGER PRIMARY KEY, id_user INTEGER, wallet_address TEXT)')->execute();
$db->createCommand('CREATE TABLE mp_notifications (id INTEGER PRIMARY KEY, type TEXT, status TEXT, description TEXT, url TEXT, timestamp INTEGER)')->execute();
$db->createCommand()->batchInsert('mp_notifications_readers', ['id', 'id_user', 'id_notification', 'alreadyread'], [
    [1, 1, 1, 0], [2, 2, 1, 0], [3, 11, 10, 0], [4, 1, 2, 0],
])->execute();
$db->createCommand()->batchInsert('mp_notifications', ['id', 'type', 'status', 'description', 'url', 'timestamp'], [
    [1, 'token', 'complete', '<script>alert(1)</script>', 'index.php?r=messages/index', time()],
    [2, 'token', 'complete', 'Second', 'index.php?r=messages/index', time()],
])->execute();
$checks = 0;
function check($condition, $label) {
    global $checks;
    if (!$condition) throw new RuntimeException($label);
    $checks++;
}
function invokeNotificationAction($action, $method = 'GET', $params = [], $csrf = true) {
    $request = Yii::$app->request;
    $request->verb = $method;
    $request->setBodyParams($params);
    $request->headers->remove('X-CSRF-Token');
    if ($csrf) $request->headers->set('X-CSRF-Token', $request->getCsrfToken());
    $controller = new app\controllers\BackendController('backend', Yii::$app);
    Yii::$app->controller = $controller;
    return $controller->runAction($action);
}
function expectHttp($code, $call) {
    try { $call(); } catch (yii\web\HttpException $e) {
        check($e->statusCode === $code, 'Expected HTTP ' . $code . ', got ' . $e->statusCode);
        return;
    }
    throw new RuntimeException('Expected HTTP ' . $code);
}
expectHttp(403, function () { invokeNotificationAction('notify'); });
expectHttp(403, function () { invokeNotificationAction('update-single-news', 'POST', ['id_notification' => 1]); });
$app->user->switchIdentity(new NotificationTestIdentity());
expectHttp(405, function () { invokeNotificationAction('update-all-news'); });
expectHttp(400, function () { invokeNotificationAction('update-all-news', 'POST', [], false); });
expectHttp(400, function () { invokeNotificationAction('update-single-news', 'POST', ['id_notification' => -1]); });
expectHttp(400, function () { invokeNotificationAction('update-single-news', 'POST', ['id_notification' => [1]]); });
expectHttp(404, function () { invokeNotificationAction('update-single-news', 'POST', ['id_notification' => 10]); });
$app->request->setQueryParams(['user_id' => 2]);
$data = invokeNotificationAction('notify');
check($data['userId'] === 1 && $data['countedUnread'] === 2, 'Notify uses session identity');
check(strpos($data['htmlContent'], '<script>') === false, 'Description HTML is escaped');
check((int)$db->createCommand('SELECT SUM(alreadyread) FROM mp_notifications_readers')->queryScalar() === 0, 'Receive does not mark read');
invokeNotificationAction('update-single-news', 'POST', ['id_notification' => 1]);
check((int)$db->createCommand('SELECT alreadyread FROM mp_notifications_readers WHERE id=1')->queryScalar() === 1, 'Owned notification marked read');
check((int)$db->createCommand('SELECT alreadyread FROM mp_notifications_readers WHERE id=2')->queryScalar() === 0, 'Other reader of same notification unchanged');
invokeNotificationAction('update-all-news', 'POST');
check((int)$db->createCommand('SELECT alreadyread FROM mp_notifications_readers WHERE id=4')->queryScalar() === 1, 'Mark all updates owned records');
check((int)$db->createCommand('SELECT SUM(alreadyread) FROM mp_notifications_readers WHERE id_user <> 1')->queryScalar() === 0, 'Other users unchanged, including id 11');
function invokePushAction($body, $method = 'POST', $csrf = true) {
    $request = Yii::$app->request;
    $request->verb = $method;
    $request->setRawBody($body);
    $request->setBodyParams([]);
    $request->headers->remove('X-CSRF-Token');
    if ($csrf) $request->headers->set('X-CSRF-Token', $request->getCsrfToken());
    $controller = new app\controllers\PushController('push', Yii::$app);
    Yii::$app->controller = $controller;
    return json_decode($controller->runAction('notified'), true);
}
expectHttp(405, function () { invokePushAction('{}', 'GET'); });
expectHttp(400, function () { invokePushAction('{}', 'POST', false); });
expectHttp(400, function () { invokePushAction('{bad'); });
expectHttp(400, function () { invokePushAction('{"model_id":-1}'); });
check(invokePushAction('{"model_id":2}')['success'] === false, 'Push rejects another user record');
check(invokePushAction('{"model_id":1}')['success'] === true, 'Push allows own record with CSRF');
echo 'PASS ' . $checks . " notification security checks\n";
