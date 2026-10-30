<?php

namespace app\components\errors;

/**
 * Web error handler that never dumps $_SERVER (see SafeFallbackTrait).
 */
class WebErrorHandler extends \yii\web\ErrorHandler
{
    use SafeFallbackTrait;
}
