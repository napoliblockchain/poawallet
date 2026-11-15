<?php

use app\widgets\WalletForm;
use yii\helpers\Html;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $formModel app\models\WizardWalletForm */

$string = Yii::$app->security->generateRandomString(32);
Yii::$app->session->set('token-wizard', $string);
$this->title = Yii::t('app', 'Restore your seed');

$form = WalletForm::begin(['id' => 'wizard-form']);
include('_js.php');
?>
<div class="card card-md">
    <div class="card-body">
        <h2 class="card-title"><?= Html::encode($this->title) ?></h2>
        <p class="text-secondary"><?= Html::encode(Yii::t('app', 'Insert your seed to restore the wallet.')) ?></p>
        <?= $form->field($formModel, 'seed')->textarea(['rows' => 4, 'class' => 'no-copypaste form-control text-lowercase wallet-seed',
            'autocomplete' => 'off', 'autocapitalize' => 'none', 'spellcheck' => 'false']) ?>
        <div class="alert alert-danger" id="seed-error" role="alert" style="display:none;"></div>
        <?= Html::activeHiddenInput($formModel, 'address') ?>
        <div class="d-flex justify-content-between gap-2 mt-4">
            <a class="btn btn-outline-secondary" href="<?= Url::to(['/wizard/index', 'token' => $string]) ?>"><i class="fa fa-arrow-left me-2" aria-hidden="true"></i><?= Html::encode(Yii::t('app', 'back')) ?></a>
            <button type="button" class="btn btn-success seed-submit"><i class="fa fa-check me-2" aria-hidden="true"></i><?= Html::encode(Yii::t('app', 'Confirm')) ?></button>
        </div>
    </div>
</div>
<?php WalletForm::end(); ?>
