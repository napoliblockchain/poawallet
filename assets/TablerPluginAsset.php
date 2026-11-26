<?php

namespace app\assets;

/** JavaScript Bootstrap 5 locale (una sola versione, in fondo alla pagina dopo jQuery). */
class TablerPluginAsset extends \yii\bootstrap5\BootstrapPluginAsset
{
    public $sourcePath = null;
    public $basePath = '@webroot/bundles/tabler';
    public $baseUrl = '@web/bundles/tabler';
    public $js = ['js/bootstrap.bundle.min.js'];
}
