<?php

use yii\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */
/* @var $fromAddress string */
/* @var $balance string */
/* @var $balance_gas string */
/* @var $node object rete e token da ChainConfig (SIP-032) */

$this->title = Yii::t('app', 'Wallet');

include('index_js.php');
?>
<?= $this->render('_balance', ['balance' => $balance, 'balance_gas' => $balance_gas, 'node' => $node]) ?>

<nav class="wallet-actions mb-4" aria-label="<?= Html::encode(Yii::t('app', 'Actions')) ?>">
    <a class="wallet-action" href="<?= Url::to(['/send/index']) ?>"><i class="far fa-paper-plane" aria-hidden="true"></i><?= Html::encode(Yii::t('app', 'Send')) ?></a>
    <a class="wallet-action" href="<?= Url::to(['/receive/index']) ?>"><i class="fas fa-qrcode" aria-hidden="true"></i><?= Html::encode(Yii::t('app', 'Receive')) ?></a>
    <a class="wallet-action" href="<?= Url::to(['/transactions/index']) ?>"><i class="fas fa-list" aria-hidden="true"></i><?= Html::encode(Yii::t('app', 'Transactions')) ?></a>
</nav>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><?= Html::encode(Yii::t('app', 'Recent Transactions')) ?></h3>
        <div class="card-actions"><?= Html::a(Html::encode(Yii::t('app', 'See all')), ['/transactions/index'], ['class' => 'small']) ?></div>
    </div>
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'showHeader' => false,
        'layout' => '{items}',
        'emptyText' => Yii::t('app', 'No transactions yet.'),
        'emptyTextOptions' => ['class' => 'text-secondary text-center py-4'],
        'tableOptions' => ['class' => 'table-98 table wallet-tx-table mb-0'],
        'options' => ['class' => 'grid-view'],
        'columns' => [[
            'format' => 'raw',
            'value' => static fn($data) => app\components\WebApp::showTransactionRow($data, $fromAddress),
        ]],
    ]) ?>
</div>
