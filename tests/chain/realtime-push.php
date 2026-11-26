<?php
// Push SSE del servizio wallet-realtime (SIP-029 passo 2) su stack usa-e-getta di run-disposable.sh.
// Apre /events per mittente, destinatario e un estraneo con ticket firmati come PHP, scrive /push/ready,
// attende il trasferimento reale fatto dal runner (/push/transfer.json) e verifica chi riceve quali eventi.
if (PHP_SAPI !== 'cli' || ($argv[1] ?? '') !== 'listen') exit(1);
error_reporting(E_ALL & ~E_DEPRECATED);
define('YII_DEBUG', false);
define('YII_ENV', 'test');
$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
require $root . '/vendor/yiisoft/yii2/Yii.php';
$config = require $root . '/config/web.php';
unset($config['bootstrap']);
$app = new yii\web\Application($config);

use app\components\RealtimeTicket;

$checks = 0;
function check($condition, string $label): void {
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    ++$checks;
    echo "  ok  $label\n";
}
$key = trim((string) file_get_contents('/push/key'));
$url = 'http://realtime:8080/events';
$users = ['sender' => 990101, 'receiver' => 990102, 'other' => 990103];

function status(string $method, ?string $ticket): int {
    global $url;
    $curl = curl_init($url);
    curl_setopt_array($curl, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5, CURLOPT_PROXY => '', CURLOPT_NOPROXY => '*']);
    if ($ticket !== null) curl_setopt($curl, CURLOPT_COOKIE, RealtimeTicket::COOKIE . '=' . $ticket);
    curl_exec($curl);
    $code = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    return $code;
}
check(status('GET', null) === 401, 'No ticket: 401');
check(status('GET', RealtimeTicket::sign(str_repeat('0', 64), $users['receiver'], time() + 600)) === 401, 'Ticket signed with another key: 401');
check(status('GET', RealtimeTicket::sign($key, $users['receiver'], time() + 3600)) === 401, 'Ticket valid for more than 15 minutes: 401');
check(status('GET', RealtimeTicket::sign($key, $users['receiver'], time() - 1)) === 401, 'Expired ticket: 401');
check(status('POST', RealtimeTicket::sign($key, $users['receiver'], time() + 600)) === 405, 'POST refused: 405');

$multi = curl_multi_init();
$buffers = [];
foreach ($users as $name => $id) {
    $buffers[$name] = '';
    $curl = curl_init($url);
    curl_setopt_array($curl, [CURLOPT_TIMEOUT => 60, CURLOPT_PROXY => '', CURLOPT_NOPROXY => '*',
        CURLOPT_COOKIE => RealtimeTicket::COOKIE . '=' . RealtimeTicket::sign($key, $id, time() + 600),
        CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$buffers, $name) { $buffers[$name] .= $chunk; return strlen($chunk); }]);
    curl_multi_add_handle($multi, $curl);
}
$events = static function (string $buffer): array {
    preg_match_all('/^event: (\w+)\ndata: (.*)$/m', $buffer, $matches, PREG_SET_ORDER);
    return array_map(static fn($m) => [$m[1], json_decode($m[2], true)], $matches);
};
// buffer letti per riferimento: si riempiono durante il ciclo curl_multi
$has = static function (string $name, string $event) use (&$buffers, $events): bool {
    return in_array($event, array_column($events($buffers[$name]), 0), true);
};
$start = time();
$ready = false;
$doneAt = null;
do {
    curl_multi_exec($multi, $running);
    curl_multi_select($multi, 0.5);
    if (!$ready && $has('sender', 'status') && $has('receiver', 'status') && $has('other', 'status')) {
        touch('/push/ready');
        $ready = true;
    }
    if ($doneAt === null && $has('receiver', 'tx') && $has('receiver', 'notify') && $has('sender', 'tx') && $has('sender', 'notify')) {
        // ancora qualche secondo: un evento per l'estraneo arriverebbe nello stesso giro
        $doneAt = time();
    }
} while ($running > 0 && time() - $start < 50 && ($doneAt === null || time() - $doneAt < 3));
curl_multi_close($multi);
check($ready, 'Three streams open with the current network status');

$transfer = null;
for ($i = 0; $i < 30 && $transfer === null; $i++) {
    $transfer = json_decode((string) @file_get_contents('/push/transfer.json'), true);
    if ($transfer === null) sleep(1);
}
check(is_array($transfer) && preg_match('/^0x[0-9a-f]{64}$/', $transfer['hash'] ?? ''), 'Real transfer mined while listening');
$txId = (int) $app->db->createCommand('SELECT id FROM transactions WHERE txhash=:h', [':h' => $transfer['hash']])->queryScalar();
check($txId > 0, 'Transfer indexed into transactions');

$first = $events($buffers['receiver'])[0] ?? null;
check($first !== null && $first[0] === 'status' && $first[1]['ok'] === true && $first[1]['head'] > 0, 'First event: indexer status ok with the chain head');
$txIds = static fn(string $name) => array_map(static fn($e) => $e[1]['id'] ?? null, array_values(array_filter($events($buffers[$name]), static fn($e) => $e[0] === 'tx')));
check(in_array($txId, $txIds('receiver'), true), 'Receiver gets "tx" with the id of the new transaction');
check(in_array($txId, $txIds('sender'), true), 'Sender gets "tx" for the same transaction');
check($has('receiver', 'notify') && $has('sender', 'notify'), 'Sender and receiver get "notify" for their new notifications');
check(!$has('other', 'tx') && !$has('other', 'notify'), 'Unrelated user gets no transaction or notification event');
check(!str_contains(implode('', $buffers), $key) && !str_contains(implode('', $buffers), $transfer['hash']), 'Events carry ids only: no key, no transaction hash');
echo "PASS $checks realtime push checks\n";
