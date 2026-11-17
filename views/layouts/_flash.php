<?php

/* Messaggi flash di sessione come avvisi Tabler, testo sempre codificato. */

use yii\helpers\Html;

$types = ['error' => 'danger', 'danger' => 'danger', 'success' => 'success', 'info' => 'info', 'warning' => 'warning'];
foreach (Yii::$app->session->getAllFlashes() as $type => $messages) {
    if (!isset($types[$type])) continue;
    foreach ((array) $messages as $message) {
        if (!is_scalar($message) || trim((string) $message) === '') continue;
        echo Html::tag('div', Html::encode((string) $message), ['class' => 'alert alert-' . $types[$type], 'role' => 'alert']);
    }
    Yii::$app->session->removeFlash($type);
}
