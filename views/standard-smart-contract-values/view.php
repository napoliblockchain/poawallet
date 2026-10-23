<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model app\models\StandardSmartContractValues */

$this->title = $model->denomination;
$this->params['breadcrumbs'][] = ['label' => Yii::t('app', 'Standard Smart Contract Values'), 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>
<div class="row">
    <div class="col-lg-8 col-md-12">
        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title"><?= Html::encode($this->title) ?></h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <?= DetailView::widget([
                        'model' => $model,
                        'attributes' => [
                            'id',
                            [
                                'attribute' => 'id_blockchain',
                                'value' => $model->blockchainStandard ? $model->blockchainStandard->denomination : null,
                            ],
                            [
                                'attribute' => 'id_contract_type',
                                'value' => $model->contractType ? $model->contractType->denomination : null,
                            ],
                            'smart_contract_address',
                            'decimals',
                            'symbol',
                        ],
                    ]) ?>
                </div>
                <div class="card-footer">
                    <?= Html::a(Yii::t('app', 'Update'), ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
                    <?= Html::a(Yii::t('app', 'Delete'), ['delete', 'id' => $model->id], [
                        'class' => 'btn btn-danger',
                        'data' => [
                            'confirm' => Yii::t('app', 'Are you sure you want to delete this item?'),
                            'method' => 'post',
                        ],
                    ]) ?>
                </div>
            </div>
        </div>
    </div>
</div>
