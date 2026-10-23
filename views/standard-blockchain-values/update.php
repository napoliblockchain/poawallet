<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model app\models\StandardBlockchainValues */

$this->title = Yii::t('app', 'Update Standard Blockchain Values: {name}', [
    'name' => $model->denomination,
]);
$this->params['breadcrumbs'][] = ['label' => Yii::t('app', 'Standard Blockchain Values'), 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = Yii::t('app', 'Update');
?>
<div class="row">
    <div class="col-lg-8 col-md-12">
        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title"><?= Html::encode($this->title) ?></h3>
            </div>
            <div class="card-body">
                <?= $this->render('_form', [
                    'model' => $model,
                ]) ?>
            </div>
        </div>
    </div>
</div>
