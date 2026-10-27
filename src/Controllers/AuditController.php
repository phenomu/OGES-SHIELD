<?php
/**
 * AuditController.php — export audit logs ke CSV (Phase 6).
 * GET /audit/export (link biasa → CSRF via query, cek session + ownership filter).
 */
declare(strict_types=1);

final class AuditController
{
    /** GET /audit/export?action=&actor=&csrf= */
    public static function export(): void
    {
        Auth::requireLogin();
        $user = Auth::user();
        $isSuper = ($user['role'] ?? '') === 'superadmin';

        // CSRF lewat query (link <a> bukan form POST) — token terikat session
        $token = (string) ($_GET['csrf'] ?? '');
        if ($token === '' || !Session::checkCsrf($token)) {
            http_response_code(403);
            exit('CSRF token tidak valid — buka ulang halaman Audit Logs.');
        }

        $actions = [
            'login_success', 'login_failed', 'logout', 'register',
            'user_approved', 'user_rejected',
            'run_created', 'run_started', 'run_stopped', 'run_stop_requested', 'run_deleted',
            'run_resume_viewed', 'engagement_selected', 'engagement_reset',
            'tool_executed', 'scope_violation', 'tool_registry_saved', 'tools_reloaded',
            'report_download', 'report_regenerated', 'template_created', 'template_deleted',
        ];
        $fAction = (string) ($_GET['action'] ?? '');
        $fActor  = (string) ($_GET['actor'] ?? '');
        if ($fAction !== '' && !in_array($fAction, $actions, true)) { $fAction = ''; }
        if (strlen($fActor) > 60) { $fActor = ''; }

        $where  = [];
        $params = [];
        if ($fAction !== '') { $where[] = 'a.action = ?'; $params[] = $fAction; }
        if ($fActor !== '')  { $where[] = 'u.username LIKE ?'; $params[] = $fActor . '%'; }
        if (!$isSuper) {
            $where[] = 'a.user_id = ?';
            $params[] = (int) $user['id'];
        }
        $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

        $rows = Database::run(
            "SELECT a.id, a.action, a.target_value, a.tool_id, a.detail, a.created_at,
                    u.username, u.role
             FROM audit_logs a
             LEFT JOIN users u ON u.id = a.user_id" . $whereSql . "
             ORDER BY a.id DESC
             LIMIT 10000",
            $params
        )->fetchAll(PDO::FETCH_ASSOC);

        Audit::log((int) $user['id'], 'audit_exported', ['rows' => count($rows), 'action' => $fAction ?: null]);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="oges-audit-' . date('Ymd-His') . '.csv"');
        header('Cache-Control: no-store');

        $out = fopen('php://output', 'w');
        // BOM UTF-8 biar Excel baca benar
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['id', 'waktu', 'aktor', 'role', 'action', 'tool', 'target', 'detail']);
        foreach ($rows as $r) {
            fputcsv($out, [
                (string) $r['id'],
                (string) $r['created_at'],
                (string) ($r['username'] ?? 'system'),
                (string) ($r['role'] ?? ''),
                (string) $r['action'],
                (string) ($r['tool_id'] ?? ''),
                (string) ($r['target_value'] ?? ''),
                (string) ($r['detail'] ?? ''),
            ]);
        }
        fclose($out);
        exit;
    }
}
