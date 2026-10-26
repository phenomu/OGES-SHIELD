<?php
/**
 * User model — akses tabel users (prepared statements semua).
 */

declare(strict_types=1);

final class User
{
    /**
     * Cari user by id.
     * @return array<string, mixed>|null
     */
    public static function findById(int $id): ?array
    {
        $row = Database::run(
            'SELECT id, name, email, username, role, rejection_reason, created_at, approved_at
             FROM users WHERE id = ? LIMIT 1',
            [$id]
        )->fetch();
        return $row ?: null;
    }

    /**
     * Cari user by email ATAU username (untuk login).
     * @return array<string, mixed>|null
     */
    public static function findByIdentifier(string $identifier): ?array
    {
        $row = Database::run(
            'SELECT id, name, email, username, password_hash, role, rejection_reason,
                    created_at, approved_at
             FROM users
             WHERE email = ? OR username = ?
             LIMIT 1',
            [$identifier, $identifier]
        )->fetch();

        return $row ?: null;
    }

    /**
     * Register user baru — selalu role `pending` (Bagian 7 PROMPT).
     * @return array{ok: bool, error: ?string, userId: ?int}
     */
    public static function register(string $name, string $email, string $username, string $password, string $reason): array
    {
        // normalisasi
        $name     = trim($name);
        $email    = strtolower(trim($email));
        $username = trim($username);
        $reason   = trim($reason);

        // validasi server-side (mirror validasi client)
        if (strlen($name) < 3)                     return ['ok' => false, 'error' => 'Nama minimal 3 karakter.', 'userId' => null];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'Email tidak valid.', 'userId' => null];
        if (!preg_match('/^[a-zA-Z0-9_.]{3,60}$/', $username)) return ['ok' => false, 'error' => 'Username 3-60 karakter (huruf/angka/_/.).', 'userId' => null];
        if (strlen($password) < 8)                   return ['ok' => false, 'error' => 'Password minimal 8 karakter.', 'userId' => null];
        if (strlen($reason) < 20)                  return ['ok' => false, 'error' => 'Justifikasi akses minimal 20 karakter.', 'userId' => null];

        // duplikat? (pesan spesifik biar user paham)
        $dupEmail = Database::run('SELECT id FROM users WHERE email = ?', [$email])->fetch();
        if ($dupEmail) {
            return ['ok' => false, 'error' => 'Email sudah terdaftar — coba login atau pakai email lain.', 'userId' => null];
        }
        $dupUser = Database::run('SELECT id FROM users WHERE username = ?', [$username])->fetch();
        if ($dupUser) {
            return ['ok' => false, 'error' => 'Username sudah dipakai.', 'userId' => null];
        }

        Database::run(
            'INSERT INTO users (name, email, username, password_hash, role, registration_reason)
             VALUES (?, ?, ?, ?, "pending", ?)',
            [$name, $email, $username, password_hash($password, PASSWORD_DEFAULT), substr($reason, 0, 500)]
        );

        $userId = (int) Database::pdo()->lastInsertId();
        Audit::log($userId, 'register_requested', [
            'email' => $email, 'username' => $username,
        ]);

        return ['ok' => true, 'error' => null, 'userId' => $userId];
    }

    /** Daftar user role=pending (untuk halaman approvals). */
    public static function pendingList(): array
    {
        return Database::run(
            "SELECT id, name, email, username, role, created_at
             FROM users
             WHERE role = 'pending'
             ORDER BY created_at ASC"
        )->fetchAll();
    }

    /** Approve: pending → user. @return bool sukses. */
    public static function approve(int $userId, int $adminId): bool
    {
        $st = Database::run(
            "UPDATE users
             SET role = 'user', approved_at = NOW(), approved_by = ?, rejection_reason = NULL
             WHERE id = ? AND role = 'pending'",
            [$adminId, $userId]
        );
        return $st->rowCount() === 1;
    }

    /** Reject: pending → rejected + alasan (tampil saat user coba login). */
    public static function reject(int $userId, string $reason): bool
    {
        $st = Database::run(
            "UPDATE users
             SET role = 'rejected', rejection_reason = ?, approved_at = NULL, approved_by = NULL
             WHERE id = ? AND role = 'pending'",
            [substr(trim($reason), 0, 500) ?: 'Tidak memenuhi kriteria akses.', $userId]
        );
        return $st->rowCount() === 1;
    }
}
