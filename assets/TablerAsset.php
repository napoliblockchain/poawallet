<?php

namespace app\assets;

/** CSS Tabler locale al posto del CSS Bootstrap (mappato in config/web.php, assetManager). */
class TablerAsset extends \yii\bootstrap5\BootstrapAsset
{
    public $sourcePath = null;
    public $basePath = '@webroot/bundles/tabler';
    public $baseUrl = '@web/bundles/tabler';
    public $css = ['css/tabler.min.css'];
}
