<?php
/** reset_tester_pass.php — reset password tester@oges.local (dev only). */
require __DIR__ . '/../src/Core/Env.php';
Env::load(__DIR__ . '/../.env');
$pdo = new PDO(
    'mysql:host=' . Env::get('DB_HOST', '127.0.0.1') . ';dbname=' . Env::get('DB_NAME') . ';charset=utf8mb4',
    Env::get('DB_USER'),
    Env::get('DB_PASS')
);
$hash = password_hash('tester-pass-123', PASSWORD_BCRYPT);
$st = $pdo->prepare('UPDATE users SET password_hash = ? WHERE email = ?');
$st->execute([$hash, 'tester@oges.local']);
echo "updated rows: ", $st->rowCount(), "\n";
echo password_verify('tester-pass-123', $hash) ? "verify OK\n" : "verify BAD\n";
