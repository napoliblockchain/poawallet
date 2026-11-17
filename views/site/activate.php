<?php

/* @var $this yii\web\View */

use yii\helpers\Html;

$this->title = Yii::t('app', 'Activation form');
?>
<div class="card card-md">
    <div class="card-body">
        <h2 class="card-title text-center mb-4"><?= Html::encode($this->title) ?></h2>
        <?php if (Yii::$app->session->hasFlash('userActived')): ?>
            <div class="alert alert-success" role="status"><?= Html::encode(Yii::$app->session->getFlash('userActived')) ?></div>
            <?= Html::a(Html::encode(Yii::t('app', 'Login')), ['site/login'], ['class' => 'btn btn-primary w-100']) ?>
        <?php endif; ?>
        <?php if (Yii::$app->session->hasFlash('registerError')): ?>
            <div class="alert alert-warning" role="alert"><?= Html::encode(Yii::$app->session->getFlash('registerError')) ?></div>
            <?= Html::a(Html::encode(Yii::t('app', 'Back to home!')), ['site/index'], ['class' => 'btn btn-outline-primary w-100']) ?>
        <?php endif; ?>
    </div>
</div>
