<?php
// Struttura dell'app (layout Tabler, menu, URL puliti) e pagina wallet resa da utente autenticato.
// Stack usa-e-getta di tests/chain/run-disposable.sh (MySQL migrato, chain di sviluppo); dopo erc20-integration.php.
// output trattenuto: la sessione web richiede che non siano ancora partiti header
ob_start();
error_reporting(E_ALL);
// avvisi ed errori diventano eccezioni; le deprecazioni delle librerie (es. bacon-qr-code) restano come in produzione
set_error_handler(static function ($severity, $message, $file, $line) {
    if (in_array($severity, [E_DEPRECATED, E_USER_DEPRECATED], true)) return true;
    throw new ErrorException($message, 0, $severity, $file, $line);
});
define('YII_DEBUG', false);
define('YII_ENV', 'test');
$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
require $root . '/vendor/yiisoft/yii2/Yii.php';

$chainDir = getenv('CHAIN_DATA_DIR') ?: '/chain';
$tts = json_decode(file_get_contents("$chainDir/tts.json"), true);
$rpcUrl = getenv('CHAIN_RPC_URL') ?: $tts['rpc'];
$config = require $root . '/config/web.php';
unset($config['bootstrap']);
$config['components']['request']['hostInfo'] = 'https://wallet.example.invalid';
$config['components']['request']['scriptUrl'] = '/index.php';
$config['components']['request']['scriptFile'] = $root . '/html/index.php';
$config['components']['request']['url'] = '/wallet/index';
$config['components']['session'] = ['class' => yii\web\Session::class, 'savePath' => sys_get_temp_dir()];
$app = new yii\web\Application($config);
$db = $app->db;

$checks = 0;
function check($condition, string $label): void {
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    ++$checks;
    echo "  ok  $label\n";
}

// --- fixture: utente con rete, token, nodo e wallet (indirizzo del detentore dei token di sviluppo)
$userId = 990002;
foreach (['nodes' => 'id_user', 'smart_contract' => 'id_user', 'blockchains' => 'id_user', 'mp_wallets' => 'id_user', 'mp_users' => 'id'] as $table => $column) {
    $db->createCommand()->delete($table, [$column => $userId])->execute();
}
$db->createCommand()->insert('mp_users', ['id' => $userId, 'username' => 'ui-test@example.com', 'password' => 'x', 'first_name' => '<b>Ui</b>',
    'oauth_provider' => 'mail', 'email' => 'ui-test@example.com', 'status_activation_code' => 1])->execute();
$contractTypeId = (int) (new yii\db\Query())->select('id')->from('contract_type')->orderBy('id')->scalar();
$db->createCommand()->insert('blockchains', ['id_user' => $userId, 'denomination' => 'Dev QBFT', 'chain_id' => (string) $tts['chainId'],
    'url' => $rpcUrl, 'symbol' => 'ETH', 'url_block_explorer' => '', 'zerogas' => 1])->execute();
$blockchainId = (int) $db->getLastInsertID();
$db->createCommand()->insert('smart_contract', ['id_user' => $userId, 'id_blockchain' => $blockchainId, 'id_contract_type' => $contractTypeId,
    'denomination' => 'TTS dev', 'smart_contract_address' => $tts['address'], 'decimals' => $tts['decimals'], 'symbol' => $tts['symbol']])->execute();
$contractId = (int) $db->getLastInsertID();
$db->createCommand()->insert('nodes', ['id_user' => $userId, 'id_blockchain' => $blockchainId, 'id_smart_contract' => $contractId])->execute();
$db->createCommand()->insert('mp_wallets', ['id_user' => $userId, 'wallet_address' => $tts['holder'], 'blocknumber' => '0x0'])->execute();
$app->user->setIdentity(app\models\Users::findOne($userId));

// --- URL puliti
check($app->urlManager->createUrl(['/wallet/index']) === '/wallet/index' && $app->urlManager->createUrl(['/site/activate', 'id' => 'a', 'sign' => 'b']) === '/site/activate?id=a&sign=b',
    'Clean URLs without index.php');

// --- pagina wallet completa (saldo token formattato, layout)
$app->requestedRoute = 'wallet/index';
$response = $app->runAction('wallet/index');
$html = is_string($response) ? $response : (string) $app->response->content;
check(str_contains($html, 'id="total-balance"') && preg_match('/id="total-balance">\s*[0-9.,]+\s*</', $html) === 1, 'Wallet page renders the token balance (asDecimal with options)');
check(str_contains($html, '/bundles/tabler/css/tabler.min.css') && str_contains($html, '/bundles/fontawesome/css/all.min.css') && str_contains($html, '/bundles/tabler/js/bootstrap.bundle.min.js'),
    'Local Tabler, icons and Bootstrap 5 JavaScript');
