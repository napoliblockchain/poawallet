<?php

/* @var $this yii\web\View */
/* @var $model app\models\LoginForm */

use app\widgets\WalletForm;
use yii\helpers\Html;

$this->title = Yii::t('app', 'Login');
?>
<h1 class="auth-title"><?= Html::encode(Yii::t('app', 'Sign in to your wallet')) ?></h1>
<p class="auth-subtitle"><?= Html::encode(Yii::t('app', 'Enter your credentials to continue')) ?></p>
<?php $form = WalletForm::begin(['id' => 'login-form']); ?>
<?= $form->field($model, 'username')->input('email', ['autocomplete' => 'username', 'placeholder' => Yii::t('app', 'name@example.com')]) ?>
<?= $form->field($model, 'password')->passwordInput(['autocomplete' => 'current-password', 'placeholder' => '••••••••']) ?>
<?= Html::activeHiddenInput($model, 'oauth_provider', ['value' => 'mail']) ?>
<?= Html::submitButton(Html::encode(Yii::t('app', 'Login')), ['class' => 'auth-submit', 'name' => 'login-button']) ?>
<?php WalletForm::end(); ?>
<div class="auth-links">
    <?= Html::a(Html::encode(Yii::t('app', 'Forgot password?')), ['site/request-password-reset']) ?>
    <?= Html::a(Html::encode(Yii::t('app', 'Sign Up')), ['site/register']) ?>
</div>
