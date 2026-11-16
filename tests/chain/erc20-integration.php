<?php
// Integration test of app\components\Erc20 against the development chain (compose profile "chain").
// Needs: migrated MySQL (DB_* env), chain data volume mounted read-only at CHAIN_DATA_DIR (tts.json, key).
// Every result is cross-checked with independent JSON-RPC calls.
// Run inside the wallet container: php tests/chain/erc20-integration.php
// come in sviluppo (php.ini-development): anche le deprecazioni diventano errori.
// CHAIN_IT_STRICT=0 per eseguirlo come in produzione (deprecazioni ignorate).
if (getenv('CHAIN_IT_STRICT') !== '0') {
    error_reporting(E_ALL);
    set_error_handler(function ($severity, $message, $file, $line) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    });
} else {
    error_reporting(E_ALL & ~E_DEPRECATED);
}
// debug off: error output must never dump environment variables (secrets)
define('YII_DEBUG', false);
define('YII_ENV', 'test');
$root = getenv('NAPAY_TEST_APP_ROOT') ?: dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
require $root . '/vendor/yiisoft/yii2/Yii.php';

$chainDir = getenv('CHAIN_DATA_DIR') ?: '/chain';
$tts = json_decode(file_get_contents("$chainDir/tts.json"), true);
$holderKey = preg_replace('/^0x/', '', trim(file_get_contents("$chainDir/key")));
$rpcUrl = getenv('CHAIN_RPC_URL') ?: $tts['rpc'];

$config = require $root . '/config/web.php';
unset($config['bootstrap']);
$config['components']['user']['enableSession'] = false;
$app = new yii\web\Application($config);
$db = $app->db;

$checks = 0;
function check($condition, $label) {
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
    echo "  ok  $label\n";
}
function rpc($method, $params = []) {
    global $rpcUrl;
    $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => 'Content-Type: application/json',
        'content' => json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params])]]);
    $res = json_decode(file_get_contents($rpcUrl, false, $ctx), true);
    if (isset($res['error'])) throw new RuntimeException("rpc $method: " . $res['error']['message']);
    return $res['result'];
}
function tokenBalanceRpc($token, $address) {
    $data = '0x70a08231' . str_pad(substr(strtolower($address), 2), 64, '0', STR_PAD_LEFT);
    return gmp_strval(gmp_init(rpc('eth_call', [['to' => $token, 'data' => $data], 'latest']), 16));
}
function waitReceipt($hash, $erc20) {
    for ($i = 0; $i < 30; $i++) {
        $receipt = $erc20->getReceipt($hash);
        if ($receipt !== null) return $receipt;
        sleep(1);
    }
    throw new RuntimeException("receipt not found for $hash");
}

// --- fixture: utente e wallet (rete e token dalle variabili d'ambiente) ---
$holder = $tts['holder'];
$userId = 990001;
$db->createCommand()->delete('mp_wallets', ['id_user' => $userId])->execute();
$db->createCommand()->delete('mp_users', ['id' => $userId])->execute();
$db->createCommand()->insert('mp_users', ['id' => $userId, 'username' => 'chain-it@example.com', 'password' => 'x',
    'oauth_provider' => 'test', 'email' => 'chain-it@example.com', 'status_activation_code' => 1])->execute();
$db->createCommand()->insert('mp_wallets', ['id_user' => $userId, 'wallet_address' => $holder, 'blocknumber' => '0x0'])->execute();
$app->user->setIdentity(app\models\Users::findOne($userId));

$erc20 = new app\components\Erc20($userId);
$recipient = '0x' . bin2hex(random_bytes(20));

echo "Chain {$tts['chainId']} @ $rpcUrl, TTS {$tts['address']}\n";

// --- lettura ---
$block = $erc20->getBlockInfo();
check(hexdec($block->number) > 0 && hexdec($block->number) <= hexdec(rpc('eth_blockNumber')), 'getBlockInfo: latest block');

$nonce = $erc20->getNonce($holder);
check(is_int($nonce) && $nonce === hexdec(rpc('eth_getTransactionCount', [$holder, 'latest'])), 'getNonce matches eth_getTransactionCount');

check((string) $erc20->tokenBalance($holder) === tokenBalanceRpc($tts['address'], $holder), 'tokenBalance matches balanceOf via eth_call');

check($erc20->gasBalance($holder) > 0, 'gasBalance of funded holder > 0');

