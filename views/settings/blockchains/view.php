<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model app\models\Blockchains */

$this->title = $model->denomination;
$body = DetailView::widget([
    'model' => $model,
    'options' => ['class' => 'table table-vcenter card-table mb-0'],
    'attributes' => ['denomination', 'url', 'chain_id', 'symbol', 'url_block_explorer'],
]);
echo $this->render('/layouts/_card', ['title' => $this->title, 'flush' => true, 'body' => $body,
    'actions' => Html::a('<i class="fas fa-edit me-1" aria-hidden="true"></i>' . Html::encode(Yii::t('app', 'Update')), ['update', 'id' => $model->id], ['class' => 'btn btn-sm btn-primary'])]);
