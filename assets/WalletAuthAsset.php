<?php

namespace app\assets;

use yii\web\AssetBundle;

/**
 * Pagine di accesso del wallet (login, registrazione, attivazione, password): Tabler locale, stile del wallet e
 * scheda comune alle app Napay (auth.css, catena di blocchi animata), senza Bootstrap 4 né risorse esterne.
 */
class WalletAuthAsset extends AssetBundle
{
    public $basePath = '@webroot/bundles';
    public $baseUrl = '@web/bundles';
    public $css = [
        'tabler/css/tabler.min.css',
        'wallet/wallet.css',
        'auth/auth.css',
    ];
    public $js = [
        'auth/auth-chain.js',
    ];
    public $depends = [
        'yii\web\YiiAsset',
    ];
}
