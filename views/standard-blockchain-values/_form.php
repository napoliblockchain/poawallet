<?php

use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model app\models\StandardBlockchainValues */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="standard-blockchain-values-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'denomination')->textInput(['maxlength' => true]) ?>
    <?= $form->field($model, 'chain_id')->textInput(['maxlength' => true]) ?>
    <?= $form->field($model, 'url')->textInput(['maxlength' => true]) ?>
    <?= $form->field($model, 'symbol')->textInput(['maxlength' => true]) ?>
    <?= $form->field($model, 'url_block_explorer')->textInput(['maxlength' => true]) ?>
    <?= $form->field($model, 'zerogas')->textInput() ?>

    <div class="mt-4">
        <?= Html::submitButton('<i class="fas fa-save me-1" aria-hidden="true"></i>' . Html::encode(Yii::t('app', 'Save')), ['class' => 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
