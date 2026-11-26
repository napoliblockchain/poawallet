<?php
// Importo dei trasferimenti TTS dalle ricevute: conversione esadecimale esatta e solo dal log Transfer del token.
error_reporting(E_ALL & ~E_DEPRECATED);
$root = getenv('NAPAY_TEST_APP_ROOT') ?: dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
require $root . '/vendor/yiisoft/yii2/Yii.php';
Yii::setAlias('@app', $root);

use app\components\Erc20;

$checks = 0;
function check($condition, string $label): void {
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
}
function refused(callable $call): bool {
    try { $call(); } catch (InvalidArgumentException $e) { return true; }
    return false;
}

check(Erc20::wei2eth('0x' . str_pad('4d2', 64, '0', STR_PAD_LEFT), 2) === 12.34, 'Transfer data 1234 units = 12.34 TTS');
// il vecchio codice raddoppiava la prima cifra non nulla (0x12c -> 0x112c)
check(Erc20::wei2eth('0x12c', 2) === 3.0 && Erc20::wei2eth('0x5', 2) === 0.05 && Erc20::wei2eth('0x0', 2) === 0.0, 'Small values and zero exact');
check(Erc20::wei2eth('0xDE0B6B3A7640000', 18) === 1.0 && Erc20::wei2eth('0x7b', 0) === 123.0, '18 and 0 decimals');
check(refused(fn() => Erc20::wei2eth('12c', 2)) && refused(fn() => Erc20::wei2eth('0x', 2)) && refused(fn() => Erc20::wei2eth('0xzz', 2))
    && refused(fn() => Erc20::wei2eth('0x' . str_repeat('f', 65), 2)) && refused(fn() => Erc20::wei2eth('0x1', 19)) && refused(fn() => Erc20::wei2eth(null, 2)),
    'Malformed amounts or decimals refused');

$token = '0x' . str_repeat('ab', 20);
$log = static fn(string $address, string $topic, string $data) => (object) ['address' => $address, 'topics' => [$topic, '0x' . str_repeat('0', 64)], 'data' => $data];
$amount = '0x' . str_pad('4d2', 64, '0', STR_PAD_LEFT);
$other = '0x' . str_pad('270f', 64, '0', STR_PAD_LEFT);
$approval = '0x8c5be1e5ebec7d5bd14f71427d1e84f3dd0314c0f7b2291e5b200ac8c7c3b925';
$receipt = (object) ['status' => '0x1', 'logs' => [
    $log('0x' . str_repeat('cd', 20), Erc20::TRANSFER_TOPIC, $other),   // altro contratto
    $log($token, $approval, $other),                                     // altro evento del token
    $log(strtoupper($token), strtoupper(Erc20::TRANSFER_TOPIC), $amount), // Transfer del token (maiuscole indifferenti)
]];
check(Erc20::transferAmount($receipt, $token, 2) === 12.34, 'Amount taken from the token Transfer log, not from other contracts or events');
check(Erc20::transferAmount((object) ['logs' => [$log('0x' . str_repeat('cd', 20), Erc20::TRANSFER_TOPIC, $other)]], $token, 2) === null
    && Erc20::transferAmount((object) ['logs' => []], $token, 2) === null && Erc20::transferAmount((object) [], $token, 2) === null, 'No token Transfer: no amount');
echo "PASS $checks ERC-20 amount checks\n";
