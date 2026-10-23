<?php

$this->title = Yii::t('app', 'Update Profile') . ': ' . $model->description;
?>
<div class="card"><div class="card-body">
    <?= $this->render('_form', compact('model')) ?>
</div></div>
