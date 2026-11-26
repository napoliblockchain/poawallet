<?php
// Access control check on EVERY controller action (auto-discovered), using the real AccessControl filter.
// Policy: guest denied everywhere except the explicit public list; global configuration only for webmaster.
// No production database: SQLite in memory with the profiles table.
define('YII_DEBUG', false);
define('YII_ENABLE_ERROR_HANDLER', false);
error_reporting(E_ALL & ~E_DEPRECATED);
$root = getenv('NAPAY_TEST_APP_ROOT') ?: dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
require $root . '/vendor/yiisoft/yii2/Yii.php';

class AccessTestIdentity implements yii\web\IdentityInterface
{
    public $privilege_id;
    public function __construct($privilegeId) { $this->privilege_id = $privilegeId; }
    public function getId() { return 1; }
    public function getAuthKey() { return 'fixture'; }
    public function validateAuthKey($key) { return $key === 'fixture'; }
    public static function findIdentity($id) { return null; }
    public static function findIdentityByAccessToken($token, $type = null) { return null; }
}

$app = new yii\web\Application([
    'id' => 'access-control-test', 'basePath' => $root, 'runtimePath' => '/tmp/napay-access-runtime',
    'components' => [
        'request' => ['cookieValidationKey' => 'fixture-only', 'scriptFile' => $root . '/web/index.php', 'scriptUrl' => '/index.php'],
        'user' => ['identityClass' => AccessTestIdentity::class, 'enableSession' => false, 'loginUrl' => null],
        'db' => ['class' => yii\db\Connection::class, 'dsn' => 'sqlite::memory:'],
    ],
]);
$app->db->createCommand('CREATE TABLE profiles (id INTEGER PRIMARY KEY, description TEXT, level INTEGER, resource TEXT)')->execute();
$app->db->createCommand()->batchInsert('profiles', ['id', 'description', 'level', 'resource'], [
    [1, 'Webmaster', 100, 'webmaster'], [5, 'User', 20, 'user'],
])->execute();

// actions reachable without login, by design
const PUBLIC_ACTIONS = [
    'site/error', 'site/index', 'site/login', 'site/register', 'site/activate',
    'site/request-password-reset', 'site/reset-password',
    'oauthtelegram/check-authorization',
];
// controllers that manage global configuration or other users' data
const WEBMASTER_CONTROLLERS = ['logs', 'profiles'];

$checks = 0;
function check($condition, $label) {
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
}
// runs the access filter as Yii does on a real request (honours only/except)
function allowed($controller, $action) {
    $access = $controller->getBehavior('access');
    if ($access === null) {
        return true;
    }
    $event = new yii\base\ActionEvent($action);
    try {
        $access->beforeFilter($event);
    } catch (yii\web\ForbiddenHttpException $e) {
        return false;
    }
    return $event->isValid;
}
function kebab($name) { return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $name)); }

$files = array_merge(glob($root . '/controllers/*Controller.php'), glob($root . '/controllers/settings/*Controller.php'));
$guest = null;
$user = new AccessTestIdentity(5);
$webmaster = new AccessTestIdentity(1);
$seenPublic = [];
foreach ($files as $file) {
    $base = basename($file, 'Controller.php');
    if (str_starts_with($base, 'old_')) {
        continue;
    }
    $sub = basename(dirname($file)) === 'settings' ? 'settings\\' : '';
    $class = 'app\\controllers\\' . $sub . $base . 'Controller';
    $id = ($sub ? 'settings/' : '') . kebab($base);
    $controller = new $class($id, Yii::$app);
    foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $m) {
        if (!preg_match('/^action([A-Z]\w*)$/', $m->name, $mm) || $m->class !== $class) {
            continue;
        }
        $route = $id . '/' . kebab($mm[1]);
        $action = $controller->createAction(kebab($mm[1]));
        check($action !== null, "$route resolvable");

        Yii::$app->user->setIdentity($guest);
        if (in_array($route, PUBLIC_ACTIONS, true)) {
            check(allowed($controller, $action), "public route open to guest: $route");
            $seenPublic[] = $route;
            continue;
        }
        check(!allowed($controller, $action), "guest denied on $route");

        Yii::$app->user->setIdentity($user);
        $userAllowed = allowed($controller, $action);
        Yii::$app->user->setIdentity($webmaster);
        $webmasterAllowed = allowed($controller, $action);
        if (in_array($id, WEBMASTER_CONTROLLERS, true)) {
            check(!$userAllowed, "plain user denied on $route");
        } else {
            check($userAllowed, "user allowed on $route");
        }
        check($webmasterAllowed, "webmaster allowed on $route");
    }
}
sort($seenPublic);
$expected = PUBLIC_ACTIONS;
sort($expected);
check($seenPublic === $expected, 'every declared public action exists');

echo 'PASS ' . $checks . " access control checks\n";
