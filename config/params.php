<?php
$env = require __DIR__ . '/env.php';

return [
    'adminEmail' => $env('MAIL_ADMIN'),
    'senderEmail' => $env('MAIL_USERNAME'),
    'senderName' => $env('MAIL_FROM_NAME'),
    'logoApplicazione' => '/css/images/logo.png',
    'website' => $env('APP_WEBSITE'),
    'adminName' => $env('APP_ADMIN_NAME'),
    'supportEmail' => $env('MAIL_SUPPORT'),

    /**
     * AES key used by WebApp::encrypt/decrypt.
     * Must stay the same values used to encrypt existing data.
     */
    'encryption' => [
        'secret_key' => $env('ENCRYPT_SECRET_KEY'),
        'secret_iv' => $env('ENCRYPT_SECRET_IV'),
        'encrypt_method' => $env('ENCRYPT_METHOD', 'AES-256-CBC'),
    ],

    /**
     * VAPID keys for web push (VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY). Empty = push disabled.
     * subject: contact for push services (mailto: or https URL).
     */
    'vapid' => [
        'public_key' => $env('VAPID_PUBLIC_KEY', null),
        'private_key' => $env('VAPID_PRIVATE_KEY', null),
        'subject' => $env('VAPID_SUBJECT', 'mailto:' . $env('MAIL_SUPPORT')),
    ],

    // Redis channel listened by ws-notify (same value as NOTIFY_CHANNEL of the ws-notify service)
    'notify.channel' => $env('NOTIFY_CHANNEL', 'poawallet:notify'),

    /**
     * Set the password reset token expiration time.
     */
    'user.passwordResetTokenExpire' => 3600,
    'user.passwordMinLength' => 8,

    /**
     * Set the list of usernames that we do not want to allow to users to take upon registration or profile change.
     */
    'user.spamNames' => 'admin|superadmin|creator|thecreator|username|administrator|root',

    //
    'user.rememberMeDuration' => 7776000, // This number is 60sec * 60min * 24h * 90days

    /**
     * Set the nonce timeout for activate user
     */
    'nonce.timeout' => 24 * 60 * 60,


    // Telegram login informations
    'telegram.clientId' => $env('TELEGRAM_CLIENT_ID', null),
    'telegram.clientSecret' => $env('TELEGRAM_CLIENT_SECRET', null),


    // smart contract
    'smartcontract_address' => $env('SMARTCONTRACT_ADDRESS', null),
    'default_blockchain' => $env('DEFAULT_BLOCKCHAIN', null),
    'default_smartcontract' => $env('DEFAULT_SMARTCONTRACT', null),

    // sealer address for POA CZN
    'sealer_account_2' => $env('SEALER_ACCOUNT_2', null),
    'sealer_prvkey_2' => $env('SEALER_PRVKEY_2', null),

    // sealer address for POA FID (zerogas faucet, see Erc20)
    'sealer_account_3' => $env('SEALER_ACCOUNT_3', null),
    'sealer_prvkey_3' => $env('SEALER_PRVKEY_3', null),


    // websocket url
    'WebSocketServerAddress' => $env('WEBSOCKET_URL', '/ws-notify'),
    'WebSocketServerAddress-localhost' => $env('WEBSOCKET_URL_LOCALHOST', '/ws-notify'),
    'websocket_url' => $env('WEBSOCKET_URL', null),
];
