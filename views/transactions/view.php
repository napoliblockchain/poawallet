<?php

use app\components\WebApp;
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model app\models\Transactions */

$this->title = Yii::t('app', 'Transaction details');
include('view_js.php');

// explorer della chain configurata (SIP-032), vuoto in sviluppo
$explorer = rtrim((string) app\components\Settings::poa()->blockchain->url_block_explorer, '/');
// link all'explorer solo se l'indirizzo configurato è http(s)
$explorerLink = static function (string $text, string $path) use ($explorer): string {
    if ($text === '' || !preg_match('~^https?://~i', $explorer)) return Html::encode($text);
    return Html::a(Html::encode($text), $explorer . $path, ['target' => '_blank', 'rel' => 'noopener', 'class' => 'wallet-address']);
};
$pending = $model->status == 'new' && $model->txhash != '';
$status = $pending ? Yii::t('app', 'Pending...') : $model->status;
?>
<div class="card">
    <div class="card-header">
        <h2 class="card-title"><?= Html::encode($this->title) ?></h2>
        <div class="card-actions">
            <button type="button" class="btn btn-sm <?= $model->status == 'complete' ? 'btn-success' : 'btn-warning' ?> trans-get text-capitalize" id="trans-get-<?= (int) $model->id ?>"
                title="<?= Html::encode(Yii::t('app', 'Refresh status')) ?>"><i class="fas fa-sync-alt me-1" aria-hidden="true"></i><?= Html::encode($status) ?></button>
        </div>
    </div>
    <div class="card-body">
        <div class="text-center mb-4">
            <div class="display-6 fw-bold"><?= Html::encode(Yii::$app->formatter->asDecimal($model->token_price)) ?> <small class="fs-4 text-secondary"><?= Html::encode(app\components\ChainConfig::tokenSymbol()) ?></small></div>
            <div class="text-secondary"><?= Html::encode(Yii::$app->formatter->asDatetime((int) $model->invoice_timestamp, 'php:d/m/Y H:i:s')) ?></div>
        </div>
        <dl class="row mb-0">
            <dt class="col-sm-3"><?= Html::encode($model->getAttributeLabel('from_address')) ?></dt>
            <dd class="col-sm-9"><?= $explorerLink((string) $model->from_address, '/address/' . rawurlencode((string) $model->from_address)) ?></dd>
            <dt class="col-sm-3"><?= Html::encode($model->getAttributeLabel('to_address')) ?></dt>
            <dd class="col-sm-9"><?= $explorerLink((string) $model->to_address, '/address/' . rawurlencode((string) $model->to_address)) ?></dd>
            <dt class="col-sm-3"><?= Html::encode($model->getAttributeLabel('txhash')) ?></dt>
            <dd class="col-sm-9"><?= $explorerLink((string) $model->txhash, '/tx/' . rawurlencode((string) $model->txhash)) ?></dd>
            <dt class="col-sm-3"><?= Html::encode($model->getAttributeLabel('blocknumber')) ?></dt>
            <dd class="col-sm-9"><?= $model->blocknumber ? $explorerLink((string) hexdec((string) $model->blocknumber), '/block/' . hexdec((string) $model->blocknumber)) : '&ndash;' ?></dd>
            <?php if ((string) $model->message !== ''): ?>
                <dt class="col-sm-3"><?= Html::encode(Yii::t('app', 'memo')) ?></dt>
                <dd class="col-sm-9 text-break"><?= Html::encode($model->message) ?></dd>
            <?php endif ?>
            <dt class="col-sm-3"><?= Html::encode(Yii::t('app', 'Transaction ID')) ?></dt>
            <dd class="col-sm-9 small text-secondary text-break mb-0"><?= Html::encode(WebApp::encrypt($model->id)) ?></dd>
        </dl>
    </div>
</div>
