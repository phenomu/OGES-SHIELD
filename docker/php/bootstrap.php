<?php
/**
 * bootstrap.php — provisioning web container (idempotent, dijalankan entrypoint).
 *
 * Urutan:
 *   1. Tunggu MySQL bisa dihubungi.
 *   2. Kalau tabel `users` belum ada → terapkan database/schema.sql.
 *      (Perlu karena MySQL hanya menjalankan /docker-entrypoint-initdb.d saat
 *       datadir masih kosong. Kalau volume sudah pernah dibuat — mis. oleh
 *       scripts/run-db.sh — schema tidak pernah dieksekusi otomatis.)
 *   3. Kalau tabel `users` kosong → buat 1 superadmin dari env OGES_ADMIN_*.
 *
 * Statement CREATE DATABASE / USE dibuang saat apply, karena user aplikasi
 * (`oges`) hanya punya privilege di database-nya sendiri.
 */

declare(strict_types=1);

// Default /app (layout image). Bisa dioverride untuk uji lokal / image lain.
define('BASE_PATH', getenv('OGES_BASE_PATH') ?: '/app');

require BASE_PATH . '/src/Core/Env.php';
require BASE_PATH . '/src/Core/Database.php';

Env::load(BASE_PATH . '/.env');

const SCHEMA_FILE   = BASE_PATH . '/database/schema.sql';
const WAIT_ATTEMPTS = 45;   // ~90 detik
const WAIT_SLEEP    = 2;

// --------------------------------------------------------------- helpers ----

function logline(string $msg): void
{
    echo '[bootstrap] ' . $msg . "\n";
}

function fail(string $msg, int $code = 0): void
{
    fwrite(STDERR, '[bootstrap] ' . $msg . "\n");
    exit($code);
}

/** Tunggu koneksi PDO siap. */
function wait_for_db(): PDO
{
    $lastErr = 'unknown';
    for ($i = 1; $i <= WAIT_ATTEMPTS; $i++) {
        try {
            return Database::pdo();
        } catch (Throwable $e) {
            $lastErr = $e->getMessage();
            sleep(WAIT_SLEEP);
        }
    }
    fail('MySQL tidak bisa dihubungi setelah '
        . (WAIT_ATTEMPTS * WAIT_SLEEP) . "s: {$lastErr}", 1);
    exit(1); // unreachable — memuasakan static analyzer
}

/**
 * Cek keberadaan tabel.
 *
 * JANGAN pakai `SHOW TABLES LIKE ?` — MySQL tidak mengizinkan placeholder pada
 * statement SHOW (error 1064 "syntax error near '?'"). Query information_schema
 * memakai prepared statement normal.
 */
function table_exists(PDO $pdo, string $table): bool
{
    try {
        $st = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = ?'
        );
        $st->execute([$table]);
        return (int) $st->fetchColumn() > 0;
    } catch (Throwable $e) {
        fwrite(STDERR, "[bootstrap] WARN cek tabel '{$table}': {$e->getMessage()}\n");
        return false;
    }
}

/**
 * Pisahkan dump SQL jadi statement individual.
 * Menghormati string literal ('…', "…"), backtick identifier, dan komentar
 * (-- …, # …, hingga /* … *\/). Titik-koma di dalam konteks itu tidak dipotong.
 *
 * @return list<string>
 */
function split_sql(string $sql): array
{
    $out = [];
    $buf = '';
    $len = strlen($sql);
    $i = 0;

    while ($i < $len) {
        $ch   = $sql[$i];
        $next = $i + 1 < $len ? $sql[$i + 1] : '';

        // Komentar baris: -- … / # …
        if (($ch === '-' && $next === '-') || $ch === '#') {
            $nl = strpos($sql, "\n", $i);
            $i = $nl === false ? $len : $nl + 1;
            $buf .= "\n";
            continue;
        }

        // Komentar blok: /* … */
        if ($ch === '/' && $next === '*') {
            $end = strpos($sql, '*/', $i + 2);
            $i = $end === false ? $len : $end + 2;
            $buf .= ' ';
            continue;
        }

        // String / identifier ter-quote
        if ($ch === "'" || $ch === '"' || $ch === '`') {
            $quote = $ch;
            $buf .= $ch;
            $i++;
            while ($i < $len) {
                $c = $sql[$i];
                // escape backslash di dalam string (bukan identifier)
                if ($c === '\\' && $quote !== '`' && $i + 1 < $len) {
                    $buf .= $c . $sql[$i + 1];
                    $i += 2;
                    continue;
                }
                // quote ganda ('' atau "") = quote ter-escape
                if ($c === $quote && $i + 1 < $len && $sql[$i + 1] === $quote) {
                    $buf .= $c . $c;
                    $i += 2;
                    continue;
                }
                $buf .= $c;
                $i++;
                if ($c === $quote) {
                    break;
                }
            }
            continue;
        }

        if ($ch === ';') {
            $stmt = trim($buf);
            if ($stmt !== '') {
                $out[] = $stmt;
            }
            $buf = '';
            $i++;
            continue;
        }

        $buf .= $ch;
        $i++;
    }

    $stmt = trim($buf);
    if ($stmt !== '') {
        $out[] = $stmt;
    }
    return $out;
}

