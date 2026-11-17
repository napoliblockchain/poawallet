<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $node object rete e token da ChainConfig (SIP-032) */

$this->title = Yii::t('app', 'Network info');
// blocchi aggiornati da wallet-sync.js (evento "status" del flusso in tempo reale, classi network-*)
?>
<div class="network-details">
    <div class="card mb-3">
        <div class="card-header"><h2 class="card-title"><i class="fas fa-network-wired me-2 text-primary" aria-hidden="true"></i><?= Html::encode(Yii::t('app', 'Node info')) ?></h2></div>
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-4 text-secondary fw-normal"><?= Html::encode(Yii::t('app', 'Network: ')) ?></dt><dd class="col-8"><?= Html::encode($node->blockchain->denomination ?? '') ?></dd>
                <dt class="col-4 text-secondary fw-normal"><?= Html::encode(Yii::t('app', 'Token: ')) ?></dt><dd class="col-8"><?= Html::encode($node->smartContract->denomination ?? '') ?></dd>
                <dt class="col-4 text-secondary fw-normal"><?= Html::encode(Yii::t('app', 'Chain ID')) ?></dt><dd class="col-8"><?= Html::encode((string) ($node->blockchain->chain_id ?? '')) ?></dd>
                <dt class="col-4 text-secondary fw-normal"><?= Html::encode(Yii::t('app', 'Token contract')) ?></dt><dd class="col-8 text-break font-monospace mb-0" data-token-address><?= Html::encode($node->smartContract->smart_contract_address ?? '') ?></dd>
            </dl>
        </div>
    </div>
    <div class="row g-3">
        <div class="col-md-6">
            <div class="card h-100"><div class="card-body">
                <div class="d-flex justify-content-between"><span class="text-secondary"><i class="fas fa-cubes me-1" aria-hidden="true"></i><?= Html::encode(Yii::t('app', 'Latest block')) ?></span><span class="network-block-number fw-bold"></span></div>
            </div></div>
        </div>
        <div class="col-md-6">
            <div class="card h-100"><div class="card-body">
                <div class="d-flex justify-content-between"><span class="text-secondary"><i class="fas fa-wallet me-1" aria-hidden="true"></i><?= Html::encode(Yii::t('app', 'Wallet block')) ?></span>
                    <span><span class="network-block-percentage text-success"></span> <span class="network-block-wallet fw-bold"></span></span></div>
            </div></div>
        </div>
    </div>
</div>
