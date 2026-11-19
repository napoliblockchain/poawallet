<?php
if (PHP_SAPI !== 'cli' || getenv('WALLET_PASSWORD_TEST') !== 'isolated-password-change' || getenv('DB_DSN') !== 'mysql:host=mysql;dbname=wallet_password_test') exit(1);
$root = dirname(__DIR__); define('YII_DEBUG', false); define('YII_ENV', 'prod'); define('YII_ENABLE_ERROR_HANDLER', false);
require $root . '/vendor/autoload.php'; require $root . '/vendor/yiisoft/yii2/Yii.php';
$app = new yii\web\Application(require $root . '/config/web.php'); $db = $app->db;
$checks = 0; $jars = []; $jar = null;
register_shutdown_function(static function () use (&$jars) { foreach ($jars as $file) @unlink($file); });
function check($ok, $label) {
    global $checks, $root;
    if (!$ok) {
        // Failure locations only: never dump request/session values or exception messages.
        foreach (glob($root . '/runtime/logs/*') ?: [] as $file) if (is_file($file)) {
            preg_match_all('~ in (/var/www/[^:\r\n]+):([0-9]+)~', file_get_contents($file), $locations, PREG_SET_ORDER);
            foreach (array_slice($locations, -3) as $location) fwrite(STDERR, 'Fixture error location: ' . $location[1] . ':' . $location[2] . "\n");
        }
        throw new RuntimeException($label);
    }
    ++$checks; echo "PASS: $label\n";
}
function request($path, $post = null) {
    global $jar; $c = curl_init('http://127.0.0.1' . $path);
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 15, CURLOPT_PROXY => '', CURLOPT_FOLLOWLOCATION => false, CURLOPT_COOKIEFILE => $jar, CURLOPT_COOKIEJAR => $jar]);
    if ($post !== null) curl_setopt_array($c, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post)]);
    $raw = curl_exec($c); if ($raw === false) throw new RuntimeException('Fixture transport failed');
    $n = curl_getinfo($c, CURLINFO_HEADER_SIZE); $r = ['status' => curl_getinfo($c, CURLINFO_RESPONSE_CODE), 'body' => substr($raw, $n), 'headers' => substr($raw, 0, $n)]; curl_close($c); return $r;
}
function csrf($page) { $d = new DOMDocument(); @$d->loadHTML($page['body'], LIBXML_NONET); $n = (new DOMXPath($d))->query('//meta[@name="csrf-token"]/@content'); if ($n->length !== 1) throw new RuntimeException('CSRF missing'); return $n->item(0)->nodeValue; }
function newJar() { global $jar, $jars; $jar = tempnam('/tmp', 'wallet-password-'); $jars[] = $jar; return $jar; }
function login($name = 'self', $password = 'fixture-password-only') { $p = request('/site/login'); return request('/site/login', ['_csrf' => csrf($p), 'LoginForm' => ['username' => $name . '@example.invalid', 'password' => $password, 'rememberMe' => 1, 'oauth_provider' => 'mail']]); }
function change($current, $password, $repeat = null, $extra = []) { $p = request('/users/change-password'); return request('/users/change-password', ['_csrf' => csrf($p), 'ChangePasswordForm' => ['current_password' => $current, 'password' => $password, 'repeat_password' => $repeat ?? $password] + $extra]); }
check($db->createCommand('SELECT DATABASE()')->queryScalar() === 'wallet_password_test', 'Disposable wallet database confirmed');
$profile = (int) $db->createCommand("SELECT id FROM profiles WHERE resource='user'")->queryScalar(); $ids = [];
foreach (['self', 'other', 'disabled'] as $name) {
    $db->createCommand()->insert('mp_users', ['username' => $name . '@example.invalid', 'email' => $name . '@example.invalid', 'password' => Yii::$app->security->generatePasswordHash('fixture-password-only'), 'activation_code' => 'old-reset-fixture', 'status_activation_code' => 1, 'oauth_provider' => 'mail', 'oauth_uid' => $name, 'authKey' => Yii::$app->security->generateRandomString(), 'accessToken' => Yii::$app->security->generateRandomString(60), 'first_name' => $name, 'last_name' => 'Fixture', 'picture' => '/favicon.ico', 'privilege_id' => $profile])->execute();
    $ids[$name] = (int) $db->lastInsertID;
}
$db->createCommand()->insert('mp_wallets', ['id_user' => $ids['self'], 'wallet_address' => '0x1111111111111111111111111111111111111111', 'blocknumber' => '123'])->execute();
$wallets = $db->createCommand('SELECT * FROM mp_wallets ORDER BY id')->queryAll(); $other = app\models\Users::findOne($ids['other'])->attributes;
newJar(); check(request('/users/change-password')['status'] === 302, 'Wallet guest change requires login');
check(login()['status'] === 302, 'Wallet fixture login'); $before = app\models\Users::findOne($ids['self'])->attributes;
$page = request('/users/change-password'); check($page['status'] === 200 && str_contains($page['body'], '/users/change-password'), 'Wallet password form and menu available');
check(request('/users/change-password', ['ChangePasswordForm' => ['current_password' => 'wallet-csrf-password-canary']])['status'] === 400, 'Wallet change requires CSRF');
$oldJar = $jar; newJar(); check(login()['status'] === 302, 'Wallet second remembered session login'); $secondJar = $jar; $jar = $oldJar;
check(str_contains((string) file_get_contents($secondJar), "\t_identity\t"), 'Wallet real remember-me cookie issued');
foreach ([['wrong-current', 'wallet-valid-new-password'], ['fixture-password-only', 'short'], ['fixture-password-only', 'wallet-valid-new-password', 'different'], ['fixture-password-only', str_repeat('x', 73)], ['fixture-password-only', str_repeat('é', 37)], [['array'], 'wallet-valid-new-password']] as $n => $case) {
    $r = change(...$case); check($r['status'] === 200 && !str_contains($r['body'], 'value="wallet-valid-new-password"'), 'Wallet invalid change safely rejected: ' . $n);
    check(app\models\Users::findOne($ids['self'])->password === $before['password'], 'Wallet rejected change preserves credentials');
}
$app->cache->flush();
for ($i = 0; $i < 5; ++$i) check(change('wrong-current', 'wallet-valid-new-password')['status'] === 200, 'Wallet wrong current password counted: ' . $i);
$r = change('fixture-password-only', 'wallet-valid-new-password'); check($r['status'] === 429 && stripos($r['headers'], 'Retry-After:') !== false, 'Wallet password guessing throttled');
$app->cache->flush(); $new = 'wallet-fixture-new-password-only'; $r = change('fixture-password-only', $new, null, ['user_id' => $ids['other'], 'authKey' => 'injected']);
check($r['status'] === 302 && str_contains($r['headers'], '/site/login'), 'Wallet successful change signs current session out');
$after = app\models\Users::findOne($ids['self']);
check(Yii::$app->security->validatePassword($new, $after->password) && !Yii::$app->security->validatePassword('fixture-password-only', $after->password), 'Wallet new password replaces old');
check($after->authKey !== $before['authKey'] && $after->accessToken !== $before['accessToken'] && $after->activation_code === null, 'Wallet remember-me, bearer and reset credentials rotated');
check(app\models\Users::findOne($ids['other'])->attributes === $other, 'Wallet supplied user ID cannot change another account');
check($db->createCommand('SELECT * FROM mp_wallets ORDER BY id')->queryAll() === $wallets, 'Wallet address and chain data unchanged');
$jar = $secondJar; check(request('/users/change-password')['status'] === 302, 'Wallet other web session revoked');
$file = newJar(); file_put_contents($file, implode("\n", array_filter(file($secondJar, FILE_IGNORE_NEW_LINES), static fn($line) => !str_contains($line, "\tPHPSESSID\t"))) . "\n");
check(request('/users/change-password')['status'] === 302, 'Wallet old remembered cookie rejected');
newJar(); check(login('self', $new)['status'] === 302, 'Wallet new password authenticates');
$mails = glob($root . '/runtime/mail/*.eml') ?: []; $mail = implode("\n", array_map('file_get_contents', $mails));
check($mails !== [] && !str_contains($mail, $new) && !str_contains($mail, 'fixture-password-only'), 'Wallet notification email contains no password');
$logs = $db->createCommand('SELECT description FROM logs')->queryColumn();
check(count(array_filter($logs, static fn($v) => $v === 'Account password changed: user ID ' . $ids['self'] . '.')) === 1, 'Wallet password change audited');
$raw = implode("\n", $logs); foreach (glob($root . '/runtime/logs/*') ?: [] as $file) if (is_file($file)) $raw .= file_get_contents($file);
check(!str_contains($raw, $new) && !str_contains($raw, 'wrong-current') && !str_contains($raw, 'wallet-csrf-password-canary'), 'Wallet error and audit logs never dump passwords, including CSRF failures');
newJar(); check(login('disabled')['status'] === 302, 'Wallet initially active identity logs in'); $db->createCommand()->update('mp_users', ['status_activation_code' => 0], ['id' => $ids['disabled']])->execute();
check(change('fixture-password-only', 'wallet-valid-new-password')['status'] === 200 && Yii::$app->security->validatePassword('fixture-password-only', app\models\Users::findOne($ids['disabled'])->password), 'Wallet disabled account cannot change credentials through stale identity');
echo "PASS $checks wallet password HTTP checks\n";
