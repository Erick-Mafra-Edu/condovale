<?php

namespace App\Http\Utils;

class SanitizeUtil
{
    public static function sanitizeString($string): string
    {
        return htmlspecialchars(strip_tags($string), ENT_QUOTES, 'UTF-8');
    }

    public static function sanitizeInt($int): int
    {
        // FILTER_SANITIZE_NUMBER_INT returns a string and keeps "+" and "-",
        // so the cast is what actually guarantees an integer id.
        return (int) filter_var($int, FILTER_SANITIZE_NUMBER_INT);
    }

    public static function sanitizeEmail($email)
    {
        return filter_var($email, FILTER_SANITIZE_EMAIL);
    }
}
