<?php

use yii\helpers\Html;
use yii\grid\GridView;

$this->title = Yii::t('app', 'Logs');
?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><?= Html::encode($this->title) ?></h3>
    </div>
    <div class="card-body p-0">
        <?= GridView::widget([
            'dataProvider' => $dataProvider,
            'filterModel'  => $searchModel,
            'columns' => [
                'id',
                [
                    'attribute' => 'timestamp',
                    'value'     => fn($m) => date('Y-m-d H:i:s', $m->timestamp),
                    'filter'    => false,
                ],
                'remote_address',
                'browser:ntext',
                'description:ntext',
                ['class' => 'yii\grid\ActionColumn', 'template' => '{view} {delete}'],
            ],
        ]) ?>
    </div>
</div>
