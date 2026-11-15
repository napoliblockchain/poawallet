<?php

/* @var $this yii\web\View */
/* @var $model app\models\LoginForm */

use app\widgets\WalletForm;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = Yii::t('app', 'Login');
?>
<div class="card card-md">
    <div class="card-body">
        <h2 class="card-title text-center mb-4"><?= Html::encode(Yii::t('app', 'Sign in to your wallet')) ?></h2>
        <?php $form = WalletForm::begin(['id' => 'login-form']); ?>
        <?= $form->field($model, 'username')->input('email', ['autocomplete' => 'username', 'placeholder' => 'nome@esempio.it']) ?>
        <?= $form->field($model, 'password', ['template' => "{label}\n{input}\n{error}\n<div class=\"form-hint text-end mt-1\">"
            . Html::a(Html::encode(Yii::t('app', 'Forgot password?')), ['site/request-password-reset']) . '</div>'])
            ->passwordInput(['autocomplete' => 'current-password']) ?>
        <?= Html::activeHiddenInput($model, 'oauth_provider', ['value' => 'mail']) ?>
        <div class="form-footer">
            <?= Html::submitButton(Html::encode(Yii::t('app', 'Login')), ['class' => 'btn btn-primary w-100', 'name' => 'login-button']) ?>
        </div>
        <?php WalletForm::end(); ?>
    </div>
</div>
<p class="text-center wallet-switch mt-3">
    <?= Html::encode(Yii::t('app', 'Don\'t you have an account yet?')) ?>
    <?= Html::a(Html::encode(Yii::t('app', 'Sign Up')), ['site/register']) ?>
</p>
