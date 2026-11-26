<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

$this->title = Yii::t('app', 'Log') . ' #' . $model->id;
?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title"><?= Html::encode($this->title) ?></h3>
        <?= Html::a(Yii::t('app', 'Delete'), ['delete', 'id' => $model->id], [
            'class' => 'btn btn-sm btn-danger',
            'data'  => ['confirm' => Yii::t('app', 'Are you sure?'), 'method' => 'post'],
        ]) ?>
    </div>
    <div class="card-body">
        <?= DetailView::widget([
            'model' => $model,
            'attributes' => [
                'id',
                ['attribute' => 'timestamp', 'value' => date('Y-m-d H:i:s', $model->timestamp)],
                'remote_address',
                'browser',
                'description:ntext',
            ],
        ]) ?>
    </div>
</div>
