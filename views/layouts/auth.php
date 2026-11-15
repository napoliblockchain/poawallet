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
    <meta name="theme-color" content="#2a4fa0">
    <?php $this->registerCsrfMetaTags() ?>
    <title><?= Html::encode($this->title) ?></title>
    <link rel="manifest" href="/manifest.json">
    <?php $this->head() ?>
</head>
<body class="wallet-auth">
<?php $this->beginBody() ?>
<div class="page page-center">
    <div class="container container-tight py-4">
        <div class="text-center mb-4">
            <a href="<?= Url::to(['site/index']) ?>" class="wallet-brand">
                <img src="/css/images/logo.png" alt="">
                <span><?= Html::encode(Yii::$app->name) ?></span>
            </a>
        </div>
        <?= $this->render('_flash') ?>
        <?= $content ?>
        <div class="wallet-footer mt-4"><?= Logo::footer() ?></div>
    </div>
</div>
<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
