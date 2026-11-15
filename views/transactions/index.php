<?php

use yii\grid\GridView;
use yii\helpers\Html;
use yii\widgets\Pjax;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */
/* @var $fromAddress string */

$this->title = Yii::t('app', 'Transactions');
?>
<div class="card">
    <div class="card-header">
        <h2 class="card-title"><?= Html::encode(Yii::t('app', 'Transaction list')) ?></h2>
    </div>
    <?php Pjax::begin(); ?>
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'showHeader' => false,
        'layout' => "{items}\n<div class=\"card-footer d-flex justify-content-center\">{pager}</div>",
        'emptyText' => Yii::t('app', 'No transactions yet.'),
        'emptyTextOptions' => ['class' => 'text-secondary text-center py-4'],
        'tableOptions' => ['class' => 'table-96 table wallet-tx-table mb-0'],
        'pager' => ['class' => yii\bootstrap5\LinkPager::class],
        'columns' => [[
            'format' => 'raw',
            'value' => static fn($data) => app\components\WebApp::showTransactionRow($data, $fromAddress),
        ]],
    ]) ?>
    <?php Pjax::end(); ?>
</div>
