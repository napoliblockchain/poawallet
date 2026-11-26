<?php

/* @var $this yii\web\View */
/* @var $model app\models\PasswordResetRequestForm */

use app\widgets\WalletForm;
use yii\helpers\Html;

$this->title = Yii::t('app', 'Request password reset');
?>
<div class="card card-md">
    <div class="card-body">
        <h2 class="card-title text-center mb-2"><?= Html::encode($this->title) ?></h2>
        <p class="text-secondary text-center mb-4"><?= Html::encode(Yii::t('app', 'Please fill out your email. A link to reset password will be sent there.')) ?></p>
        <?php $form = WalletForm::begin(['id' => 'request-password-reset-form']); ?>
        <?= $form->field($model, 'email')->input('email', ['autocomplete' => 'email', 'autofocus' => true]) ?>
        <div class="form-footer">
            <?= Html::submitButton(Html::encode(Yii::t('app', 'Send')), ['class' => 'btn btn-primary w-100']) ?>
        </div>
        <?php WalletForm::end(); ?>
    </div>
</div>
<p class="text-center wallet-switch mt-3"><?= Html::a(Html::encode(Yii::t('app', 'Back to login')), ['site/login']) ?></p>
