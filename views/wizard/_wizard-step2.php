<?php

use yii\helpers\Html;
?>
<div class="text-center mb-3 text-primary"><i class="fas fa-key fa-2x" aria-hidden="true"></i></div>
<p class="lead"><?= Html::encode(Yii::t('app', 'So, 12 words will be chosen that uniquely identify your wallet.')) ?></p>
<p class="text-secondary"><?= Html::encode(Yii::t('app', 'The combination of these random words is unique in the world and will allow you to recover the contents of your wallet even in case of loss of your device.')) ?></p>
<div class="d-flex justify-content-between gap-2 mt-4">
    <button type="button" id="stepwizard_step2_prev" class="btn btn-outline-secondary prev-step"><i class="fa fa-arrow-left me-2" aria-hidden="true"></i><?= Html::encode(Yii::t('app', 'Previous')) ?></button>
    <button type="button" id="stepwizard_step2_next" class="btn btn-primary next-step"><?= Html::encode(Yii::t('app', 'Next')) ?><i class="fa fa-arrow-right ms-2" aria-hidden="true"></i></button>
</div>
