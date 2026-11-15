<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\DetailView;
use app\assets\AccountValueAsset;
use app\components\WebApp;


// impostazioni variabili
$options = [
    'accountValueArray' => $userAccountValueArray['accountValues'],
];
$this->registerJs(
    "var yiiUserOptions = ".\yii\helpers\Json::htmlEncode($options).";",
    yii\web\View::POS_HEAD,
    'yiiUserOptions'
);

AccountValueAsset::register($this);

include ('manage-options_js.php');
include ('manage-pin_js.php');
include ('manage-masterseed_js.php');
include ('manage-push_js.php');

$this->title = Yii::t('app', 'User Profile');
$symbol = Html::encode($node->smartContract->symbol);
$fullName = trim($model->first_name . ' ' . $model->last_name);
// installazione dell'app sulla schermata Home (deferredPrompt da service.js)
$this->registerJs(<<<JS
window.saveOnDesktop = function () {
    if (!window.deferredPrompt) return;
    deferredPrompt.prompt();
    deferredPrompt.userChoice.then(function () { $('.saveOnDesktop').removeClass('checked'); });
    deferredPrompt = null;
};
document.querySelector('.saveOnDesktop').addEventListener('click', function () { window.saveOnDesktop(); });
// stato degli interruttori anche per i lettori di schermo (la classe .checked cambia in global.script.js)
$(document).on('click', '.sweet-check', function () { var el = this; setTimeout(function () { el.setAttribute('aria-checked', el.classList.contains('checked')); }, 0); });
JS, yii\web\View::POS_END, 'wallet_save_on_desktop');
?>
<div class="card mb-3">
    <div class="card-body d-flex align-items-center gap-3">
        <span class="avatar avatar-lg rounded-circle bg-primary-lt"<?= $model->picture ? ' style="background-image: url(' . Html::encode($model->picture) . ')"' : '' ?>>
            <?= $model->picture ? '' : '<i class="fas fa-user" aria-hidden="true"></i>' ?>
        </span>
        <div class="min-w-0">
            <h2 class="mb-0 text-truncate"><?= Html::encode($fullName !== '' ? $fullName : $model->email) ?></h2>
            <div class="text-secondary text-truncate"><?= Html::encode($model->email) ?></div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6">
        <div class="card h-100"><div class="card-body">
            <div class="text-secondary small mb-1"><i class="fas fa-arrow-down text-success me-1" aria-hidden="true"></i><?= Html::encode(Yii::t('app', 'Total Income')) ?></div>
            <div class="h3 mb-2"><?= Html::encode(WebApp::si_formatter($received['sum'])) ?> <small class="text-secondary"><?= $symbol ?></small></div>
            <div class="progress progress-sm"><div class="progress-bar bg-success" role="progressbar" style="width: <?= (float) $percent_received ?>%" aria-label="<?= (int) $received['count'] ?>"></div></div>
            <div class="small text-secondary mt-1"><?= (int) $received['count'] ?> <?= Html::encode(Yii::t('app', 'Transactions')) ?></div>
        </div></div>
    </div>
    <div class="col-6">
        <div class="card h-100"><div class="card-body">
            <div class="text-secondary small mb-1"><i class="fas fa-arrow-up text-danger me-1" aria-hidden="true"></i><?= Html::encode(Yii::t('app', 'Expenses')) ?></div>
            <div class="h3 mb-2"><?= Html::encode(WebApp::si_formatter($sent['sum'])) ?> <small class="text-secondary"><?= $symbol ?></small></div>
            <div class="progress progress-sm"><div class="progress-bar bg-danger" role="progressbar" style="width: <?= (float) $percent_sent ?>%" aria-label="<?= (int) $sent['count'] ?>"></div></div>
            <div class="small text-secondary mt-1"><?= (int) $sent['count'] ?> <?= Html::encode(Yii::t('app', 'Transactions')) ?></div>
        </div></div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div>
                <div class="fw-bold"><?= Html::encode($node->smartContract->denomination) ?></div>
                <div class="text-secondary small"><?= Html::encode(WebApp::si_formatter($userAccountValueArray['balance'])) ?> <?= $symbol ?></div>
            </div>
            <span class="badge bg-<?= $userAccountValueArray['color'] === 'red' ? 'danger' : 'success' ?>-lt">
                <i class="fa fa-arrow-<?= Html::encode($userAccountValueArray['arrow']) ?> me-1" aria-hidden="true"></i><?= Html::encode($userAccountValueArray['increase']) ?>%
            </span>
        </div>
        <span class="accountValue d-block"><canvas width="252" height="80"></canvas></span>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title"><?= Html::encode(Yii::t('app', 'Edit settings')) ?></h3></div>
    <div class="list-group list-group-flush" id="settingsForm">
        <div class="list-group-item d-flex align-items-center gap-3">
            <i class="far fa-keyboard fa-fw text-primary" aria-hidden="true"></i>
            <span class="flex-fill"><?= Html::encode(Yii::t('app', 'PIN')) ?></span>
            <div class="pincodeslider sweet-check" role="switch" aria-checked="false" aria-label="<?= Html::encode(Yii::t('app', 'PIN')) ?>"><div class="outline"><span></span></div></div>
            <div class="pincodeslider-remove sweet-check checked" role="switch" aria-checked="true" aria-label="<?= Html::encode(Yii::t('app', 'Remove PIN')) ?>" style="display:none;"><div class="outline"><span></span></div></div>
        </div>
        <div class="list-group-item d-flex align-items-center gap-3 masterseed-box">
            <i class="fas fa-key fa-fw text-primary" aria-hidden="true"></i>
            <span class="flex-fill"><?= Html::encode(Yii::t('app', 'Show Master Seed')) ?></span>
            <div class="masterseedSlider sweet-check" role="switch" aria-checked="false" aria-label="<?= Html::encode(Yii::t('app', 'Show Master Seed')) ?>"><div class="outline"><span></span></div></div>
        </div>
        <div class="list-group-item d-flex align-items-center gap-3">
            <i class="far fa-bell fa-fw text-primary" aria-hidden="true"></i>
            <span class="flex-fill js-push-btn-modal-text"><?= Html::encode(Yii::t('app', 'PUSH notifications')) ?></span>
            <div class="js-push-btn-modal sweet-check" role="switch" aria-checked="false" data-bs-toggle="modal" data-bs-target="#pushEnableModal" aria-label="<?= Html::encode(Yii::t('app', 'PUSH notifications')) ?>"><div class="outline"><span></span></div></div>
        </div>
        <div class="list-group-item d-flex align-items-center gap-3">
            <i class="fas fa-mobile-alt fa-fw text-primary" aria-hidden="true"></i>
            <span class="flex-fill"><?= Html::encode(Yii::t('app', 'Save app to Home')) ?></span>
            <div class="sweet-check saveOnDesktop" role="switch" aria-checked="false" aria-label="<?= Html::encode(Yii::t('app', 'Save app to Home')) ?>"><div class="outline"><span></span></div></div>
        </div>
    </div>
</div>
