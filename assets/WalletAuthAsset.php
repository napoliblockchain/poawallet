<?php

namespace app\assets;

use yii\web\AssetBundle;

/**
 * Pagine di accesso del wallet (login, registrazione, attivazione, password): Tabler locale e
 * stile del wallet, senza Bootstrap 4 né risorse esterne.
 */
class WalletAuthAsset extends AssetBundle
{
    public $basePath = '@webroot/bundles';
    public $baseUrl = '@web/bundles';
    public $css = [
        'tabler/css/tabler.min.css',
        'wallet/wallet.css',
    ];
    public $depends = [
        'yii\web\YiiAsset',
    ];
}
