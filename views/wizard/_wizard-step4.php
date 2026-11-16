<?php

use yii\helpers\Html;
use yii\helpers\Url;

// token di sessione per la pagina di ripristino
$string = Yii::$app->security->generateRandomString(32);
Yii::$app->session->set('token-restore', $string);
?>
<div data-seed-choice>
<div class="text-center mb-3 text-primary"><i class="fas fa-tools fa-2x" aria-hidden="true"></i></div>
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
</div>
<!-- nuove chiavi: le 12 parole, poi 3 parole a caso da riscrivere prima di salvare il wallet -->
<div data-seed-backup hidden>
    <div class="text-center mb-3 text-primary"><i class="fas fa-pencil-alt fa-2x" aria-hidden="true"></i></div>
    <h2 class="card-title"><?= Html::encode(Yii::t('app', 'Your 12 words')) ?></h2>
    <div class="alert alert-warning"><?= Html::encode(Yii::t('app', 'Write these 12 words on paper, in this order, and keep them in a safe place: they are the only way to recover the wallet.')) ?></div>
    <ol class="wallet-seed-words no-copypaste mx-auto mb-4" data-seed-words aria-live="polite"></ol>
    <button type="button" class="btn btn-primary w-100" data-seed-written><?= Html::encode(Yii::t('app', 'I wrote them down')) ?></button>
</div>
<div class="wallet-seed-check" data-seed-check hidden>
    <div class="text-center mb-3 text-primary"><i class="fas fa-clipboard-check fa-2x" aria-hidden="true"></i></div>
    <h2 class="card-title"><?= Html::encode(Yii::t('app', 'Check your words')) ?></h2>
    <p class="text-secondary"><?= Html::encode(Yii::t('app', 'Type the requested words to confirm you wrote them down.')) ?></p>
    <div data-seed-check-fields></div>
    <div class="text-danger mb-3" data-seed-check-error role="alert" hidden><?= Html::encode(Yii::t('app', 'One or more words are wrong: check your paper.')) ?></div>
    <div class="d-flex justify-content-between gap-2">
        <button type="button" class="btn btn-outline-secondary" data-seed-show><i class="fa fa-arrow-left me-2" aria-hidden="true"></i><?= Html::encode(Yii::t('app', 'Show the words again')) ?></button>
        <button type="button" class="btn btn-primary" data-seed-confirm><?= Html::encode(Yii::t('app', 'Create the wallet')) ?></button>
    </div>
    <template data-seed-field-label><?= Html::encode(Yii::t('app', 'Word no. {n}', ['n' => '__N__'])) ?></template>
</div>
