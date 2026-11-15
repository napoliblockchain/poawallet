<?php

/* @var $this yii\web\View */
/* @var $model yii\db\ActiveRecord */

$this->title = Yii::t('app', 'Insert Token');
echo $this->render('/layouts/_card', ['title' => $this->title, 'body' => $this->render('_form', ['model' => $model])]);
