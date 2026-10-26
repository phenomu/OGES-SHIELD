<?php
/**
 * ActiveRun.php — helper "current engagement" (Phase 6).
 * Run aktif = pilihan user (session) atau fallback run terbaru milik user.
 * Dipakai topbar, settings, recon, vulnerabilities, exploitation.
 */
declare(strict_types=1);

final class ActiveRun
{
    public const SESSION_KEY = 'active_run_id';

    /** Run aktif user (row assoc atau null). */
    public static function current(array $user): ?array
    {
        $db = Database::pdo();
        $isSuper = ($user['role'] ?? '') === 'superadmin';

        $chosen = $_SESSION[self::SESSION_KEY] ?? '';
        if (is_string($chosen) && $chosen !== '' && preg_match('/^[a-f0-9-]{36}$/', $chosen)) {
            $st = $db->prepare(
                'SELECT r.id, r.status, r.current_phase, r.phases, r.started_at, r.finished_at,
                        r.created_at, r.template_id, r.target_id, wt.name AS template_name,
                        t.value AS target_value, t.type AS target_type, t.environment
                 FROM workflow_runs r
                 JOIN workflow_templates wt ON wt.id = r.template_id
                 JOIN targets t ON t.id = r.target_id
                 WHERE r.id = ? AND (? = 1 OR r.created_by = ?)'
            );
            $st->execute([$chosen, $isSuper ? 1 : 0, (int) $user['id']]);
            $run = $st->fetch(PDO::FETCH_ASSOC);
            if ($run) {
                return $run;
            }
        }

        // fallback: run terbaru milik user
        $st = $db->prepare(
            'SELECT r.id, r.status, r.current_phase, r.phases, r.started_at, r.finished_at,
                    r.created_at, r.template_id, r.target_id, wt.name AS template_name,
                    t.value AS target_value, t.type AS target_type, t.environment
             FROM workflow_runs r
             JOIN workflow_templates wt ON wt.id = r.template_id
             JOIN targets t ON t.id = r.target_id
             WHERE (? = 1 OR r.created_by = ?)
             ORDER BY r.created_at DESC LIMIT 1'
        );
        $st->execute([$isSuper ? 1 : 0, (int) $user['id']]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Daftar run milik user utk selector (terbaru dulu). */
    public static function list(array $user, int $limit = 20): array
    {
        $db = Database::pdo();
        $isSuper = ($user['role'] ?? '') === 'superadmin';
        $st = $db->prepare(
            'SELECT r.id, r.status, r.created_at, t.value AS target_value
             FROM workflow_runs r
             JOIN targets t ON t.id = r.target_id
             WHERE (? = 1 OR r.created_by = ?)
             ORDER BY r.created_at DESC LIMIT ' . max(1, min(100, $limit))
        );
        $st->execute([$isSuper ? 1 : 0, (int) $user['id']]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Set pilihan user (validasi ownership — bukan dari request mentah). */
    public static function select(array $user, string $runId): bool
    {
        $db = Database::pdo();
        $isSuper = ($user['role'] ?? '') === 'superadmin';
        $st = $db->prepare('SELECT id FROM workflow_runs WHERE id = ? AND (? = 1 OR created_by = ?)');
        $st->execute([$runId, $isSuper ? 1 : 0, (int) $user['id']]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }
        $_SESSION[self::SESSION_KEY] = (string) $row['id'];
        return true;
    }

    /** Kembali ke default (run terbaru). */
    public static function reset(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
    }
}
