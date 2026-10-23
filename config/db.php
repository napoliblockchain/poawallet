<?php
$env = require __DIR__ . '/env.php';

return [
    'class' => 'yii\db\Connection',
    'dsn' => $env('DB_DSN'),
    'username' => $env('DB_USERNAME'),
    'password' => $env('DB_PASSWORD'),
    'charset' => 'utf8',
];