/** Terapkan schema.sql — lewati CREATE DATABASE / USE (privilege user app). */
function apply_schema(PDO $pdo): void
{
    if (!is_file(SCHEMA_FILE)) {
        fail('database/schema.sql tidak ditemukan di image.', 1);
    }

    $statements = split_sql((string) file_get_contents(SCHEMA_FILE));
    $applied = 0;
    $skipped = 0;

    foreach ($statements as $stmt) {
        $head = strtoupper(ltrim($stmt));
        if (str_starts_with($head, 'CREATE DATABASE') || str_starts_with($head, 'USE ')) {
            $skipped++;
            continue;
        }
        try {
            $pdo->exec($stmt);
            $applied++;
        } catch (Throwable $e) {
            // Statement yang gagal jangan disembunyikan — tampilkan di log supaya
            // troubleshooting tidak buta.
            fwrite(STDERR, '[bootstrap] WARN statement dilewati: '
                . substr(preg_replace('/\s+/', ' ', $stmt) ?? '', 0, 80)
                . ' → ' . $e->getMessage() . "\n");
        }
    }
    logline("schema diterapkan: {$applied} statement ({$skipped} di-skip).");
}

// ------------------------------------------------------------------- main --

$pdo = wait_for_db();
logline('MySQL terhubung.');

if (!table_exists($pdo, 'users')) {
    logline('tabel inti belum ada → menerapkan schema.sql...');
    apply_schema($pdo);
    $pdo = Database::pdo(); // reconnect agar metadata tabel segar
}

if (!table_exists($pdo, 'users')) {
    fail('schema gagal diterapkan: tabel `users` tetap tidak ada.', 1);
}

try {
    $count = (int) ($pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() ?: 0);
} catch (Throwable $e) {
    fail('tidak bisa membaca tabel users: ' . $e->getMessage(), 1);
}

/**
 * Baca env string dengan default yang aman.
 * Tidak pakai `?: default` karena "0" adalah string falsy — nilai valid akan
 * diam-diam diganti default.
 */
function env_str(string $key, string $default): string
{
    $v = Env::get($key);
    if ($v === null || $v === '') {
        return $default;
    }
    return (string) $v;
}

function env_bool(string $key, bool $default): bool
{
    $v = Env::get($key);
    if ($v === null || $v === '') {
        return $default;
    }
    return in_array(strtolower(trim((string) $v)), ['1', 'true', 'yes', 'on'], true);
}

// ------------------------------------------------------------- superadmin ----

if ($count > 0) {
    logline("{$count} user sudah ada — seed superadmin dilewati.");
    $adminId = (int) ($pdo->query(
        "SELECT id FROM users WHERE role = 'superadmin' ORDER BY id LIMIT 1"
    )->fetchColumn() ?: 0);
} else {
    $email    = env_str('OGES_ADMIN_EMAIL', 'admin@oges.local');
    $username = env_str('OGES_ADMIN_USER', 'admin');
    $name     = env_str('OGES_ADMIN_NAME', 'OGES Admin');
    $password = env_str('OGES_ADMIN_PASS', 'oges-admin-123');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        fail("OGES_ADMIN_EMAIL tidak valid: {$email}", 1);
    }

    $st = $pdo->prepare(
        "INSERT INTO users (name, email, username, password_hash, role, approved_at)
         VALUES (?, ?, ?, ?, 'superadmin', NOW())"
    );
    $st->execute([
        $name, strtolower($email), $username, password_hash($password, PASSWORD_DEFAULT),
    ]);
    $adminId = (int) $pdo->lastInsertId();

    logline('superadmin dibuat:');
    echo "  email    : {$email}\n";
    echo "  username : {$username}\n";
    echo "  password : {$password}\n";
}

// ---------------------------------------------------- target demo (opsional) --
// Tanpa ini user harus mendaftarkan target manual dulu sebelum "Set Workflow"
// bisa dipakai. Default aktif supaya `docker compose up` langsung terpakai.

if (env_bool('OGES_SEED_DEMO_TARGET', true) && $adminId > 0) {
    $demoValue = env_str('OGES_DEMO_TARGET', 'http://mockwp:8899/');
    $demoName  = env_str('OGES_DEMO_TARGET_NAME', 'Mock WordPress (demo)');

    try {
        $pdo->prepare(
            "INSERT INTO targets (user_id, name, type, value, environment, in_scope, notes)
             VALUES (?, ?, 'url', ?, 'lab', 1, 'Auto-seed container: target demo internal')
             ON DUPLICATE KEY UPDATE in_scope = 1, environment = 'lab'"
        )->execute([$adminId, $demoName, $demoValue]);
        logline("target demo in-scope: {$demoValue}");
    } catch (Throwable $e) {
        // Constraint / bentrok data — bukan alasan menggagalkan boot.
        fwrite(STDERR, '[bootstrap] WARN seed target demo: ' . $e->getMessage() . "\n");
    }
}

exit(0);
