<?php

use yii\helpers\Html;
?>
<div class="text-center mb-3 text-primary"><i class="fas fa-lock fa-2x" aria-hidden="true"></i></div>
<div class="alert alert-warning" role="alert"><strong><?= Html::encode(Yii::t('app', 'But be careful, keep them in a safe place \'cause anyone who gets hold of this key can access its contents.')) ?></strong></div>
<p class="text-secondary"><?= Html::encode(Yii::t('app', 'Remember to make a backup of your digital wallet. This is an important step in securing your asset.')) ?></p>
<div class="d-flex justify-content-between gap-2 mt-4">
    <button type="button" id="stepwizard_step3_prev" class="btn btn-outline-secondary prev-step"><i class="fa fa-arrow-left me-2" aria-hidden="true"></i><?= Html::encode(Yii::t('app', 'Previous')) ?></button>
    <button type="button" id="stepwizard_step3_next" class="btn btn-primary next-step"><?= Html::encode(Yii::t('app', 'Next')) ?><i class="fa fa-arrow-right ms-2" aria-hidden="true"></i></button>
</div>
