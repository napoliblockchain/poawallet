<?php

namespace app\components;

use yii\base\InvalidConfigException;

/**
 * Rete e token del wallet, solo da variabili d'ambiente (SIP-032): il wallet serve Napay e usa una sola chain e un
 * solo token. Nessun URL RPC scelto dagli utenti. La forma ricalca i vecchi nodi per utente (->blockchain,
 * ->smartContract) così Erc20 e le viste restano uguali.
 *
 * Variabili: CHAIN_RPC_URL, CHAIN_ID, CHAIN_NAME, CHAIN_SYMBOL (gas), CHAIN_EXPLORER_URL, CHAIN_ZERO_GAS,
 * TTS_TOKEN_ADDRESS, TTS_TOKEN_NAME, TTS_TOKEN_SYMBOL, TTS_TOKEN_DECIMALS.
 */
final class ChainConfig
{
    private static ?object $config = null;

    /** @throws InvalidConfigException se manca o non è valida la rete o il token */
    public static function get(): object
    {
        if (self::$config !== null) return self::$config;
        $url = self::env('CHAIN_RPC_URL');
        $chainId = self::env('CHAIN_ID');
        $address = self::env('TTS_TOKEN_ADDRESS');
        $explorer = rtrim(self::env('CHAIN_EXPLORER_URL'), '/');
        if (preg_match('#^https?://[^\s]+$#', $url) !== 1) throw new InvalidConfigException('CHAIN_RPC_URL is missing or invalid.');
        if (preg_match('/^[1-9][0-9]{0,9}$/D', $chainId) !== 1) throw new InvalidConfigException('CHAIN_ID is missing or invalid.');
        if (preg_match('/^0x[0-9a-fA-F]{40}$/D', $address) !== 1) throw new InvalidConfigException('TTS_TOKEN_ADDRESS is missing or invalid.');
        if ($explorer !== '' && preg_match('#^https?://[^\s]+$#', $explorer) !== 1) throw new InvalidConfigException('CHAIN_EXPLORER_URL is invalid.');
        $abi = (string) file_get_contents(__DIR__ . '/abi/erc20.json');
        return self::$config = (object) [
            'blockchain' => (object) [
                'denomination' => self::env('CHAIN_NAME', 'Napay TTS'),
                'url' => $url,
                'chain_id' => $chainId,
                'symbol' => self::env('CHAIN_SYMBOL', 'ETH'),
                'url_block_explorer' => $explorer,
                'zerogas' => self::env('CHAIN_ZERO_GAS', '1') === '1' ? 1 : 0,
            ],
            'smartContract' => (object) [
                'denomination' => self::env('TTS_TOKEN_NAME', 'TTS'),
                'symbol' => self::tokenSymbol(),
                'decimals' => self::tokenDecimals(),
                'smart_contract_address' => $address,
                'contractType' => (object) ['smart_contract_abi' => $abi],
            ],
        ];
    }

    /** Simbolo del token anche senza rete configurata (righe delle transazioni, test). */
    public static function tokenSymbol(): string
    {
        return self::env('TTS_TOKEN_SYMBOL', 'TTS');
    }

    public static function tokenDecimals(): int
    {
        $decimals = self::env('TTS_TOKEN_DECIMALS', '2');
        return preg_match('/^(1[0-8]|[0-9])$/D', $decimals) === 1 ? (int) $decimals : 2;
    }

    private static function env(string $name, string $default = ''): string
    {
        $value = getenv($name);
        return $value === false || trim($value) === '' ? $default : trim($value);
    }
}
