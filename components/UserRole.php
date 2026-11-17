<?php

namespace app\components;

use Yii;
use app\models\Profiles;

class UserRole
{
    public static function profile(): ?Profiles
    {
        $identity = Yii::$app->user->identity;
        if ($identity === null || empty($identity->privilege_id)) {
            return null;
        }
        return Profiles::findOne($identity->privilege_id);
    }

    public static function isWebmaster(): bool
    {
        $p = self::profile();
        return $p !== null && $p->resource === 'webmaster';
    }

    public static function isUser(): bool
    {
        $p = self::profile();
        return $p !== null && $p->resource === 'user';
    }

    public static function can(int $level): bool
    {
        $p = self::profile();
        return $p !== null && $p->level >= $level;
    }

    public static function privilegeLevel(): int
    {
        $p = self::profile();
        return $p !== null ? (int)$p->level : 0;
    }

    public static function privilegeName(): string
    {
        $p = self::profile();
        return $p !== null ? $p->description : '';
    }
}
