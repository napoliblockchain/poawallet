<?php

use app\components\Settings;

$vapidPublicKey = Settings::vapid()?->public_key ?? '';
$urlSavesubscription = \yii\helpers\Url::to(['push/save']); // save subscription for push messages

$options = [
    'cryptURL' => \yii\helpers\Url::to(['/wallet/crypt']),
    'revealSeedURL' => \yii\helpers\Url::to(['/wallet/reveal-seed']),
    'messages' => [
        'seedNotFound' => Yii::t('app', 'Backup not found!'),
        'wrongPin' => Yii::t('app', 'Wrong PIN.'),
        'tooManyAttempts' => Yii::t('app', 'Too many wrong attempts: the seed is locked for 24 hours.'),
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
