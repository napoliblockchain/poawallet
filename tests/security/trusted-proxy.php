<?php
// X-Forwarded-* headers are honoured only from TRUSTED_PROXIES (real config/web.php request settings).
define('YII_DEBUG', false);
define('YII_ENABLE_ERROR_HANDLER', false);
error_reporting(E_ALL & ~E_DEPRECATED);
$root = getenv('NAPAY_TEST_APP_ROOT') ?: dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
require $root . '/vendor/yiisoft/yii2/Yii.php';

// test-only values for the variables required by config/web.php
foreach (['COOKIE_VALIDATION_KEY', 'DB_DSN', 'DB_USERNAME', 'DB_PASSWORD', 'ENCRYPT_SECRET_KEY', 'ENCRYPT_SECRET_IV',
    'MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_NAME', 'MAIL_ADMIN', 'MAIL_SUPPORT',
    'APP_WEBSITE', 'APP_ADMIN_NAME'] as $name) {
    putenv("$name=test-$name");
}
putenv('REDIS_HOST=');

$checks = 0;
function check($condition, $label) {
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
}
function request($trustedProxies, $remoteAddr, array $headers) {
    putenv("TRUSTED_PROXIES=$trustedProxies");
    $config = require $GLOBALS['root'] . '/config/web.php';
    // headers arrive as server variables, like from Apache: Yii filters untrusted ones while reading them
    $_SERVER['REMOTE_ADDR'] = $remoteAddr;
    $_SERVER['HTTP_HOST'] = 'wallet.local:18443';
    unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO'], $_SERVER['HTTP_X_FORWARDED_FOR']);
    foreach ($headers as $name => $value) {
        $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
    }
    return Yii::createObject(['class' => yii\web\Request::class] + $config['components']['request']);
}

$gateway = '172.22.0.5';
$forwarded = ['X-Forwarded-Proto' => 'https', 'X-Forwarded-For' => '203.0.113.9'];

$r = request('10.0.0.0/8,172.16.0.0/12,192.168.0.0/16', $gateway, $forwarded);
check($r->getIsSecureConnection() === true, 'trusted gateway: HTTPS detected');
check($r->getHostInfo() === 'https://wallet.local:18443', 'trusted gateway: absolute links use https');
check($r->getUserIP() === '203.0.113.9', 'trusted gateway: client IP from X-Forwarded-For');

$r = request('10.0.0.0/8,172.16.0.0/12,192.168.0.0/16', '203.0.113.50', $forwarded);
check($r->getIsSecureConnection() === false, 'untrusted source: X-Forwarded-Proto ignored');
check($r->getUserIP() === '203.0.113.50', 'untrusted source: X-Forwarded-For ignored');

$r = request('', $gateway, $forwarded);
check($r->getIsSecureConnection() === false && $r->getHostInfo() === 'http://wallet.local:18443', 'no TRUSTED_PROXIES: no proxy trusted');

echo 'PASS ' . $checks . " trusted proxy checks\n";
