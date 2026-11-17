<?php
// The fallback error path (error while handling an error) must never print environment variables:
// secrets live in $_SERVER because configuration comes from the environment.
// Each handler runs in a child process (the fallback calls exit) with YII_DEBUG on and a canary variable.
$root = getenv('NAPAY_TEST_APP_ROOT') ?: dirname(__DIR__, 2);

if (($argv[1] ?? '') === 'child') {
    define('YII_DEBUG', true);
    require $root . '/vendor/autoload.php';
    require $root . '/vendor/yiisoft/yii2/Yii.php';
    new yii\console\Application(['id' => 'error-handler-test', 'basePath' => $root]);
    $handler = Yii::createObject($argv[2]);
    $method = new ReflectionMethod($handler, 'handleFallbackExceptionMessage');
    $method->invoke($handler, new RuntimeException('second-error'), new RuntimeException('first-error'));
    exit(0);
}

$checks = 0;
function check($condition, $label) {
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
}
function runChild($handlerClass) {
    $canary = 'canary-' . bin2hex(random_bytes(8));
    $env = getenv() + ['SECRET_CANARY' => $canary];
    $env['SECRET_CANARY'] = $canary;
    $proc = proc_open([PHP_BINARY, __FILE__, 'child', $handlerClass], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
    $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    $code = proc_close($proc);
    return [$output, $code, $canary];
}

[$out, $code, $canary] = runChild(yii\console\ErrorHandler::class);
check(str_contains($out, $canary), 'control: original Yii handler leaks environment (test detects the leak)');

foreach (['app\components\errors\ConsoleErrorHandler', 'app\components\errors\WebErrorHandler'] as $class) {
    [$out, $code, $canary] = runChild($class);
    check($code === 1, "$class: fallback still terminates with error code");
    check(str_contains($out, 'first-error') && str_contains($out, 'second-error'), "$class: errors still reported");
    check(!str_contains($out, $canary) && !str_contains($out, '$_SERVER'), "$class: environment not printed");
}

echo 'PASS ' . $checks . " error handler checks\n";
