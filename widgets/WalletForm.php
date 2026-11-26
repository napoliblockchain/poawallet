<?php

namespace app\widgets;

use yii\widgets\ActiveForm;

/** ActiveForm con le classi Bootstrap 5/Tabler (errore sull'input, messaggio sotto il campo). */
class WalletForm extends ActiveForm
{
    public $validationStateOn = self::VALIDATION_STATE_ON_INPUT;
    public $errorCssClass = 'is-invalid';
    public $successCssClass = '';
    public $fieldConfig = [
        'options' => ['class' => 'mb-3'],
        'labelOptions' => ['class' => 'form-label'],
        'inputOptions' => ['class' => 'form-control'],
        'errorOptions' => ['class' => 'invalid-feedback'],
    ];
}
