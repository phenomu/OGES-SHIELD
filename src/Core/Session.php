<?php
/**
 * Session — wrapper session PHP native + CSRF token + flash message.
 * Bagian 7 PROMPT: session_start native, regenerate id saat login.
 */

declare(strict_types=1);

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name('oges_session');
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => (($_SERVER['HTTPS'] ?? '') !== '') && ($_SERVER['HTTPS'] !== 'off'),
        ]);
        session_start();
    }

    /** Regenerate session id (dipanggil saat login sukses — anti session fixation). */
    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    // ---- CSRF ----

    /** Ambil (atau buat) token CSRF sesi ini. */
    public static function csrf(): string
    {
        if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    /**
     * Validasi CSRF token. false = tolak request.
     * Argumen opsional: token eksplisit (dari $_GET untuk link download,
     * mis. export CSV) — default membaca $_POST['csrf'].
     */
    public static function checkCsrf(?string $explicitToken = null): bool
    {
        $token = $explicitToken ?? ($_POST['csrf'] ?? '');
        $known = $_SESSION['csrf'] ?? '';
        return is_string($token) && is_string($known) && $known !== '' && $token !== '' && hash_equals($known, $token);
    }

    // ---- Flash message (sekali baca) ----

    public static function flash(string $key, ?string $message = null): ?string
    {
        if ($message !== null) {
            $_SESSION['flash'][$key] = $message;
            return null;
        }
        $message = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $message;
    }
}
