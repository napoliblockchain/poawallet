<?php

use yii\helpers\Html;
use yii\helpers\Url;

/* Barra in alto: indietro (fuori dal wallet), marchio, stato sincronizzazione, notifiche, menu "Altro". */
$wallet = Yii::$app->controller->id === 'wallet';
?>
<header class="navbar wallet-topbar sticky-top d-print-none">
    <div class="container-xl gap-2 flex-nowrap">
        <?php if (!$wallet): ?>
            <a class="btn btn-icon" href="javascript:history.back();" aria-label="<?= Html::encode(Yii::t('app', 'Back')) ?>"><i class="fa fa-arrow-left" aria-hidden="true"></i></a>
        <?php endif ?>
        <a class="navbar-brand d-flex align-items-center me-auto" href="<?= Url::to(['/wallet/index']) ?>">
            <img src="/brand/icon.svg" alt="" class="wallet-brand-icon"><span class="wallet-wordmark" aria-label="<?= Html::encode(Yii::$app->name) ?>"><span class="wallet-wordmark-name">na<span>pay</span></span><small>Wallet</small></span>
        </a>
        <small class="header-message d-none d-sm-inline text-truncate"></small>
        <span class="d-inline-flex align-items-center px-2">
            <span class="pulse-button pulse-button-pending" role="status" aria-label="<?= Html::encode(Yii::t('app', 'Synchronizing')) ?>"
                data-label-ok="<?= Html::encode(Yii::t('app', 'Synchronized')) ?>" data-label-pending="<?= Html::encode(Yii::t('app', 'Synchronizing')) ?>"
                data-label-error="<?= Html::encode(Yii::t('app', 'Server not reachable: retrying')) ?>"
                data-label-lagging="<?= Html::encode(Yii::t('app', 'Blockchain reading delayed')) ?>"></span>
        </span>
        <button type="button" class="btn btn-icon" data-search="open" aria-label="<?= Html::encode(Yii::t('app', 'Notifications')) ?>">
            <i class="fas fa-bell" aria-hidden="true"></i>
            <span class="notify-quantity" style="display:none;" id="quantity_circle"><span id="quantity_notify"></span></span>
        </button>
        <button type="button" class="btn btn-icon" data-bs-toggle="offcanvas" data-bs-target="#wallet-more" aria-controls="wallet-more" aria-label="<?= Html::encode(Yii::t('app', 'Menu')) ?>">
            <i class="fas fa-bars" aria-hidden="true"></i>
        </button>
    </div>
</header>
