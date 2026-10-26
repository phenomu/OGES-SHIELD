<?php
/**
 * seed_admin.php — CLI: buat/upgrade 1 akun superadmin.
 * Pakai: php scripts/seed_admin.php <email> <username> <name> [password]
 * Kalau password tidak diberikan → generate random & print sekali.
 * Idempotent: kalau email sudah ada → update role/password (bukan insert baru).
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/src/Core/Env.php';
require BASE_PATH . '/src/Core/Database.php';

Env::load(BASE_PATH . '/.env');

if (PHP_SAPI !== 'cli' || $argc < 4) {
    fwrite(STDERR, "Pakai: php scripts/seed_admin.php <email> <username> <name> [password]\n");
    exit(1);
}

[, $email, $username, $name] = $argv;
$password = $argv[4] ?? bin2hex(random_bytes(8)); // 16 char hex

$email = strtolower(trim($email));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Email tidak valid: {$email}\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);

$existing = Database::run('SELECT id, role FROM users WHERE email = ? OR username = ?', [$email, $username])->fetch();

if ($existing) {
    Database::run(
        "UPDATE users SET role = 'superadmin', password_hash = ?, name = ?, approved_at = NOW()
         WHERE id = ?",
        [$hash, $name, (int) $existing['id']]
    );
    echo "Superadmin di-update (id={$existing['id']}, role lama={$existing['role']})\n";
} else {
    Database::run(
        "INSERT INTO users (name, email, username, password_hash, role, approved_at)
         VALUES (?, ?, ?, ?, 'superadmin', NOW())",
        [$name, $email, $username, $hash]
    );
    echo "Superadmin dibuat.\n";
}

echo "Email    : {$email}\n";
echo "Username : {$username}\n";
echo "Password : {$password}\n";
echo "Simpan password ini — tidak disimpan dalam bentuk plaintext di mana pun.\n";
