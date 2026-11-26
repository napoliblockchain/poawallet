<?php

use yii\helpers\Html;
use yii\grid\GridView;

$this->title = Yii::t('app', 'Profiles');
?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title"><?= Html::encode($this->title) ?></h3>
        <?= Html::a(Yii::t('app', 'Create'), ['create'], ['class' => 'btn btn-sm btn-success']) ?>
    </div>
    <div class="card-body p-0">
        <?= GridView::widget([
            'dataProvider' => $dataProvider,
            'filterModel'  => $searchModel,
            'columns' => [
                'id',
                'description',
                'level',
                'resource',
                ['class' => 'yii\grid\ActionColumn', 'template' => '{view} {update} {delete}'],
            ],
        ]) ?>
    </div>
</div>
