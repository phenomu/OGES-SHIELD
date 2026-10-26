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
