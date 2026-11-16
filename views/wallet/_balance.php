<?php

/* Saldo token e gas della rete selezionata (pagine Wallet e Invia). id total-balance aggiornato da wallet-sync.js (flusso /events). */
/* @var $balance string|int saldo token in unità minime */
/* @var $balance_gas string|float */
/* @var $node app\models\Nodes */

use yii\helpers\Html;
use yii\helpers\Url;
use app\components\WebApp;

$decimals = (int) $node->smartContract->decimals;
?>
<div class="card wallet-hero mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-2">
            <span class="wallet-hero-label"><?= Html::encode(Yii::t('app', 'Balance on: ')) ?></span>
            <a class="small" href="<?= Url::to(['/network/index']) ?>"><i class="fas fa-network-wired me-1" aria-hidden="true"></i><?= Html::encode($node->blockchain->denomination) ?></a>
        </div>
        <div class="wallet-balance">
            <i class="fa fa-star star-total-balance fa-xs me-1" aria-hidden="true"></i>
            <span id="total-balance"><?= Html::encode(WebApp::tokenAmount($balance, $decimals)) ?></span>
            <small><?= Html::encode($node->smartContract->symbol) ?></small>
        </div>
        <div class="wallet-gas mt-1">
            <i class="fab fa-ethereum me-1" aria-hidden="true"></i><?= Html::encode(Yii::t('app', 'Gas')) ?>:
            <span id="total-balance_gas"><?= Html::encode(Yii::$app->formatter->asDecimal($balance_gas, 8)) ?></span>
            <?= Html::encode($node->blockchain->symbol) ?>
        </div>
    </div>
</div>
