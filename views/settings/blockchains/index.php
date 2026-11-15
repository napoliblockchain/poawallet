<?php

use yii\grid\GridView;
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = Yii::t('app', 'Networks');
$body = ($dataProvider->totalCount == 0 ? '<div class="alert alert-info m-3">' . Html::encode(Yii::t('app', 'You must insert a Networks node')) . '</div>' : '')
    . GridView::widget([
        'dataProvider' => $dataProvider,
        'layout' => "{items}\n<div class=\"card-footer d-flex justify-content-between align-items-center\">{summary}{pager}</div>",
        'tableOptions' => ['class' => 'table table-vcenter card-table'],
        'columns' => [
            ['attribute' => 'denomination', 'format' => 'raw', 'value' => static fn($data) => Html::a(Html::encode($data->denomination), ['view', 'id' => $data->id])],
            'chain_id',
            'symbol',
        ],
    ]);
echo $this->render('/layouts/_card', ['title' => $this->title, 'flush' => true, 'body' => $body,
    'actions' => Html::a('<i class="fas fa-plus me-1" aria-hidden="true"></i>' . Html::encode(Yii::t('app', 'Add Network')), ['create'], ['class' => 'btn btn-sm btn-primary'])]);
