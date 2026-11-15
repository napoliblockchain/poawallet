<?php

use yii\helpers\Html;
use yii\helpers\Url;

// token di sessione per la pagina di ripristino
$string = Yii::$app->security->generateRandomString(32);
Yii::$app->session->set('token-restore', $string);
?>
<h2 class="card-title"><?= Html::encode(Yii::t('app', 'Create or restore')) ?></h2>
<div class="list-group mb-4">
    <a class="list-group-item list-group-item-action d-flex gap-3 align-items-start" href="<?= Url::to(['/restore/index', 'token' => $string]) ?>">
        <i class="fas fa-undo-alt text-primary mt-1" aria-hidden="true"></i>
        <span><strong class="d-block"><?= Html::encode(Yii::t('app', 'Restore')) ?></strong>
            <span class="text-secondary small"><?= Html::encode(Yii::t('app', 'You already have the 12 words (seed) of a wallet and want to restore it.')) ?></span></span>
    </a>
    <button type="button" class="list-group-item list-group-item-action d-flex gap-3 align-items-start seed-submit" id="seed-submit">
        <i class="fas fa-key text-success mt-1" aria-hidden="true"></i>
        <span class="text-start"><strong class="d-block" id="js-newseed-btn-text"><?= Html::encode(Yii::t('app', 'New keys')) ?></strong>
            <span class="text-secondary small"><?= Html::encode(Yii::t('app', 'Generate a new wallet with new 12 words.')) ?></span></span>
    </button>
</div>
<button type="button" id="stepwizard_step4_prev" class="btn btn-outline-secondary prev-step"><i class="fa fa-arrow-left me-2" aria-hidden="true"></i><?= Html::encode(Yii::t('app', 'Previous')) ?></button>
