<?php
// Transaction signing (Erc20::signTransaction): canonical RLP, EIP-155 chainId, signature valid for the key.
// Many random keys so that signatures with r or s starting with a zero byte are covered
// (web3p/ethereum-tx 0.4.3 encoded those non-canonically and nodes rejected the transaction).
// No network, no database. Deprecations are errors, as in development.
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
$root = getenv('NAPAY_TEST_APP_ROOT') ?: dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
require $root . '/vendor/yiisoft/yii2/Yii.php';
new yii\console\Application(['id' => 'sign-transaction-test', 'basePath' => $root]);

use kornrunner\Ethereum\Transaction;
use kornrunner\RLP\RLP;
use kornrunner\Secp256k1;
use kornrunner\Signature\Signature;
use Mdanter\Ecc\EccFactory;

$checks = 0;
function check($condition, $label) {
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
}

const CHAIN_ID = 2118;
const SAMPLES = 1000;          // probability of no zero-leading r/s in all samples ~0.04%
const VERIFY_SIGNATURES = 100; // full signature verification is slower

$sign = new ReflectionMethod(app\components\Erc20::class, 'signTransaction');
$rlp = new RLP();
$secp = new Secp256k1();
$generator = EccFactory::getSecgCurves()->generator256k1();
$zeroLeading = 0;

for ($i = 0; $i < SAMPLES; $i++) {
    $key = bin2hex(random_bytes(32));
    $tx = [
        'nonce' => $i % 4,
        'to' => '0x' . bin2hex(random_bytes(20)),
        'gas' => '0x200b20',
        'gasPrice' => '1000',
        'value' => $i % 2 ? '0xDE0B6B3A7640000' : '0',
        'data' => $i % 2 ? '' : '0xa9059cbb' . str_repeat('0', 128),
    ];
    $raw = $sign->invoke(null, $tx, $key, CHAIN_ID);
    $f = $rlp->decode('0x' . $raw); // nonce, gasPrice, gas, to, value, data, v, r, s

    if ($i === 0) {
        check(count($f) === 9, 'legacy transaction has 9 fields');
        check($sign->invoke(null, $tx, $key, CHAIN_ID) === $raw, 'signing is deterministic (RFC 6979)');
    }
    foreach ([0, 1, 2, 4, 6, 7, 8] as $n) {
        if ($f[$n] !== '' && str_starts_with($f[$n], '00')) {
            check(false, "integer field $n canonical (sample $i)");
        }
    }
    if (strlen($f[7]) < 64 || strlen($f[8]) < 64) {
        $zeroLeading++;
    }
    $v = hexdec($f[6]);
    if ($v !== CHAIN_ID * 2 + 35 && $v !== CHAIN_ID * 2 + 36) {
        check(false, "EIP-155 v for chainId " . CHAIN_ID . " (sample $i)");
    }
    if ($f[1] !== '03e8' || $f[0] !== ($tx['nonce'] ? sprintf('%02x', $tx['nonce']) : '')) {
        check(false, "nonce and gasPrice 1000 wei encoded as numbers (sample $i)");
    }

    if ($i < VERIFY_SIGNATURES) {
        $point = $generator->getPrivateKeyFrom(gmp_init($key, 16))->getPublicKey()->getPoint();
        $publicKey = '04' . str_pad(gmp_strval($point->getX(), 16), 64, '0', STR_PAD_LEFT)
            . str_pad(gmp_strval($point->getY(), 16), 64, '0', STR_PAD_LEFT);
        $unsigned = new Transaction($f[0], $f[1], $f[2], $f[3], $f[4], $f[5]);
        $signature = new Signature(gmp_init($f[7], 16), gmp_init($f[8], 16), $v - CHAIN_ID * 2 - 35);
        if (!$secp->verify($unsigned->hash(CHAIN_ID), $signature, $publicKey)) {
            check(false, "signature valid for the signing key (sample $i)");
        }
    }
}
check(true, SAMPLES . ' samples: integer fields canonical, v and values correct');
check(true, VERIFY_SIGNATURES . ' signatures verified against the public key');
check($zeroLeading > 0, "zero-leading r/s case covered ($zeroLeading samples)");

echo 'PASS ' . $checks . " sign transaction checks ($zeroLeading with zero-leading r/s)\n";
