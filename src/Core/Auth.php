<?php
/**
 * Auth — login/logout + guard middleware (Bagian 7 PROMPT).
 * Role: pending (tidak bisa login) · user · superadmin · rejected.
 */

declare(strict_types=1);

final class Auth
{
    /** @var array<string, mixed>|null cache user yang sedang login */
    private static ?array $user = null;

    /**
     * Attempt login. Side effect: sukses → sesi terpasang (regenerate id).
     * @return array{ok: bool, status: string, error: ?string}
     *         status: ok | pending | rejected | invalid
     */
    public static function attempt(string $identifier, string $password): array
    {
        $identifier = trim($identifier);
        if ($identifier === '' || $password === '') {
            return ['ok' => false, 'status' => 'invalid', 'error' => 'Email/username dan password wajib diisi.'];
        }

        $user = User::findByIdentifier($identifier);
        if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
            Audit::log(null, 'login_failed', ['identifier' => $identifier]);
            return ['ok' => false, 'status' => 'invalid', 'error' => 'Email/username atau password salah.'];
        }

        // role check — pesan jelas per state (Bagian 4.10/7)
        if ($user['role'] === 'pending') {
            Audit::log((int) $user['id'], 'login_blocked_pending', []);
            return ['ok' => false, 'status' => 'pending', 'error' => 'Akun kamu masih menunggu persetujuan superadmin — belum bisa login.'];
        }
        if ($user['role'] === 'rejected') {
            Audit::log((int) $user['id'], 'login_blocked_rejected', []);
            $reason = $user['rejection_reason'] ? ' Alasan: ' . (string) $user['rejection_reason'] : '';
            return ['ok' => false, 'status' => 'rejected', 'error' => 'Akun kamu ditolak superadmin.' . $reason];
        }

        self::login($user);
        Audit::log((int) $user['id'], 'login_success', ['role' => $user['role']]);
        return ['ok' => true, 'status' => 'ok', 'error' => null];
    }

    /** Simpan sesi login + regenerate id (anti session fixation). */
    public static function login(array $user): void
    {
        Session::regenerate();
        $_SESSION['user_id'] = (int) $user['id'];
        self::$user = null;
    }

    public static function logout(): void
    {
        if (self::user()) {
            Audit::log((int) self::user()['id'], 'logout', []);
        }
        Session::destroy();
        self::$user = null;
    }

    /** User yang sedang login (lazy dari DB), atau null. */
    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }
        $userId = $_SESSION['user_id'] ?? null;
        if (!is_int($userId)) {
            return null;
        }
        $row = Database::run(
            'SELECT id, name, email, username, role, created_at, approved_at
             FROM users WHERE id = ? LIMIT 1',
            [$userId]
        )->fetch();
        if (!$row || in_array($row['role'], ['pending', 'rejected'], true)) {
            return null; // akun didowngrade setelah login → paksa logout
        }
        self::$user = $row;
        return $row;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isSuperadmin(): bool
    {
        return (self::user()['role'] ?? '') === 'superadmin';
    }

    /** Guard: wajib login — kalau belum, redirect /login. */
    public static function requireLogin(): void
    {
        if (!self::check()) {
            redirect('/login');
        }
    }

    /** Guard: wajib superadmin — server-side (Bagian 7), bukan cuma sembunyi menu. */
    public static function requireSuperadmin(): void
    {
        self::requireLogin();
        if (!self::isSuperadmin()) {
            http_response_code(403);
            View::page('403');
            exit;
        }
    }

    /** Label role untuk tampilan. */
    public static function roleLabel(string $role): string
    {
        return match ($role) {
            'superadmin' => 'Superadmin',
            'user'       => 'Security Researcher',
            'pending'    => 'Pending Review',
            'rejected'   => 'Rejected',
            default      => ucfirst($role),
        };
    }
}
