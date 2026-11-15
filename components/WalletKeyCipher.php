<?php

namespace app\components;

use Nullix\JsAesPhp\JsAesPhp;

/**
 * Decifra la chiave privata che il browser custodisce in IndexedDB (cifrata con la password del wallet).
 * Il browser (procedura guidata, ripristino) usa CryptoJS: JSON {"ct": base64, "iv": hex, "s": hex}, AES-256-CBC con chiave
 * derivata come OpenSSL EVP_BytesToKey (MD5). Il formato esadecimale di js-aes-php resta accettato.
 * Fino al 2026-11-15 il server leggeva solo il secondo: gli invii dal wallet fallivano ("Invalid encryptedValue").
 */
final class WalletKeyCipher
{
    /** @return mixed valore decifrato (JSON decodificato) o null se il formato o la password non sono validi */
    public static function decrypt(string $encrypted, string $password): mixed
    {
        if (str_starts_with(ltrim($encrypted), '{')) {
            return self::decryptCryptoJs($encrypted, $password);
        }
        try {
            return JsAesPhp::decrypt($encrypted, $password);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function decryptCryptoJs(string $encrypted, string $password): mixed
    {
        $json = json_decode($encrypted, true);
        if (!is_array($json) || !is_string($json['ct'] ?? null) || !is_string($json['s'] ?? null) || !is_string($json['iv'] ?? null)
            || preg_match('/^[0-9a-f]{16}$/D', $json['s']) !== 1 || preg_match('/^[0-9a-f]{32}$/D', $json['iv']) !== 1) {
            return null;
        }
        $cipherText = base64_decode($json['ct'], true);
        if ($cipherText === false || $cipherText === '') {
            return null;
        }
        $salt = hex2bin($json['s']);
        // EVP_BytesToKey(MD5): blocchi md5(precedente || password || salt) fino a 32 byte di chiave + 16 di IV
        $derived = '';
        $block = '';
        while (strlen($derived) < 48) {
            $block = md5($block . $password . $salt, true);
            $derived .= $block;
        }
        $plain = openssl_decrypt($cipherText, 'aes-256-cbc', substr($derived, 0, 32), OPENSSL_RAW_DATA, substr($derived, 32, 16));
        if ($plain === false) {
            return null;
        }
        return json_decode($plain, true);
    }

    /** Cifra nel formato del browser (CryptoJS): usato dai test per riprodurre una chiave salvata dal wallet. */
    public static function encryptCryptoJs(mixed $value, string $password): string
    {
        $salt = random_bytes(8);
        $derived = '';
        $block = '';
        while (strlen($derived) < 48) {
            $block = md5($block . $password . $salt, true);
            $derived .= $block;
        }
        $iv = substr($derived, 32, 16);
        $cipher = openssl_encrypt(json_encode($value), 'aes-256-cbc', substr($derived, 0, 32), OPENSSL_RAW_DATA, $iv);
        return json_encode(['ct' => base64_encode($cipher), 'iv' => bin2hex($iv), 's' => bin2hex($salt)]);
    }
}
