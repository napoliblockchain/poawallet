<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model app\models\SmartContracts */

$this->title = Yii::t('app', 'Token details');
$body = DetailView::widget([
    'model' => $model,
    'options' => ['class' => 'table table-vcenter card-table mb-0'],
    'attributes' => [
        'denomination',
        ['attribute' => 'smart_contract_address', 'contentOptions' => ['class' => 'text-break font-monospace']],
        'contractType.denomination',
        'decimals',
        'symbol',
        'blockchain.denomination',
    ],
]);
echo $this->render('/layouts/_card', ['title' => $this->title . ': ' . $model->denomination, 'flush' => true, 'body' => $body,
    'actions' => Html::a('<i class="fas fa-check me-1" aria-hidden="true"></i>' . Html::encode(Yii::t('app', 'Select')), ['/nodes/update'], ['class' => 'btn btn-sm btn-outline-primary'])
        . Html::a('<i class="fas fa-edit me-1" aria-hidden="true"></i>' . Html::encode(Yii::t('app', 'Update')), ['update', 'id' => $model->id], ['class' => 'btn btn-sm btn-primary']),
    'footer' => Html::a('<i class="fas fa-trash me-1" aria-hidden="true"></i>' . Html::encode(Yii::t('app', 'Delete')), ['delete', 'id' => $model->id], [
        'class' => 'btn btn-sm btn-outline-danger',
        'data' => ['confirm' => Yii::t('app', 'Are you sure you want to delete this item?'), 'method' => 'post'],
    ])]);
