<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

$this->title = $model->description;
?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title"><?= Html::encode($this->title) ?></h3>
        <div>
            <?= Html::a(Yii::t('app', 'Update'), ['update', 'id' => $model->id], ['class' => 'btn btn-sm btn-primary']) ?>
            <?= Html::a(Yii::t('app', 'Delete'), ['delete', 'id' => $model->id], [
                'class' => 'btn btn-sm btn-danger',
                'data'  => ['confirm' => Yii::t('app', 'Are you sure?'), 'method' => 'post'],
            ]) ?>
        </div>
    </div>
    <div class="card-body">
        <?= DetailView::widget([
            'model' => $model,
            'attributes' => ['id', 'description', 'level', 'resource'],
        ]) ?>
    </div>
</div>
