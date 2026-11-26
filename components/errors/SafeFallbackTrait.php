<?php

namespace app\components\errors;

use Yii;

/**
 * Same as Yii's fallback handler (error raised while handling another error) without the $_SERVER dump:
 * configuration and secrets come from environment variables, so $_SERVER contains them and Yii would
 * write them to error_log (container logs) when YII_DEBUG is on.
 */
trait SafeFallbackTrait
{
    protected function handleFallbackExceptionMessage($exception, $previousException)
    {
        $msg = "An Error occurred while handling another error:\n";
        $msg .= (string) $exception;
        $msg .= "\nPrevious exception:\n";
        $msg .= (string) $previousException;
        if (YII_DEBUG) {
            if (PHP_SAPI === 'cli') {
                echo $msg . "\n";
            } else {
                echo '<pre>' . htmlspecialchars($msg, ENT_QUOTES, Yii::$app->charset) . '</pre>';
            }
        } else {
            echo 'An internal server error occurred.';
        }
        error_log($msg);
        exit(1);
    }
}
