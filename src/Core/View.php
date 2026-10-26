<?php
/**
 * View — render template dari src/Views/.
 * - render() : template polos (404, dll)
 * - page()   : template di dalam shell app (topbar + sidebar) — halaman dashboard
 * - auth()   : template di dalam shell auth (login/register/pending)
 */

declare(strict_types=1);

final class View
{
    /** Judul halaman aktif — dipakai <title> + highlight sidebar. */
    public static string $pageTitle = 'OGES-SHIELD';
    /** Key menu sidebar yang aktif (match dengan key di partial sidebar). */
    public static string $activeNav = '';

    public static function render(string $template, array $data = []): void
    {
        echo self::capture($template, $data);
    }

    public static function page(string $template, array $data = []): void
    {
        $content = self::capture($template, $data);
        $content = self::injectCspNonce($content);
        require BASE_PATH . '/src/Views/layout/app.php';
    }

    public static function auth(string $template, array $data = []): void
    {
        $content = self::capture($template, $data);
        $content = self::injectCspNonce($content);
        require BASE_PATH . '/src/Views/layout/auth.php';
    }

    /** Tambah nonce CSP ke semua <script> inline (CSP script-src 'nonce-…'). */
    private static function injectCspNonce(string $html): string
    {
        $nonce = defined('CSP_NONCE') ? CSP_NONCE : '';
        if ($nonce === '') {
            return $html;
        }
        return (string) preg_replace(
            '/<script(?![^>]*(?:nonce=|src=))/i',
            '<script nonce="' . $nonce . '"',
            $html
        );
    }

    private static function capture(string $template, array $data = []): string
    {
        $file = BASE_PATH . '/src/Views/' . $template . '.php';
        if (!is_file($file)) {
            http_response_code(500);
            return 'View not found: ' . htmlspecialchars($template);
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }
}
