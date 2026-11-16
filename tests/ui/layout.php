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

// --- fixture: utente con wallet (indirizzo del detentore dei token di sviluppo); rete e token dalle variabili
$userId = 990002;
foreach (['mp_wallets' => 'id_user', 'mp_users' => 'id'] as $table => $column) {
    $db->createCommand()->delete($table, [$column => $userId])->execute();
}
$db->createCommand()->insert('mp_users', ['id' => $userId, 'username' => 'ui-test@example.com', 'password' => 'x', 'first_name' => '<b>Ui</b>',
    'oauth_provider' => 'mail', 'email' => 'ui-test@example.com', 'status_activation_code' => 1])->execute();
$db->createCommand()->insert('mp_wallets', ['id_user' => $userId, 'wallet_address' => $tts['holder'], 'blocknumber' => '0x0'])->execute();
$app->user->setIdentity(app\models\Users::findOne($userId));

// --- SIP-032: niente più righe per utente di rete/token né il vecchio indicizzatore PHP
$gone = true;
foreach (['nodes', 'smart_contract', 'blockchains', 'contract_type', 'standard_blockchain_values', 'standard_smart_contract_values', 'ethtxs', 'ethtxs_status'] as $table) {
    $gone = $gone && $db->getTableSchema($table, true) === null;
}
check($gone && $db->getTableSchema('transactions', true)->getColumn('id_smart_contract') === null, 'Database: per-user network tables, ethtxs and transactions.id_smart_contract dropped');

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
check(str_contains($html, 'id="wallet-more"') && str_contains($html, 'data-bs-toggle="offcanvas"') && str_contains($html, 'href="/messages/index"')
    && str_contains($html, 'href="/network/index"') && !str_contains($html, '/nodes/update') && !str_contains($html, '/smartcontracts/') && !str_contains($html, '/blockchains/'),
    'More panel with the other sections; only Network info for the chain');
check(substr_count($html, 'data-bs-toggle="offcanvas"') === 1 && !str_contains($html, 'fa-bars'), 'One menu only: no top-right menu button');
check(!str_contains($html, 'href="/logs/index"') && !str_contains($html, 'href="/profiles/index"'), 'Administration links hidden from a normal user');
check(preg_match('~<form action="/site/logout" method="post">\s*<input type="hidden" name="_csrf"~', $html) === 1, 'Logout is a POST with CSRF token');
check(!str_contains($html, '<b>Ui</b>'), 'User name never printed as HTML');
foreach (['id="quantity_circle"', 'id="quantity_notify"', 'id="notification-list"', 'class="pulse-button', 'class="header-message', 'id="snackbar"', 'id="reload"', 'data-search="open"', 'data-search="close"'] as $hook) {
    check(str_contains($html, $hook), 'Script hook kept: ' . $hook);
}
check(str_contains($html, '/js/wallet-sync.js') && !str_contains($html, 'ws-websocket.js') && !str_contains($html, 'wss_server') && !str_contains($html, 'WebSocketServerAddress'),
    'Polling synchronization, no WebSocket client or blocking alert');
check(!str_contains($html, 'ws-latest.js') && !str_contains($html, 'latestWorker'), 'No browser-driven chain scan (indexer only, no duplicate notifications)');
check(!str_contains($html, 'data-toggle=') && !str_contains($html, 'data-dismiss="modal"') && !str_contains($html, 'class="close"'), 'No Bootstrap 4 data attributes in the layout');

$render = static function (string $route, array $params = []) use ($app): string {
    $app->requestedRoute = $route;
    $app->response->clear();
    $result = $app->runAction($route, $params);
    return is_string($result) ? $result : (string) $app->response->content;
};
// --- profilo senza transazioni completate (somme nulle) e pagine di impostazione
$profile = $render('users/view', ['id' => app\components\WebApp::encrypt($userId)]);
check(str_contains($profile, 'pincodeslider') && str_contains($profile, 'masterseedSlider') && str_contains($profile, 'js-push-btn-modal') && str_contains($profile, 'id="pinNewModal"'),
    'Profile without transactions renders, switches and PIN dialogs present');
check(!str_contains($profile, 'anonymous.png') && str_contains($profile, 'fa-user'), 'Profile shows the user icon, no picture');
foreach (['network/index' => 'network-block-number', 'messages/index' => 'notifications-form'] as $route => $marker) {
    $page = $render($route);
    check(str_contains($page, $marker) && !str_contains($page, 'ref-card'), 'Settings page restyled: ' . $route);
}

