<?php
use app\widgets\WalletForm as ActiveForm;
use yii\helpers\Html;
$this->title = Yii::t('app', 'Change password');
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="card">
    <div class="card-header"><h2 class="card-title"><?= Html::encode($this->title) ?></h2></div>
    <div class="card-body">
        <p class="text-secondary"><?= Html::encode(Yii::t('app', 'Changing your password signs out your other sessions. Wallet keys and funds are not affected.')) ?></p>
        <?php $form = ActiveForm::begin(['id' => 'change-password-form', 'enableClientValidation' => false]); ?>
        <?= $form->field($model, 'current_password')->passwordInput(['autocomplete' => 'current-password', 'value' => '']) ?>
        <?= $form->field($model, 'password')->passwordInput(['autocomplete' => 'new-password', 'value' => '']) ?>
        <?= $form->field($model, 'repeat_password')->passwordInput(['autocomplete' => 'new-password', 'value' => '']) ?>
        <div class="text-end"><?= Html::submitButton(Html::encode(Yii::t('app', 'Change password')), ['class' => 'btn btn-primary']) ?></div>
        <?php ActiveForm::end(); ?>
    </div>
</div>
