<?php

// environment from YII_ENV / YII_DEBUG (set by the Docker target); default production, no debug in prod
$yiiEnv = getenv('YII_ENV');
defined('YII_ENV') or define('YII_ENV', in_array($yiiEnv, ['dev', 'test', 'prod'], true) ? $yiiEnv : 'prod');
defined('YII_DEBUG') or define('YII_DEBUG', YII_ENV !== 'prod' && filter_var(getenv('YII_DEBUG'), FILTER_VALIDATE_BOOLEAN));

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

$config = require __DIR__ . '/../config/web.php';

(new yii\web\Application($config))->run();
