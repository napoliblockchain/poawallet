<?php
/**
 * Redis: canale realtime verso ws-notify (NotificationService::publishRealtime).
 * REDIS_HOST vuoto = componente non registrato, realtime disattivato.
 */
$env = require __DIR__ . '/env.php';

$host = $env('REDIS_HOST', null);
if ($host === null) {
    return null;
}

return [
    'class' => yii\redis\Connection::class,
    'hostname' => $host,
    'port' => (int) $env('REDIS_PORT', '6379'),
    'database' => (int) $env('REDIS_DATABASE', '0'),
    'password' => $env('REDIS_PASSWORD', null),
    'connectionTimeout' => 2,
];
