<?php

/* @var $this \yii\web\View */
/* @var $content string */

use app\assets\AppAsset;
use app\assets\LightWalletAsset;
use app\assets\ServiceWorkerAsset;
use app\components\Logo;
use yii\helpers\Html;

AppAsset::register($this);
LightWalletAsset::register($this);
ServiceWorkerAsset::register($this);
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Html::encode(Yii::$app->language) ?>">
<head>
    <meta charset="<?= Html::encode(Yii::$app->charset) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#2a4fa0">
    <?php $this->registerCsrfMetaTags() ?>
    <title><?= Html::encode($this->title) ?></title>
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" href="/brand/icon.svg" type="image/svg+xml">
    <link rel="icon" href="/favicon.ico" sizes="48x48">
    <link rel="apple-touch-icon" href="/brand/apple-touch-icon.png">
    <?php $this->head() ?>
</head>
<body class="wallet-app">
<?php $this->beginBody() ?>
<header class="navbar navbar-dark wallet-topbar d-print-none">
    <div class="container-xl">
        <span class="navbar-brand d-flex align-items-center"><img src="/css/images/logo.png" alt=""><span><?= Html::encode(Yii::$app->name) ?></span></span>
    </div>
</header>
<main class="page-wrapper">
    <div class="container-tight py-4">
        <?= $this->render('_flash') ?>
        <?= $content ?>
    </div>
</main>
<footer class="text-center text-secondary small py-4"><?= Logo::footer() ?></footer>
<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
