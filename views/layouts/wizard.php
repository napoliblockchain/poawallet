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
    <meta name="theme-color" content="#ffffff">
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
<header class="navbar wallet-topbar d-print-none">
    <div class="container-xl">
        <span class="navbar-brand d-flex align-items-center"><img src="/brand/icon.svg" alt="" class="wallet-brand-icon"><span class="wallet-wordmark" aria-label="<?= Html::encode(Yii::$app->name) ?>"><span class="wallet-wordmark-name">na<span>pay</span></span><small>Wallet</small></span></span>
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
