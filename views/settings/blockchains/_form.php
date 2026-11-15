<?php

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;
use app\components\WebApp;

/* @var $this yii\web\View */
/* @var $model app\models\Blockchains */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="blockchains-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->errorSummary($model, ['class' => 'alert alert-danger']) ?>

    <?= $form->field($model, 'denomination')->textInput(['maxlength' => true])->label(Yii::t('app','Denomination')) ?>

    <?= $form->field($model, 'url')->textInput() ?>

    <?= $form->field($model, 'chain_id')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'symbol')->textInput(['maxlength' => true]) ?>
    <?= $form->field($model, 'url_block_explorer')->textInput(['maxlength' => true]) ?>
    <?= $form->field($model, 'id_user')->hiddenInput(['value' => Yii::$app->user->id])->label(false) ?>


    <div class="mt-4">
        <?= Html::submitButton('<i class="fas fa-save me-1" aria-hidden="true"></i>' . Html::encode(Yii::t('app', 'Save')), ['class' => 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
