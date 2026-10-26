<?php
/**
 * Helper global kecil — dimuat via autoload convention (fungsi, bukan class).
 * Dideklarasikan di sini dan di-require manual dari index.php.
 */

declare(strict_types=1);

if (!function_exists('redirect')) {
    /** Redirect 302 + stop eksekusi. */
    function redirect(string $to): void
    {
        header('Location: ' . $to, true, 302);
        exit;
    }
}

if (!function_exists('e')) {
    /** htmlspecialchars shortcut. */
    function e(?string $s): string
    {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('mbTrunc')) {
    /** Truncate UTF-8 aman (tanpa ekstensi mbstring) + ellipsis. */
    function mbTrunc(?string $s, int $max): string
    {
        $s = (string) $s;
        if (function_exists('mb_substr')) {
            return mb_strlen($s) > $max ? mb_substr($s, 0, $max - 1) . '…' : $s;
        }
        return strlen($s) > $max ? substr($s, 0, $max - 1) . '…' : $s;
    }
}
