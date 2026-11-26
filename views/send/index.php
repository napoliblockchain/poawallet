<?php

use app\assets\QRCodeReaderAsset;
use app\components\WebApp;
use app\widgets\WalletForm;
use yii\helpers\Html;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $sendForm app\models\SendForm */
/* @var $fromAddress string */
/* @var $balance string */
/* @var $balance_gas string */
/* @var $node object rete e token da ChainConfig (SIP-032) */

QRCodeReaderAsset::register($this);
$this->title = Yii::t('app', 'Send');
$symbol = Html::encode($node->smartContract->symbol);
$sendForm->from = $fromAddress;

include('send_js.php');
include('qrcodescanner_js.php');
?>
<?= $this->render('/wallet/_balance', ['balance' => $balance, 'balance_gas' => $balance_gas, 'node' => $node]) ?>

<div class="card">
    <div class="card-header"><h2 class="card-title"><?= Html::encode(Yii::t('app', 'Send token')) ?></h2></div>
    <div class="card-body">
        <?php $form = WalletForm::begin(['id' => 'send-form', 'enableAjaxValidation' => true, 'enableClientValidation' => false]); ?>
        <?= Html::activeHiddenInput($sendForm, 'from', ['readonly' => true]) ?>
        <?= Html::activeHiddenInput($sendForm, 'balance', ['value' => $balance]) ?>
        <?= Html::activeHiddenInput($sendForm, 'balance_gas', ['value' => $balance_gas]) ?>

        <?= $form->field($sendForm, 'to', [
            'template' => "{label}\n<div class=\"input-group\">{input}<button type=\"button\" class=\"btn btn-outline-primary\" id=\"activate-camera-btn\" aria-label=\""
                . Html::encode(Yii::t('app', 'Scan QR code')) . "\"><i class=\"fa fa-camera\" aria-hidden=\"true\"></i></button></div>\n{hint}\n{error}",
        ])->textInput(['autocomplete' => 'off', 'spellcheck' => 'false', 'placeholder' => '0x…'])
            ->hint(Yii::t('app', 'Insert address or press camera to scan'), ['class' => 'form-hint']) ?>

        <?= $form->field($sendForm, 'amount', [
            'template' => "{label}\n<div class=\"input-group\">{input}<span class=\"input-group-text\">{$symbol}</span></div>\n{error}",
        ])->textInput(['type' => 'number', 'inputmode' => 'decimal', 'step' => 'any', 'min' => '0']) ?>

        <?= $form->field($sendForm, 'memo')->textarea(['rows' => 2]) ?>

        <div class="alert alert-danger" id="error-summary" role="alert" style="display:none;"></div>
        <button type="button" class="btn btn-primary w-100" id="getCheckedButton1"><i class="far fa-paper-plane me-2" aria-hidden="true"></i><?= Html::encode(Yii::t('app', 'Send token')) ?></button>
        <?php WalletForm::end(); ?>
    </div>
</div>

<!-- conferma dell'invio (aperta da send_js.php dopo i controlli) -->
<div class="modal fade" id="sellOrder" tabindex="-1" aria-labelledby="sellOrderTitle" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="sellOrderTitle"><?= Html::encode(Yii::t('app', 'Confirm the transfer')) ?></h3>
                <button type="button" class="btn-close hide-content" data-bs-dismiss="modal" aria-label="<?= Html::encode(Yii::t('app', 'Close')) ?>"></button>
            </div>
            <div class="modal-body">
                <div class="hide-content">
                    <dl class="row mb-0">
                        <dt class="col-6 text-secondary fw-normal"><?= Html::encode(Yii::t('app', 'Amount to send:')) ?></dt>
                        <dd class="col-6 text-end fw-bold"><span class="amount-to-send"></span> <?= $symbol ?></dd>
                        <dt class="col-6 text-secondary fw-normal"><?= Html::encode(Yii::t('app', 'Asset:')) ?></dt>
                        <dd class="col-6 text-end"><span class="js-initial-amount"><?= Html::encode(WebApp::si_formatter($balance)) ?></span><span class="js-final-amount" style="display:none;"></span> <?= $symbol ?></dd>
                        <dt class="col-6 text-secondary fw-normal"><?= Html::encode(Yii::t('app', 'Transaction fee:')) ?></dt>
                        <dd class="col-6 text-end mb-0"><span id="amount-to-send-gas"></span> Gwei <?= Html::encode($node->blockchain->symbol) ?></dd>
                    </dl>
                </div>
                <div class="transaction-details" style="display: none;"></div>
            </div>
            <div class="modal-footer">
                <a href="#" class="pay-submit hide-content w-100"><button type="button" class="btn btn-primary w-100 js-confirm-submit disabled"><?= Html::encode(Yii::t('app', 'Confirm')) ?></button></a>
                <div class="pay-close w-100" style="display: none;"><?= Html::a(Html::encode(Yii::t('app', 'Close')), ['/wallet/index'], ['class' => 'btn btn-success w-100']) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- fotocamera per leggere il QR (qrcodescanner_js.php) -->
<div class="modal fade" id="cameraPopup" tabindex="-1" aria-labelledby="cameraPopupTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="cameraPopupTitle"><?= Html::encode(Yii::t('app', 'Camera scan')) ?></h3>
                <button type="button" class="btn-close" id="camera-close" data-bs-dismiss="modal" aria-label="<?= Html::encode(Yii::t('app', 'Close')) ?>"></button>
            </div>
            <div class="modal-body" id="camera-body"><video muted playsinline id="qr-video"></video></div>
        </div>
    </div>
</div>
