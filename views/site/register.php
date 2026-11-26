<?php

/* @var $this yii\web\View */
/* @var $model app\models\SignupForm */

use app\widgets\WalletForm;
use yii\helpers\Html;

$this->title = Yii::t('app', 'Register');
?>
<div class="card card-md">
    <div class="card-body">
        <h2 class="card-title text-center mb-2"><?= Html::encode(Yii::t('app', 'Registration form')) ?></h2>
        <?php if (Yii::$app->session->hasFlash('registerFormSubmitted')): ?>
            <div class="alert alert-success" role="status">
                <?= Html::encode(Yii::t('app', 'Your registration request has been registered.')) ?>
                <?= Html::encode(Yii::t('app', 'You will receive an email to confirm your subscription.')) ?>
            </div>
            <?= Html::a(Html::encode(Yii::t('app', 'Login')), ['site/login'], ['class' => 'btn btn-primary w-100']) ?>
        <?php else: ?>
            <p class="text-secondary text-center mb-4"><?= Html::encode(Yii::t('app', 'Please fill out the fields. A link to activate your account will be sent.')) ?></p>
            <?php $form = WalletForm::begin(['id' => 'register-form']); ?>
            <?= $form->field($model, 'username')->input('email', ['autocomplete' => 'username', 'placeholder' => 'nome@esempio.it']) ?>
            <?= $form->field($model, 'password')->passwordInput(['autocomplete' => 'new-password']) ?>
            <?= $form->field($model, 'password_repeat')->passwordInput(['autocomplete' => 'new-password']) ?>
            <div class="form-footer">
                <?= Html::submitButton(Html::encode(Yii::t('app', 'Register')), ['class' => 'btn btn-primary w-100', 'name' => 'register-button']) ?>
            </div>
            <?php WalletForm::end(); ?>
        <?php endif; ?>
    </div>
</div>
<p class="text-center wallet-switch mt-3">
    <?= Html::encode(Yii::t('app', 'Already have an account?')) ?>
    <?= Html::a(Html::encode(Yii::t('app', 'Login')), ['site/login']) ?>
</p>
