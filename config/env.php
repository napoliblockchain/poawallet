<?php
/**
 * Lettura della configurazione da variabili d'ambiente (file .env passato al container).
 *
 * Uso:
 *   $env = require __DIR__ . '/env.php';
 *   $env('DB_DSN');                  // obbligatoria: se manca l'avvio fallisce
 *   $env('GOOGLE_CLIENT_ID', null);  // facoltativa, con default
 *
 * Il messaggio di errore riporta solo il nome della variabile, mai il valore.
 */
return static function (string $name, ...$default) {
    $value = getenv($name);

    if ($value === false || $value === '') {
        if ($default === []) {
            throw new \RuntimeException("Missing required environment variable: {$name}");
        }
        return $default[0];
    }

    return $value;
};
