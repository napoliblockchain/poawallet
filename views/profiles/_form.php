<?php

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;

?>
<?php $form = ActiveForm::begin() ?>
    <?= $form->field($model, 'description')->textInput(['maxlength' => true]) ?>
    <?= $form->field($model, 'level')->textInput() ?>
    <?= $form->field($model, 'resource')->textInput(['maxlength' => true]) ?>
    <div class="mt-4">
        <?= Html::submitButton('<i class="fas fa-save me-1" aria-hidden="true"></i>' . Html::encode(Yii::t('app', 'Save')), ['class' => 'btn btn-primary']) ?>
    </div>
<?php ActiveForm::end() ?>
