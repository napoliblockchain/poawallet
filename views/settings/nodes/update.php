<?php

/* @var $this yii\web\View */
/* @var $model app\models\Nodes */

$this->title = Yii::t('app', 'Node selection');
echo $this->render('/layouts/_card', ['title' => $this->title, 'body' => $this->render('_form', ['model' => $model])]);
