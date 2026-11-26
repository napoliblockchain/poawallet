<?php

namespace app\assets;

use yii\web\AssetBundle;

/**
 * Struttura dell'app wallet: Tabler (al posto di Bootstrap 5), icone locali, stile del wallet.
 * global.script.js resta per i comportamenti delle viste (notifiche, data-loader, popup).
 */
class AppAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $css = [
        'bundles/wallet/wallet.css',
    ];
    public $js = [
        'js/global.script.js',
    ];
    public $depends = [
        'yii\web\YiiAsset',
        'yii\bootstrap5\BootstrapAsset',
        'yii\bootstrap5\BootstrapPluginAsset',
        'app\assets\FontAwesomeAsset',
    ];
}
