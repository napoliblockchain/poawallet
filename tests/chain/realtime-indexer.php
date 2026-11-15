<?php
// Indicizzatore wallet-realtime (SIP-029): dati di partenza e verifica dopo due giri (--once) del servizio Go.
// Fasi: setup | verify, entrambe con il JSON dei trasferimenti da stdin. Stack usa-e-getta di run-disposable.sh.
if (PHP_SAPI !== 'cli' || !in_array($argv[1] ?? '', ['setup', 'verify'], true)) exit(1);
error_reporting(E_ALL & ~E_DEPRECATED);
define('YII_DEBUG', false);
define('YII_ENV', 'test');
$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
require $root . '/vendor/yiisoft/yii2/Yii.php';
$config = require $root . '/config/web.php';
unset($config['bootstrap']);
$app = new yii\web\Application($config);
$db = $app->db;
$t = json_decode(stream_get_contents(STDIN), true, 8, JSON_THROW_ON_ERROR);

$checks = 0;
function check($condition, string $label): void {
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    ++$checks;
    echo "  ok  $label\n";
}
$sender = 990101;
$receiver = 990102;

if ($argv[1] === 'setup') {
    $contractType = (int) (new yii\db\Query())->select('id')->from('contract_type')->orderBy('id')->scalar();
    foreach ([$sender => $t['holder'], $receiver => $t['receiver']] as $user => $address) {
        $db->createCommand()->insert('mp_users', ['id' => $user, 'username' => "rt$user@example.com", 'password' => 'x', 'oauth_provider' => 'test',
            'email' => "rt$user@example.com", 'status_activation_code' => 1])->execute();
        $db->createCommand()->insert('blockchains', ['id_user' => $user, 'denomination' => 'Dev QBFT', 'chain_id' => '2118',
            'url' => 'http://attacker.example.invalid', 'symbol' => 'ETH', 'url_block_explorer' => '', 'zerogas' => 1])->execute();
        $chain = (int) $db->getLastInsertID();
        $db->createCommand()->insert('smart_contract', ['id_user' => $user, 'id_blockchain' => $chain, 'id_contract_type' => $contractType,
            'denomination' => 'TTS dev', 'smart_contract_address' => $t['token'], 'decimals' => 2, 'symbol' => 'TTS'])->execute();
        $db->createCommand()->insert('nodes', ['id_user' => $user, 'id_blockchain' => $chain, 'id_smart_contract' => (int) $db->getLastInsertID()])->execute();
        // indirizzi salvati come li scrive il wallet (minuscolo o con checksum): confronto senza maiuscole
        $db->createCommand()->insert('mp_wallets', ['id_user' => $user, 'wallet_address' => $user === $sender ? strtolower($address) : $address, 'blocknumber' => '0x0'])->execute();
    }
    $contract = (int) $db->createCommand('SELECT id FROM smart_contract WHERE id_user=:u', [':u' => $sender])->queryScalar();
    foreach (['sentByWallet' => [$t['receiver'], 12.34], 'failed' => [$t['receiver'], 99999999999]] as $key => [$to, $amount]) {
        $db->createCommand()->insert('transactions', ['id_user' => $sender, 'type' => 'token', 'id_smart_contract' => $contract, 'status' => 'new',
            'token_price' => $amount, 'token_received' => 0, 'invoice_timestamp' => time(), 'expiration_timestamp' => time() + 900,
            'from_address' => $t['holder'], 'to_address' => $to, 'blocknumber' => '0x0', 'txhash' => $t[$key], 'message' => 'memo'])->execute();
    }
    $db->createCommand()->insert('chain_index_cursors', ['chain_id' => '2118', 'contract' => strtolower($t['token']), 'last_block' => (int) $t['startBlock'], 'updated_at' => time()])->execute();
    echo "PASS realtime indexer fixture\n";
    exit(0);
}

$row = fn(string $hash) => $db->createCommand('SELECT * FROM transactions WHERE txhash=:h', [':h' => $hash])->queryAll();
$sent = $row($t['sentByWallet']);
check(count($sent) === 1 && $sent[0]['status'] === 'complete' && (float) $sent[0]['token_received'] === 12.34 && (int) $sent[0]['id_user'] === $sender
    && $sent[0]['message'] === 'memo' && str_starts_with($sent[0]['blocknumber'], '0x'), 'Transfer sent by the wallet completed in place, memo kept');
$in = $row($t['incoming']);
check(count($in) === 1 && (int) $in[0]['id_user'] === $receiver && $in[0]['status'] === 'complete' && (float) $in[0]['token_price'] === 5.0
    && strtolower($in[0]['to_address']) === strtolower($t['receiver']), 'Incoming transfer recorded for the receiver');
$out = $row($t['toOutsider']);
check(count($out) === 1 && (int) $out[0]['id_user'] === $sender, 'Transfer to an unregistered address recorded for the sender');
$failed = $row($t['failed']);
check(count($failed) === 1 && $failed[0]['status'] === 'failed', 'Reverted transfer marked failed from its receipt');
$notes = fn(int $user) => $db->createCommand('SELECT n.description FROM mp_notifications n JOIN mp_notifications_readers r ON r.id_notification=n.id
    WHERE r.id_user=:u AND r.alreadyread=0 ORDER BY n.id', [':u' => $user])->queryColumn();
check(count($notes($receiver)) === 2 && in_array('You received a new transaction of 5 TTS.', $notes($receiver), true)
    && in_array('You received a new transaction of 12.34 TTS.', $notes($receiver), true), 'Receiver notified once per incoming transfer');
check(count($notes($sender)) === 3 && in_array('A transaction you sent of 1 TTS has been completed.', $notes($sender), true), 'Sender notified once per completed transfer');
$cursor = $db->createCommand('SELECT last_block FROM chain_index_cursors WHERE chain_id=:c AND contract=:a', [':c' => '2118', ':a' => strtolower($t['token'])])->queryScalar();
check($cursor !== false && (int) $cursor > 0, 'Cursor saved for the token');
check((int) $db->createCommand('SELECT COUNT(*) FROM transactions WHERE txhash IN (:a,:b,:c,:d)', [':a' => $t['sentByWallet'], ':b' => $t['incoming'], ':c' => $t['toOutsider'], ':d' => $t['failed']])->queryScalar() === 4,
    'Second indexer run created no duplicates');
echo "PASS $checks realtime indexer checks\n";
