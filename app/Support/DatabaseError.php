<?php

namespace App\Support;

use Illuminate\Database\QueryException;

/**
 * Ma'lumotlar bazasi xatolarini drayverdan qat'i nazar aniqlash uchun yordamchi.
 */
final class DatabaseError
{
    /**
     * Xato unique constraint buzilishi sababli yuzaga kelganini bildiradi.
     */
    public static function isUniqueViolation(QueryException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'UNIQUE constraint failed')
            || str_contains($message, 'Duplicate entry')
            || str_contains($message, 'duplicate key value')
            || self::errorCode($exception) === 1062 // MySQL: ER_DUP_ENTRY
            || self::errorCode($exception) === 23505; // PostgreSQL: unique_violation
    }

    private static function errorCode(QueryException $exception): int
    {
        return (int) ($exception->errorInfo[1] ?? 0);
    }
}
