<?php

/* @var $this \yii\web\View */
/* @var $content string */

use app\assets\ServiceWorkerAsset;
use app\assets\WalletAuthAsset;
use app\components\Logo;
use yii\helpers\Html;
use yii\helpers\Url;

WalletAuthAsset::register($this);
ServiceWorkerAsset::register($this);
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Html::encode(Yii::$app->language) ?>">
<head>
    <meta charset="<?= Html::encode(Yii::$app->charset) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#241a00">
    <?php $this->registerCsrfMetaTags() ?>
    <title><?= Html::encode($this->title) ?></title>
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" href="/brand/icon.svg" type="image/svg+xml">
    <link rel="icon" href="/favicon.ico" sizes="48x48">
    <link rel="apple-touch-icon" href="/brand/apple-touch-icon.png">
    <?php $this->head() ?>
</head>
<body class="wallet-auth auth auth--wallet">
<?php $this->beginBody() ?>
<div class="auth-card">
    <aside class="auth-brand">
        <canvas class="auth-chain" aria-hidden="true"></canvas>
        <img class="auth-icon" src="/brand/icon-192.png" alt="">
        <div class="auth-divider"></div>
        <p class="auth-tag"><?= Html::encode(Yii::t('app', 'Your TTS wallet: the keys stay on your device')) ?></p>
    </aside>
    <main class="auth-main">
        <div class="auth-form">
            <a class="auth-wordmark" href="<?= Url::to(['site/index']) ?>" aria-label="<?= Html::encode(Yii::$app->name) ?>">na<span>pay</span><small>Wallet</small></a>
            <?= $this->render('_flash') ?>
            <?= $content ?>
        </div>
        <div class="auth-supporters">
            <p><?= Html::encode(Yii::t('app', 'Our supporters')) ?></p>
            <div class="auth-logos">
                <img class="auth-logo-nb" src="/bundles/auth/napoli-blockchain.png" alt="Napoli Blockchain">
                <img class="auth-logo-uni" src="/bundles/auth/parthenope.jpg" alt="Università degli Studi di Napoli Parthenope">
            </div>
            <p class="auth-footer"><?= Logo::footer() ?></p>
        </div>
    </main>
</div>
<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
