<?php
/**
 * TargetController — registrasi target in-scope (Bagian 4.10 assets.php modal).
 * Scan HANYA asset yang didaftarkan di sini — tidak ada hardcoded target.
 */

declare(strict_types=1);

final class TargetController
{
    private const TYPES = ['domain', 'url', 'ip', 'cidr'];

    public static function create(): void
    {
        if (!Session::checkCsrf()) {
            Session::flash('target_error', 'Sesi kedaluwarsa — coba lagi.');
            redirect('/assets');
        }
        $user = Auth::user();

        $name = trim((string) ($_POST['name'] ?? ''));
        $type = trim((string) ($_POST['type'] ?? ''));
        $value = trim((string) ($_POST['value'] ?? ''));
        $environment = trim((string) ($_POST['environment'] ?? 'lab'));

        $value = rtrim($value, '/'); // normalisasi trailing slash
        if ($name === '' || strlen($name) > 190) {
            Session::flash('target_error', 'Nama target wajib diisi (max 190).');
            redirect('/assets');
        }
        if (!in_array($type, self::TYPES, true)) {
            Session::flash('target_error', 'Tipe target tidak valid.');
            redirect('/assets');
        }
        if (!in_array($environment, ['lab', 'staging', 'production'], true)) {
            $environment = 'lab';
        }

        // validasi nilai per tipe (server-side — jangan percaya form)
        $err = match ($type) {
            'domain' => self::validDomain($value),
            'url'    => self::validUrl($value),
            'ip'     => self::validIp($value),
            'cidr'   => self::validCidr($value),
            default  => 'Tipe tidak dikenal.',
        };
        if ($err !== null) {
            Session::flash('target_error', $err);
            redirect('/assets');
        }

        try {
            Database::run(
                'INSERT INTO targets (user_id, name, type, value, environment, in_scope)
                 VALUES (?, ?, ?, ?, ?, 1)',
                [(int) $user['id'], $name, $type, $value, $environment]
            );
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                Session::flash('target_error', 'Target sudah terdaftar (duplikat).');
            } else {
                Session::flash('target_error', 'Gagal menyimpan target.');
            }
            redirect('/assets');
        }

        Audit::log((int) $user['id'], 'target_registered', [
            'type' => $type, 'value' => $value, 'environment' => $environment,
        ]);
        Session::flash('target_ok', 'Target ' . $value . ' terdaftar in-scope — siap di-set workflow.');
        redirect('/assets');
    }

    /** Toggle in-scope / out-of-scope (POST). */
    public static function toggleScope(): void
    {
        if (!Session::checkCsrf()) {
            redirect('/assets');
        }
        $user = Auth::user();
        $id = (int) ($_POST['target_id'] ?? 0);
        $target = Database::run('SELECT id, value, in_scope, user_id FROM targets WHERE id = ?', [$id])->fetch();
        if (!$target || ((int) $target['user_id'] !== (int) $user['id'] && $user['role'] !== 'superadmin')) {
            Session::flash('target_error', 'Target tidak ditemukan.');
            redirect('/assets');
        }
        $new = ((int) $target['in_scope']) === 1 ? 0 : 1;
        Database::run('UPDATE targets SET in_scope = ? WHERE id = ?', [$new, $id]);
        Audit::log((int) $user['id'], 'target_scope_changed', [
            'value' => $target['value'], 'in_scope' => $new,
        ]);
        redirect('/assets');
    }

    private static function validDomain(string $v): ?string
    {
        if (!preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', $v)) {
            return 'Format domain tidak valid (contoh: low.my.id).';
        }
        return null;
    }

    private static function validUrl(string $v): ?string
    {
        if (!filter_var($v, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $v)) {
            return 'Format URL tidak valid (contoh: https://low.my.id/).';
        }
        return null;
    }

    private static function validIp(string $v): ?string
    {
        if (!filter_var($v, FILTER_VALIDATE_IP)) {
            return 'Format IP tidak valid.';
        }
        return null;
    }

    private static function validCidr(string $v): ?string
    {
        [$ip, $bits] = array_pad(explode('/', $v, 2), 2, null);
        if ($ip === null || $bits === null || !filter_var($ip, FILTER_VALIDATE_IP)
            || !ctype_digit($bits) || (int) $bits < 0 || (int) $bits > 32) {
            return 'Format CIDR tidak valid (contoh: 203.0.113.0/24).';
        }
        return null;
    }
}
