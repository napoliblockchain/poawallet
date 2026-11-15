<?php

use yii\helpers\Html;
use yii\helpers\ArrayHelper;
use app\models\Blockchains;
use app\models\SmartContracts;
use yii\bootstrap5\ActiveForm;

/* @var $this yii\web\View */
/* @var $model app\models\Nodes */

// echo '<pre>'.print_r($model,true).'</pre>';
// exit;

$blockchains = ArrayHelper::map(Blockchains::find()->byUserId($model->id_user)->all(), 'id', 'denomination');

$smartcontract = ArrayHelper::map(SmartContracts::find()
    ->byUserId($model->id_user)
    ->byBlockchainId($model->id_blockchain)
    ->all(), 'id', 'denomination');

include ('_formjs.php');
?>

<div class="nodes-form">

    <?php $form = ActiveForm::begin(); ?>

    <?php if (Yii::$app->session->hasFlash('nodeSelectedsuccess')): ?>
            <div class="alert alert-success" role="status"><?= Html::encode(Yii::$app->session->getFlash('nodeSelectedsuccess')) ?></div>
        <?php endif; ?>
        <?= $form->errorSummary($model, ['class' => 'alert alert-danger']) ?>

    <?= $form->field($model, 'id_blockchain')->dropDownList($blockchains) ?>
    <?= $form->field($model, 'id_smart_contract')->dropDownList($smartcontract) ?>
    <?= $form->field($model, 'id_user')->hiddenInput(['value' => Yii::$app->user->id])->label(false) ?>


    <div class="mt-4">
        <?= Html::submitButton('<i class="fas fa-save me-1" aria-hidden="true"></i>' . Html::encode(Yii::t('app', 'Save')), ['class' => 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