check(!preg_match('~fonts\.googleapis|cdnjs|global\.style\.css|bootstrap4~', $html), 'No CDN, template CSS or Bootstrap 4');
foreach (['/wallet/index', '/send/index', '/receive/index', '/transactions/index'] as $link) {
    check(str_contains($html, 'href="' . $link . '"'), 'Main menu links to ' . $link);
}
check(preg_match('~<a class="nav-link active" href="/wallet/index" aria-current="page">~', $html) === 1, 'Current section marked in the menu');
check(str_contains($html, 'id="wallet-more"') && str_contains($html, 'data-bs-toggle="offcanvas"') && str_contains($html, 'href="/mailbox/inbox"'), 'More panel with the other sections');
check(!str_contains($html, 'href="/logs/index"') && !str_contains($html, 'href="/profiles/index"'), 'Administration links hidden from a normal user');
check(preg_match('~<form action="/site/logout" method="post">\s*<input type="hidden" name="_csrf"~', $html) === 1, 'Logout is a POST with CSRF token');
check(!str_contains($html, '<b>Ui</b>'), 'User name never printed as HTML');
foreach (['id="quantity_circle"', 'id="quantity_notify"', 'id="notification-list"', 'class="pulse-button', 'class="header-message', 'id="snackbar"', 'id="reload"', 'data-search="open"', 'data-search="close"'] as $hook) {
    check(str_contains($html, $hook), 'Script hook kept: ' . $hook);
}
check(str_contains($html, '/js/wallet-sync.js') && !str_contains($html, 'ws-websocket.js') && !str_contains($html, 'wss_server') && !str_contains($html, 'WebSocketServerAddress'),
    'Polling synchronization, no WebSocket client or blocking alert');
check(!str_contains($html, 'data-toggle=') && !str_contains($html, 'data-dismiss="modal"') && !str_contains($html, 'class="close"'), 'No Bootstrap 4 data attributes in the layout');

// --- pagine del wallet: transazione con dati malevoli (memo dalla chain), sempre codificati
$evil = '<img src=x onerror=alert(1)>';
$db->createCommand()->insert('transactions', ['id_user' => $userId, 'id_smart_contract' => $contractId, 'type' => 'token', 'status' => 'complete',
    'token_price' => 12.34, 'token_received' => 12.34, 'invoice_timestamp' => time(), 'expiration_timestamp' => time() + 900,
    'from_address' => '0x' . str_repeat('ab', 20), 'to_address' => $tts['holder'], 'blocknumber' => '0x10', 'txhash' => '0x' . str_repeat('cd', 32), 'message' => $evil])->execute();
$txId = (int) $db->getLastInsertID();
$render = static function (string $route, array $params = []) use ($app): string {
    $app->requestedRoute = $route;
    $app->response->clear();
    $result = $app->runAction($route, $params);
    return is_string($result) ? $result : (string) $app->response->content;
};
$wallet = $render('wallet/index');
check(str_contains($wallet, 'wallet-hero') && str_contains($wallet, 'wallet-actions') && str_contains($wallet, 'class="wallet-tx wallet-tx-in"'), 'Wallet: balance card, quick actions, incoming transaction row');
check(!str_contains($wallet, $evil) && str_contains($wallet, htmlspecialchars($evil, ENT_QUOTES)), 'Transaction memo encoded in the row');
check(str_contains($wallet, 'href="/transactions/view?id='), 'Transaction row links to the clean detail URL');
$send = $render('send/index');
check(str_contains($send, 'id="send-form"') && str_contains($send, 'id="activate-camera-btn"') && str_contains($send, 'id="sellOrder"') && str_contains($send, 'class="modal fade" id="cameraPopup"'),
    'Send: form, camera button, confirmation and camera dialogs');
check(str_contains($send, 'import QrScanner from "/js/qrcodescanner/') && !str_contains($send, '"./js/'), 'QR scanner loaded from absolute paths');
foreach (['sendform-to', 'sendform-amount', 'sendform-memo', 'sendform-from', 'sendform-balance', 'error-summary', 'amount-to-send-gas'] as $id) {
    check(str_contains($send, 'id="' . $id . '"'), 'Send script hook kept: ' . $id);
}
$receive = $render('receive/index');
check(str_contains($receive, '<svg') && !str_contains($receive, '<desc>') && str_contains($receive, 'id="inputcopyWalletAddress"') && str_contains($receive, 'copyonClickAddress')
    && str_contains($receive, htmlspecialchars($tts['holder'])), 'Receive: QR, address and copy button');
$list = $render('transactions/index');
check(str_contains($list, 'table-96') && str_contains($list, 'wallet-tx') && !str_contains($list, $evil), 'Transactions: list with encoded rows');
$detail = $render('transactions/view', ['id' => app\components\WebApp::encrypt($txId)]);
check(str_contains($detail, 'trans-get') && str_contains($detail, '12.34') && !str_contains($detail, $evil) && str_contains($detail, htmlspecialchars($evil, ENT_QUOTES)),
    'Transaction detail: amount, refresh button, encoded memo');
$db->createCommand()->delete('transactions', ['id' => $txId])->execute();

foreach (['mp_wallets' => 'id_user', 'nodes' => 'id_user', 'smart_contract' => 'id_user', 'blockchains' => 'id_user', 'mp_users' => 'id'] as $table => $column) {
    $db->createCommand()->delete($table, [$column => $userId])->execute();
}
echo "PASS $checks UI layout checks\n";
