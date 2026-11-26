<?php

/* @var $this yii\web\View */
/* @var $model app\models\ResetPasswordForm */

use app\widgets\WalletForm;
use yii\helpers\Html;

$this->title = Yii::t('app', 'Reset password');
?>
<div class="card card-md">
    <div class="card-body">
        <h2 class="card-title text-center mb-2"><?= Html::encode($this->title) ?></h2>
        <p class="text-secondary text-center mb-4"><?= Html::encode(Yii::t('app', 'Please choose your new password:')) ?></p>
        <?php $form = WalletForm::begin(['id' => 'reset-password-form']); ?>
        <?= $form->field($model, 'password')->passwordInput(['autocomplete' => 'new-password', 'autofocus' => true]) ?>
        <div class="form-footer">
            <?= Html::submitButton(Html::encode(Yii::t('app', 'Save')), ['class' => 'btn btn-primary w-100']) ?>
        </div>
        <?php WalletForm::end(); ?>
    </div>
</div>
