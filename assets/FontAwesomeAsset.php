<?php

namespace app\assets;

use yii\web\AssetBundle;

/** Icone Font Awesome Free 5.15.4 locali (nessun CDN). */
class FontAwesomeAsset extends AssetBundle
{
    public $basePath = '@webroot/bundles/fontawesome';
    public $baseUrl = '@web/bundles/fontawesome';
    public $css = ['css/all.min.css'];
}
