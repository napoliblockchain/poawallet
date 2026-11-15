<?php

/* @var $this yii\web\View */
/* @var $model app\models\Blockchains */

$this->title = Yii::t('app', 'Update Network: {name}', ['name' => $model->denomination]);
echo $this->render('/layouts/_card', ['title' => $this->title, 'body' => $this->render('_form', ['model' => $model])]);