// --- collegamenti morti tolti: nessuna rotta verso controller rimossi
// SIP-032: rete e token dalle variabili d'ambiente, niente pagine di gestione
foreach (['spawn/index', 'mailbox/inbox', 'settings/nodes/update', 'settings/blockchains/index', 'settings/tokens/index', 'settings/index', 'nodes/update',
    'smartcontracts/index', 'blockchains/index', 'standard-blockchain-values/index', 'standard-smart-contract-values/index'] as $deadRoute) {
    try { $app->createController($deadRoute) === false ? check(true, 'Removed route: ' . $deadRoute) : check(false, 'Removed route: ' . $deadRoute); }
    catch (yii\base\InvalidRouteException $e) { check(true, 'Removed route: ' . $deadRoute); }
}
check(!method_exists(app\controllers\WalletController::class, 'actionRescan'), 'Removed action: wallet/rescan (blockchain reset)');
$network = $render('network/index');
check(str_contains($network, 'data-token-address') && !str_contains($network, 'http://besu'), 'Network info shows chain id and token contract, never the internal RPC URL');
check(str_contains($profile, 'pin-seed-numpad') && !str_contains($profile, 'masterSeedPassword') && str_contains($profile, "'crypted_pin': storedPin.pin")
    && preg_match('~id="masterSeedModal"[^>]*>\s*<div class="modal-dialog modal-fullscreen">~', $profile) === 1, 'Master seed asks the PIN on the full-screen keypad, no hidden wallet password');
foreach (['pinNewModal', 'pinVerifyModal', 'pinRemoveModal'] as $pinModal) {
    check(preg_match('~id="' . $pinModal . '"[^>]*>\s*<div class="modal-dialog modal-fullscreen">~', $profile) === 1, 'Full-screen PIN dialog: ' . $pinModal);
}
check(str_contains($profile, "getOrCreateInstance(document.getElementById('pinNewModal'), {backdrop: 'static', keyboard: false}).show()"), 'PIN dialog opened with Bootstrap 5 show()');

// --- procedura guidata (layout dedicato, quattro passi senza il vecchio widget Bootstrap 3)
$wizard = $render('wizard/index');
check(substr_count($wizard, 'data-wizard-step=') === 4 && str_contains($wizard, 'id="seed-submit"') && str_contains($wizard, 'id="form-logout"')
    && !str_contains($wizard, 'glyphicon') && !str_contains($wizard, 'wizardwidget'), 'Wizard: four steps, new keys and logout, no legacy widget');
check(!str_contains($wizard, '<b>Ui</b>'), 'Wizard greeting encodes the user name');

// --- pagine del wallet: transazione con dati malevoli (memo dalla chain), sempre codificati
$evil = '<img src=x onerror=alert(1)>';
$db->createCommand()->insert('transactions', ['id_user' => $userId, 'type' => 'token', 'status' => 'complete',
    'token_price' => 12.34, 'token_received' => 12.34, 'invoice_timestamp' => time(), 'expiration_timestamp' => time() + 900,
    'from_address' => '0x' . str_repeat('ab', 20), 'to_address' => $tts['holder'], 'blocknumber' => '0x10', 'txhash' => '0x' . str_repeat('cd', 32), 'message' => $evil])->execute();
$txId = (int) $db->getLastInsertID();
$wallet = $render('wallet/index');
check(str_contains($wallet, 'wallet-hero') && str_contains($wallet, 'wallet-actions') && str_contains($wallet, 'class="wallet-tx wallet-tx-in"'), 'Wallet: balance card, quick actions, incoming transaction row');
check(preg_match('~id="pinRequestModal"[^>]*>\s*<div class="modal-dialog modal-fullscreen">~', $wallet) === 1
    && !preg_match('~id="pinRequestModal"[\s\S]*?btn-close[\s\S]*?id="pinRequestButton"~', $wallet), 'PIN lock screen is full screen with no way to close it');
check(!str_contains($wallet, $evil) && str_contains($wallet, htmlspecialchars($evil, ENT_QUOTES)), 'Transaction memo encoded in the row');
check(str_contains($wallet, 'href="/transactions/view?id='), 'Transaction row links to the clean detail URL');
check(!str_contains($wallet, 'From: 0x' . str_repeat('ab', 20)) && str_contains($wallet, 'title="0x' . str_repeat('ab', 20) . '"') && str_contains($wallet, 'From: 0xababababab…ababababab'), 'Counterpart address shortened in the row, full in the title');
$send = $render('send/index');
check(str_contains($send, 'id="send-form"') && str_contains($send, 'id="activate-camera-btn"') && str_contains($send, 'id="sellOrder"') && str_contains($send, 'class="modal fade" id="cameraPopup"'),
    'Send: form, camera button, confirmation and camera dialogs');
check(str_contains($send, 'import QrScanner from "/js/qrcodescanner/') && !str_contains($send, '"./js/'), 'QR scanner loaded from absolute paths');
check(str_contains($send, 'window.applyPaymentRequest = function') && str_contains($send, 'window.applyPaymentRequest(result)'), 'Scanned or pasted payment request filled by the same parser');
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

foreach (['mp_wallets' => 'id_user', 'mp_users' => 'id'] as $table => $column) {
    $db->createCommand()->delete($table, [$column => $userId])->execute();
}
echo "PASS $checks UI layout checks\n";
