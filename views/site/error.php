<?php

/* @var $this yii\web\View */
/* @var $name string */
/* @var $message string */

use yii\helpers\Html;

$this->title = $name;
?>
<div class="card">
    <div class="card-body text-center">
        <div class="mb-3 text-danger"><i class="fas fa-exclamation-triangle fa-2x" aria-hidden="true"></i></div>
        <h2 class="card-title"><?= Html::encode($this->title) ?></h2>
        <p class="text-secondary"><?= nl2br(Html::encode($message)) ?></p>
        <p class="small text-secondary"><?= Html::encode(Yii::t('app', 'Please contact us if you think this is a server error. Thank you.')) ?></p>
        <?= Html::a(Html::encode(Yii::t('app', 'Back to home!')), ['/site/index'], ['class' => 'btn btn-primary']) ?>
    </div>
</div>
