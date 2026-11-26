<?php

/* @var $this yii\web\View */

use yii\helpers\Html;

$this->title = Yii::$app->name;
?>
<div class="card card-md">
    <div class="card-body text-center">
        <h2 class="card-title mb-3"><?= Html::encode(Yii::$app->name) ?></h2>
        <p class="text-secondary mb-4"><?= Html::encode(Yii::t('app', 'The Napay wallet: receive and spend TTS in the shops of the network. Your keys stay on your device.')) ?></p>
        <?= Html::a(Html::encode(Yii::t('app', 'Start')), ['site/login'], ['class' => 'btn btn-primary w-100']) ?>
    </div>
</div>
