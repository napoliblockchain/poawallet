<?php
// Access control check: every action of the wallet controllers requires login.
// Uses the real controllers and the AccessControl filter, no database.
define('YII_DEBUG', false);
define('YII_ENABLE_ERROR_HANDLER', false);
error_reporting(E_ALL & ~E_DEPRECATED);
$root = getenv('NAPAY_TEST_APP_ROOT') ?: dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
require $root . '/vendor/yiisoft/yii2/Yii.php';

class AccessTestIdentity implements yii\web\IdentityInterface
{
    public function getId() { return 1; }
    public function getAuthKey() { return 'fixture'; }
    public function validateAuthKey($key) { return $key === 'fixture'; }
    public static function findIdentity($id) { return null; }
    public static function findIdentityByAccessToken($token, $type = null) { return null; }
}

new yii\web\Application([
    'id' => 'access-control-test', 'basePath' => $root, 'runtimePath' => '/tmp/napay-access-runtime',
    'components' => [
        'request' => ['cookieValidationKey' => 'fixture-only', 'scriptFile' => $root . '/web/index.php', 'scriptUrl' => '/index.php'],
        'user' => ['identityClass' => AccessTestIdentity::class, 'enableSession' => false, 'loginUrl' => null],
        'db' => ['class' => yii\db\Connection::class, 'dsn' => 'sqlite::memory:'],
    ],
]);

$checks = 0;
// runs the access filter as Yii does on a real request (honours only/except)
function allowed($controller, $action) {
    $event = new yii\base\ActionEvent($action);
    try {
        $controller->getBehavior('access')->beforeFilter($event);
    } catch (yii\web\ForbiddenHttpException $e) {
        return false;
    }
    return $event->isValid;
}
function check($condition, $label) {
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
}

// guest is denied, logged user is allowed, for every action method of these controllers
$controllers = ['wallet', 'wizard', 'send', 'blockchain', 'receive', 'spawn', 'settings'];
foreach ($controllers as $id) {
    $class = 'app\\controllers\\' . ucfirst($id) . 'Controller';
    $controller = new $class($id, Yii::$app);
    $actions = [];
    foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $m) {
        if (preg_match('/^action([A-Z]\w*)$/', $m->name, $mm)) {
            $actions[] = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $mm[1]));
        }
    }
    check($actions !== [], "$id has actions");
    foreach ($actions as $actionId) {
        $action = $controller->createAction($actionId);
        check($action !== null, "$id/$actionId resolvable");
        check($controller->getBehavior('access') !== null, "$id has access control");

        Yii::$app->user->setIdentity(null);
        check(!allowed($controller, $action), "guest denied on $id/$actionId");

        Yii::$app->user->setIdentity(new AccessTestIdentity());
        check(allowed($controller, $action), "user allowed on $id/$actionId");
    }
}

echo 'PASS ' . $checks . " access control checks\n";
