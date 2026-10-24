<?php

use app\components\Settings;

$vapidPublicKey = Settings::vapid()?->public_key ?? '';
$urlSavesubscription = \yii\helpers\Url::to(['push/save']); // save subscription for push messages

$options = [
    'cryptURL' => \yii\helpers\Url::to(['/wallet/crypt']),
    'revealSeedURL' => \yii\helpers\Url::to(['/wallet/reveal-seed']),
    'messages' => [
        'seedNotFound' => Yii::t('app', 'Backup not found!'),
        'wrongPassword' => Yii::t('app', 'Wrong password.'),
        'tooManyAttempts' => Yii::t('app', 'Too many attempts, try again later.'),
    ],
    'expiringTime' => 5, // in test altrimenti inserisci 5 minuti
    //'vapidPublicKey' => \settings::load()->VapidPublic,
    // 'urlSavesubscription' => \yii\helpers\Url::to(['wallet/saveSubscription']),//save subscription for push messages
    // ...
];
$this->registerJs(
    "var yiiOptions = ".\yii\helpers\Json::htmlEncode($options).";",
    \yii\web\View::POS_HEAD,
    'yiiOptions'
);
