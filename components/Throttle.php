<?php
// Origin: Napay components/Throttle.php, copied unchanged for SIP-035.

namespace app\components;

use Yii;

/**
 * Rate limit a finestra fissa su cache + mutex (stesso schema di EmailRequestForm), condiviso dalle API.
 */
final class Throttle
{
    /**
     * @param array{0: int, 1: int} $limit [massimo, secondi]
     * @return int secondi di attesa (0 = consentito); $count=false controlla senza conteggiare
     */
    public static function hit(string $key, array $limit, bool $count = true): int
    {
        [$max, $window] = $limit;
        $mutex = Yii::$app->mutex;
        if (!$mutex->acquire('api-throttle', 2)) return 1;
        try {
            $cache = Yii::$app->cache;
            $cacheKey = 'throttle-' . hash('sha256', $key);
            $now = time();
            $state = $cache->get($cacheKey);
            if (!is_array($state) || ($state['reset'] ?? 0) <= $now) $state = ['count' => 0, 'reset' => $now + $window];
            if ($state['count'] >= $max) return max(1, $state['reset'] - $now);
            if ($count) {
                $state['count']++;
                $cache->set($cacheKey, $state, $state['reset'] - $now);
            }
            return 0;
        } finally {
            $mutex->release('api-throttle');
        }
    }

    public static function reset(string $key): void
    {
        Yii::$app->cache->delete('throttle-' . hash('sha256', $key));
    }
}
