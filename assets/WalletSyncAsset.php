<?php

namespace app\assets;

use yii\web\AssetBundle;

/** Sincronizzazione periodica del wallet con il server (web/js/wallet-sync.js), al posto del WebSocket. */
class WalletSyncAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $js = ['js/wallet-sync.js'];
    public $depends = ['yii\web\YiiAsset'];
}
