<?php

/* @var $this \yii\web\View */
/* @var $content string */

use yii\helpers\Html;
use app\assets\AppAsset;
use app\assets\PincodeAsset;
use app\assets\NotificationsAsset;
use app\assets\ServiceWorkerAsset;
use app\assets\WalletSyncAsset;

use app\components\Settings;

// impostazioni variabili globali per tutti i js
$options = [
    'userId' => (int)Yii::$app->user->id,
    'cryptedIdUser' => app\components\WebApp::encrypt(Yii::$app->user->id),
    'spinner' => '<div class="button-spinner spinner-border text-primary" style="width:1.3rem; height:1.3rem;" role="status"><span class="visually-hidden">Loading...</span></div>',
    'urlUpdateNotification' => \yii\helpers\Url::to(['/push/notified']),
    'urlSoundNotification' => '/src/sounds/notify.mp3',
    'pinWrong' => Yii::t('app', 'PIN is wrong!'),
];
$this->registerJs(
    "var yiiGlobalOptions = ".\yii\helpers\Json::htmlEncode($options).";",
    yii\web\View::POS_HEAD,
    'yiiGlobalOptions'
);


AppAsset::register($this);

PincodeAsset::register($this);
ServiceWorkerAsset::register($this);
WalletSyncAsset::register($this);

// try to fix page call to backend/notify
NotificationsAsset::register($this);

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
<body class="wallet-app">
<?php $this->beginBody() ?>
<div class="page">
    <?= $this->render('_header') ?>
    <?= $this->render('_navbar') ?>
    <?= $this->render('_searchform') ?>
    <main class="page-wrapper wallet-main">
        <div class="container-xl py-3">
            <?= $this->render('_flash') ?>
            <?= $content ?>
        </div>
    </main>
</div>

<div id="snackbar" role="status">
    <p class="mb-2"><?= Html::encode(Yii::t('app', 'A new version of this app is available.')) ?></p>
    <button type="button" id="reload" class="btn btn-warning"><?= Html::encode(Yii::t('app', 'Update')) ?></button>
</div>

<?php
// modal PAGE

if (Yii::$app->controller->id == 'users'){
    echo $this->render('_pin-manage');
    echo $this->render('_push-manage');
    echo $this->render('_masterseed');
}

if (Yii::$app->controller->id == 'settings'){
    echo $this->render('_blockchainscan');
}

if (Yii::$app->controller->id == 'receive'){
    echo $this->render('_clipboard-copy');
}

echo $this->render('_pin-request');

?>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
