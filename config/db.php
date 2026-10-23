<?php
$secrets = require __DIR__ . '/secrets.php';

return [
    'class' => 'yii\db\Connection',
    'dsn' => getenv('DB_DSN') ?: $secrets['db_dsn'],
    'username' => getenv('DB_USERNAME') ?: $secrets['db_username'],
    'password' => getenv('DB_PASSWORD') ?: $secrets['db_password'],
    'charset' => 'utf8',
];