$gasLimit = $erc20->getGasLimit($recipient, $holder, '12.34');
check(is_numeric($gasLimit) && (int) $gasLimit > 21000, 'getGasLimit: estimateGas for transfer');

// --- invio token firmato lato server ---
$before = tokenBalanceRpc($tts['address'], $holder);
$sent = $erc20->sendToken([
    'nonce' => $erc20->getNonce($holder),
    'from' => $holder,
    'contractAddress' => $tts['address'],
    'toAccount' => $recipient,
    'amount' => '12.34',
    'gas' => '0x200b20',
    'gasPrice' => '1000',
    'value' => '0',
    'chainId' => $tts['chainId'],
    'decryptedSign' => $holderKey,
]);
check(($sent['success'] ?? 0) === 1 && preg_match('/^0x[0-9a-f]{64}$/', (string) $sent['tx']), 'sendToken: raw transaction accepted');
$receipt = waitReceipt($sent['tx'], $erc20);
check($receipt->status === '0x1', 'sendToken: receipt status success');
check(hexdec(rpc('eth_getTransactionByHash', [$sent['tx']])['gasPrice']) === 1000, 'sendToken: gasPrice 1000 wei on chain');
check(tokenBalanceRpc($tts['address'], $recipient) === '1234', 'recipient received 12.34 TTS (1234 units)');
check(app\components\Erc20::transferAmount($receipt, $tts['address'], 2) === 12.34, 'Real receipt: 12.34 TTS read from the token Transfer log');
check(gmp_strval(gmp_sub($before, tokenBalanceRpc($tts['address'], $holder))) === '1234', 'holder balance decreased by 1234 units');
check((string) $erc20->tokenBalance($recipient) === '1234', 'tokenBalance of recipient via Erc20');
check(strlen(rpc('eth_getTransactionByHash', [$sent['tx']])['input']) === 2 + 68 * 2, 'sendToken without reference: plain transfer calldata');

// --- riferimento di pagamento Napay (SIP-021 fase 3) accodato ai dati di transfer ---
$reference = bin2hex(random_bytes(16));
check(app\components\Erc20::napayReference('napay:' . $reference) === $reference, 'napayReference: Napay memo recognized');
foreach (['chain-it', 'napay:' . strtoupper($reference), 'napay:' . $reference . "\n", 'napay:' . substr($reference, 1), null] as $memo) {
    check(app\components\Erc20::napayReference($memo) === null, 'napayReference: ignores ' . json_encode($memo));
}
$withReference = $erc20->sendToken(['nonce' => $erc20->getNonce($holder), 'from' => $holder, 'contractAddress' => $tts['address'], 'toAccount' => $recipient,
    'amount' => '0.50', 'gas' => '0x200b20', 'gasPrice' => '1000', 'value' => '0', 'chainId' => $tts['chainId'], 'decryptedSign' => $holderKey, 'reference' => $reference]);
check(($withReference['success'] ?? 0) === 1 && waitReceipt($withReference['tx'], $erc20)->status === '0x1', 'sendToken with reference: mined with success');
$input = rpc('eth_getTransactionByHash', [$withReference['tx']])['input'];
check(strlen($input) === 2 + 84 * 2 && str_ends_with($input, $reference) && tokenBalanceRpc($tts['address'], $recipient) === '1284', 'sendToken with reference: calldata = transfer + 16-byte reference, amount unchanged');
try {
    $erc20->sendToken(['nonce' => 0, 'from' => $holder, 'contractAddress' => $tts['address'], 'toAccount' => $recipient, 'amount' => '0.01',
        'gas' => '0x200b20', 'gasPrice' => '1000', 'value' => '0', 'chainId' => $tts['chainId'], 'decryptedSign' => $holderKey, 'reference' => 'zz']);
    check(false, 'sendToken rejects a malformed reference before signing');
} catch (InvalidArgumentException $e) {
    check(true, 'sendToken rejects a malformed reference before signing');
}

// --- invio tramite SendController: password legata all'utente, formato precedente, password di altri ---
$password = 'chain-it-password';
$prvKey = Nullix\JsAesPhp\JsAesPhp::encrypt($holderKey, $password);
// chiave come la salva il browser (CryptoJS, procedura guidata e ripristino) e come la cifra js-aes-php
check(app\components\WalletKeyCipher::decrypt(app\components\WalletKeyCipher::encryptCryptoJs($holderKey, $password), $password) === $holderKey
    && app\components\WalletKeyCipher::decrypt($prvKey, $password) === $holderKey, 'Wallet key decrypted in browser (CryptoJS) and js-aes-php formats');
