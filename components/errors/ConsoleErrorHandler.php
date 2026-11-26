<?php

namespace app\components\errors;

/**
 * Console error handler that never dumps $_SERVER (see SafeFallbackTrait).
 */
class ConsoleErrorHandler extends \yii\console\ErrorHandler
{
    use SafeFallbackTrait;
}
