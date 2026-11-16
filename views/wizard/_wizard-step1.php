<?php

use yii\helpers\Html;

$name = trim((string) (Yii::$app->user->identity->first_name ?? ''));
?>
<div class="text-center mb-3 text-primary"><i class="fas fa-wallet fa-2x" aria-hidden="true"></i></div>
<h2 class="card-title"><?= Html::encode(Yii::t('app', 'Hi') . ($name !== '' ? ' ' . $name : '') . ',') ?></h2>
<p class="lead"><?= Html::encode(Yii::t('app', 'soon you will activate your new wallet.')) ?></p>
<p class="text-secondary"><?= Html::encode(Yii::t('app', 'Your electronic wallet will be made secure thanks to a mathematical process that will make the content unreadable.')) ?></p>
<div class="d-flex justify-content-between gap-2 mt-4">
    <?= Html::beginForm(['/site/logout'], 'post', ['id' => 'form-logout']) ?>
    <button type="submit" class="btn btn-outline-secondary site-logout"><i class="fas fa-sign-out-alt me-1" aria-hidden="true"></i><?= Html::encode(Yii::t('app', 'Logout')) ?></button>
    <?= Html::endForm() ?>
    <button type="button" id="stepwizard_step1_next" class="btn btn-primary next-step"><?= Html::encode(Yii::t('app', 'Start')) ?><i class="fa fa-arrow-right ms-2" aria-hidden="true"></i></button>
</div>
