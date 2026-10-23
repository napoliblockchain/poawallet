<?php

use yii\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $searchModel app\models\search\StandardBlockchainValuesSearch */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = Yii::t('app', 'Standard Blockchain Values');
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="row">
    <div class="col-lg-12 col-md-12">
        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title"><?= Html::encode($this->title) ?></h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <?= GridView::widget([
                        'dataProvider' => $dataProvider,
                        'columns' => [
                            'id',
                            'denomination',
                            'chain_id',
                            'url:url',
                            'symbol',
                            'url_block_explorer:url',
                            'zerogas',
                            [
                                'class' => 'yii\grid\ActionColumn',
                                'template' => '<div class="d-flex flex-row"><div class="px-1">{view}</div><div class="px-1">{update}</div><div class="px-1">{delete}</div></div>',
                                'buttons' => [
                                    'view' => function ($url, $model) {
                                        return Html::a('<i class="fa fa-eye"></i>', Url::to(['view', 'id' => $model->id]), [
                                            'title' => Yii::t('app', 'View'),
                                            'class' => 'btn btn-primary btn-sm',
                                        ]);
                                    },
                                    'update' => function ($url, $model) {
                                        return Html::a('<i class="fa fa-pen"></i>', Url::to(['update', 'id' => $model->id]), [
                                            'title' => Yii::t('app', 'Update'),
                                            'class' => 'btn btn-warning btn-sm',
                                        ]);
                                    },
                                    'delete' => function ($url, $model) {
                                        return Html::a('<i class="fa fa-trash-alt"></i>', Url::to(['delete', 'id' => $model->id]), [
                                            'title' => Yii::t('app', 'Delete'),
                                            'class' => 'btn btn-danger btn-sm',
                                            'data-method' => 'post',
                                            'data-confirm' => Yii::t('app', 'Are you sure you want to delete this item?'),
                                            'data-pjax' => 0,
                                        ]);
                                    },
                                ],
                            ],
                        ],
                    ]); ?>
                </div>
            </div>
            <div class="card-footer">
                <?= Html::a(Yii::t('app', 'Create Standard Blockchain Values'), ['create'], ['class' => 'btn btn-success']) ?>
            </div>
        </div>
    </div>
</div>
