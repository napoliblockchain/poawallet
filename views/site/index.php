<?php

/* @var $this yii\web\View */

use yii\helpers\Html;

$this->title = Yii::$app->name;
?>
<div class="card card-md">
    <div class="card-body text-center">
        <h2 class="card-title mb-3"><?= Html::encode(Yii::$app->name) ?></h2>
        <p class="text-secondary mb-4"><?= Html::encode(Yii::t('app', 'a Progressive Web App. It works with several crypto tokens and blockchains. With Napay Wallet, you are in control over your funds. Receive, send, store and exchange your cryptocurrency within the mobile interface.')) ?></p>
        <?= Html::a(Html::encode(Yii::t('app', 'Start')), ['site/login'], ['class' => 'btn btn-primary w-100']) ?>
    </div>
</div>
