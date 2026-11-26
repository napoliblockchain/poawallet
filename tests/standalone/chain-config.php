<?php
// SIP-032: rete e token del wallet solo da variabili d'ambiente, validate; forma compatibile con Erc20 e viste.
error_reporting(E_ALL & ~E_DEPRECATED);
$root = getenv('NAPAY_TEST_APP_ROOT') ?: dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
require $root . '/vendor/yiisoft/yii2/Yii.php';
Yii::setAlias('@app', $root);

use app\components\ChainConfig;

$checks = 0;
function check($condition, string $label): void {
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
}
function load(array $env) {
    foreach (['CHAIN_RPC_URL', 'CHAIN_ID', 'CHAIN_NAME', 'CHAIN_SYMBOL', 'CHAIN_EXPLORER_URL', 'CHAIN_ZERO_GAS', 'TTS_TOKEN_ADDRESS', 'TTS_TOKEN_NAME', 'TTS_TOKEN_SYMBOL', 'TTS_TOKEN_DECIMALS'] as $name) {
        putenv(isset($env[$name]) ? "$name=" . $env[$name] : $name);
    }
    (new ReflectionProperty(ChainConfig::class, 'config'))->setValue(null, null);
    try { return ChainConfig::get(); } catch (yii\base\InvalidConfigException $e) { return null; }
}
$valid = ['CHAIN_RPC_URL' => 'http://besu:8545', 'CHAIN_ID' => '2118', 'TTS_TOKEN_ADDRESS' => '0x' . str_repeat('ab', 20)];
foreach ([
    'no RPC URL' => ['CHAIN_RPC_URL' => ''], 'RPC URL not http(s)' => ['CHAIN_RPC_URL' => 'file:///etc/passwd'],
    'no chain id' => ['CHAIN_ID' => ''], 'chain id not numeric' => ['CHAIN_ID' => '2118x'],
    'no token' => ['TTS_TOKEN_ADDRESS' => ''], 'short token' => ['TTS_TOKEN_ADDRESS' => '0x1234'],
    'explorer not http(s)' => ['CHAIN_EXPLORER_URL' => 'javascript:alert(1)'],
] as $label => $override) {
    check(load(array_merge($valid, $override)) === null, 'Refused: ' . $label);
}
$config = load($valid + ['CHAIN_NAME' => 'Dev', 'TTS_TOKEN_DECIMALS' => '2', 'CHAIN_EXPLORER_URL' => 'https://explorer.example.invalid/']);
check($config !== null && $config->blockchain->url === 'http://besu:8545' && $config->blockchain->chain_id === '2118' && $config->blockchain->zerogas === 1
    && $config->blockchain->url_block_explorer === 'https://explorer.example.invalid' && $config->blockchain->denomination === 'Dev', 'Network from the environment');
check($config->smartContract->smart_contract_address === $valid['TTS_TOKEN_ADDRESS'] && $config->smartContract->decimals === 2 && $config->smartContract->symbol === 'TTS', 'Token from the environment, TTS defaults');
$abi = json_decode($config->smartContract->contractType->smart_contract_abi, true);
check(is_array($abi) && in_array('transfer', array_column($abi, 'name'), true) && in_array('balanceOf', array_column($abi, 'name'), true), 'Standard ERC-20 ABI from the code');
check(load($valid + ['TTS_TOKEN_DECIMALS' => '99'])->smartContract->decimals === 2, 'Invalid decimals fall back to 2');
echo 'PASS ' . $checks . " chain config checks\n";
