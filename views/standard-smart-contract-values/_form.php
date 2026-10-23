<?php

use yii\bootstrap4\ActiveForm;
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model app\models\StandardSmartContractValues */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="standard-smart-contract-values-form">

    <?php $form = ActiveForm::begin(); ?>

    <div class="txt-left">
        <?= $form->errorSummary($model, ['id' => 'error-summary', 'class' => 'col-lg-12 callout callout-danger']) ?>
    </div>

    <?= $form->field($model, 'id_blockchain')->dropDownList(
        $blockchain_list,
        ['prompt' => ' ' . Yii::t('app', 'Select blockchain')]
    ) ?>

    <?= $form->field($model, 'smart_contract_address')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'id_contract_type')->dropDownList(
        $contractType_list,
        ['prompt' => ' ' . Yii::t('app', 'Select contract type')]
    ) ?>

    <?= $form->field($model, 'denomination')->textInput(['maxlength' => true]) ?>
    <?= $form->field($model, 'decimals')->textInput() ?>
    <?= $form->field($model, 'symbol')->textInput(['maxlength' => true]) ?>

    <div class="form-group">
        <?= Html::submitButton('<i class="fas fa-save"></i> ' . Yii::t('app', 'Save'), ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