check(app\components\WalletKeyCipher::decrypt(app\components\WalletKeyCipher::encryptCryptoJs($holderKey, $password), 'wrong') === null
    && app\components\WalletKeyCipher::decrypt('{"ct":"x","iv":"zz","s":"00"}', $password) === null && app\components\WalletKeyCipher::decrypt('nonsense', $password) === null,
    'Wrong password or malformed key: null, no exception');
$beforeSend = tokenBalanceRpc($tts['address'], $recipient);
$send = function ($prvPas, $memo = 'chain-it', $key = null) use ($app, $holder, $recipient, $prvKey) {
    $_POST = ['from' => $holder, 'to' => $recipient, 'amount' => '1.00', 'memo' => $memo, 'prv_key' => $key ?? $prvKey, 'prv_pas' => $prvPas];
    $app->request->setBodyParams($_POST);
    $controller = new app\controllers\SendController('send', $app);
    $app->controller = $controller;
    return $controller->runAction('generate-transaction');
};
foreach (['bound' => app\components\WebApp::encryptForUser($password, $userId), 'legacy' => app\components\WebApp::encrypt($password)] as $kind => $prvPas) {
    $res = $send($prvPas);
    check(isset($res['id']) && $res['status'] === 'new', "send/generate-transaction accepted ($kind password)");
    $upgraded = $res['prv_pas_upgraded'];
    check($kind === 'bound' ? $upgraded === null
        : app\components\WebApp::decryptForUser($upgraded, $userId)['value'] === $password, "prv_pas upgrade returned only for legacy ($kind)");
    $saved = app\models\Transactions::findOne(app\components\WebApp::decrypt($res['id']));
    check(waitReceipt($saved->txhash, $erc20)->status === '0x1', "send/generate-transaction mined ($kind)");
}
// invio con la chiave nel formato del browser: era il caso rotto ("Invalid encryptedValue")
$res = $send(app\components\WebApp::encryptForUser($password, $userId), 'chain-it', app\components\WalletKeyCipher::encryptCryptoJs($holderKey, $password));
$saved = app\models\Transactions::findOne(app\components\WebApp::decrypt($res['id']));
check(waitReceipt($saved->txhash, $erc20)->status === '0x1', 'send/generate-transaction mined with the key saved by the browser (CryptoJS)');
check(gmp_strval(gmp_sub(tokenBalanceRpc($tts['address'], $recipient), $beforeSend)) === '300', 'recipient received 3 x 1.00 TTS through the controller');
try {
    $send(app\components\WebApp::encryptForUser($password, $userId + 1));
    check(false, 'password bound to another user must be rejected');
} catch (yii\web\HttpException $e) {
    check($e->statusCode === 403, 'password bound to another user rejected (403)');
}
$controllerReference = bin2hex(random_bytes(16));
$res = $send(app\components\WebApp::encryptForUser($password, $userId), 'napay:' . $controllerReference);
$saved = app\models\Transactions::findOne(app\components\WebApp::decrypt($res['id']));
check(waitReceipt($saved->txhash, $erc20)->status === '0x1' && str_ends_with(rpc('eth_getTransactionByHash', [$saved->txhash])['input'], $controllerReference)
    && $saved->message === 'napay:' . $controllerReference, 'send/generate-transaction: Napay QR memo appended as payment reference');
$db->createCommand()->delete('mp_notifications_readers', ['id_user' => $userId])->execute();
$db->createCommand()->delete('transactions', ['id_user' => $userId])->execute();

// --- faucet gas (rete zerogas) ---
Yii::$app->params['sealer_account_3'] = $holder;
Yii::$app->params['sealer_prvkey_3'] = $holderKey;
check($erc20->gasBalance($recipient) == 0, 'recipient starts without gas');
$erc20->loadGas($recipient);
for ($i = 0; $i < 30 && $erc20->gasBalance($recipient) < 1; $i++) sleep(1);
check($erc20->gasBalance($recipient) >= 1, 'loadGas: faucet sent 1 ETH to recipient');

// --- cleanup fixture ---
$db->createCommand()->delete('mp_wallets', ['id_user' => $userId])->execute();
$db->createCommand()->delete('mp_users', ['id' => $userId])->execute();

echo 'PASS ' . $checks . " erc20 chain integration checks\n";
