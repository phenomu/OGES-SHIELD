<?php
declare(strict_types=1);

/**
 * ReportController — Phase 5: download report.pdf + regenerasi.
 * File PDF disimpan orchestrator (storage/reports/{run_id}.pdf) — PHP hanya
 * stream file ke user (auth + ownership), tidak pernah menyentuh AI key.
 */
final class ReportController
{
    /** GET /reports — halaman list report (data asli Phase 5). */
    public static function index(): void
    {
        Auth::requireLogin();
        $user = Auth::user();
        $isSuper = ($user['role'] ?? '') === 'superadmin';
        $ownSql = $isSuper ? '' : ' AND wr.created_by = ' . (int) $user['id'];

        $db = Database::pdo();
        $st = $db->prepare(
            'SELECT r.run_id, r.file_path, r.overall_summary, r.generated_at,
                    t.value AS target_value, wt.name AS template_name,
                    (SELECT COUNT(*) FROM findings f WHERE f.run_id = r.run_id) AS findings_count,
                    (SELECT COUNT(*) FROM findings f WHERE f.run_id = r.run_id AND f.severity = "critical") AS critical_count
             FROM reports r
             JOIN workflow_runs wr ON wr.id = r.run_id
             JOIN targets t ON t.id = wr.target_id
             JOIN workflow_templates wt ON wt.id = wr.template_id
             WHERE 1=1' . $ownSql . '
             ORDER BY r.generated_at DESC'
        );
        $st->execute();
        $reports = $st->fetchAll(PDO::FETCH_ASSOC);

        View::page('reports', ['reports' => $reports]);
    }

    /** GET /report/download?id=<run_id> — stream PDF ke browser. */
    public static function download(): void
    {
        $runId = (string) ($_GET['id'] ?? '');
        if (!preg_match('/^[a-f0-9-]{36}$/', $runId)) {
            http_response_code(400);
            exit('run id tidak valid');
        }
        Auth::requireLogin();
        $user = Auth::user();

        $db = Database::pdo();
        $st = $db->prepare(
            'SELECT r.file_path, wr.created_by, u.role, t.value AS target_value
             FROM reports r
             JOIN workflow_runs wr ON wr.id = r.run_id
             JOIN targets t ON t.id = wr.target_id
             JOIN users u ON u.id = wr.created_by
             WHERE r.run_id = ?'
        );
        $st->execute([$runId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            http_response_code(404);
            exit('report tidak ditemukan');
        }
        if ((int) $row['created_by'] !== (int) $user['id'] && $user['role'] !== 'superadmin') {
            http_response_code(403);
            exit('bukan run milik kamu');
        }

        $rel = (string) $row['file_path']; // storage/reports/{uuid}.pdf
        $abs = BASE_PATH . '/' . ltrim(str_replace(['..', "\0"], '', $rel), '/');
        $abs = realpath($abs);
        if ($abs === false || !str_starts_with($abs, BASE_PATH . '/storage/reports/')) {
            http_response_code(404);
            exit('file report tidak ada di disk');
        }
        Audit::log((int) $user['id'], 'report_download', ['run_id' => $runId]);

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="oges-shield-report-' . $runId . '.pdf"');
        header('Content-Length: ' . (string) filesize($abs));
        header('X-Content-Type-Options: nosniff');
        readfile($abs);
        exit;
    }

    /** POST /reports/regenerate — minta orchestrator buat ulang PDF (idempotent). */
    public static function regenerate(): void
    {
        Auth::requireLogin();
        $user = Auth::user();

        $runId = (string) ($_POST['run_id'] ?? '');
        $csrf = (string) ($_POST['csrf'] ?? '');
        if (!Session::checkCsrf($csrf)) {
            Session::flash('rep_error', 'Sesi kedaluwarsa — coba lagi.');
            redirect('/reports');
        }
        if (!preg_match('/^[a-f0-9-]{36}$/', $runId)) {
            Session::flash('rep_error', 'run id tidak valid');
            redirect('/reports');
        }

        $db = Database::pdo();
        $st = $db->prepare(
            'SELECT wr.created_by, u.role FROM workflow_runs wr
             JOIN users u ON u.id = wr.created_by WHERE wr.id = ?'
        );
        $st->execute([$runId]);
        $run = $st->fetch(PDO::FETCH_ASSOC);
        if (!$run) {
            Session::flash('rep_error', 'run tidak ditemukan');
            redirect('/reports');
        }
        if ((int) $run['created_by'] !== (int) $user['id'] && $user['role'] !== 'superadmin') {
            Session::flash('rep_error', 'bukan run milik kamu');
            redirect('/reports');
        }

        $resp = self::orchestratorPost('/internal/generate-report',
            json_encode(['run_id' => $runId]));
        $ok = is_array($resp) && !empty($resp['ok']);
        if ($ok) {
            Session::flash('rep_success', 'Report dibuat ulang (' .
                (int) ($resp['findings'] ?? 0) . ' findings, ' . (int) ($resp['size_kb'] ?? 0) . ' KB)');
        } else {
            Session::flash('rep_error', 'Gagal generate report: ' .
                ($resp['detail'] ?? 'orchestrator tidak merespons'));
        }
        redirect('/reports');
    }

    /** HTTP POST ke orchestrator (internal). @return array|null decoded JSON. */
    private static function orchestratorPost(string $path, string $jsonBody): ?array
    {
        $url = rtrim((string) Env::get('ORCHESTRATOR_URL', 'http://127.0.0.1:8100'), '/') . $path;
        $ctx = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'timeout' => 180, // generate PDF + AI summary bisa lambat
                'header'  => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content' => $jsonBody,
                'ignore_errors' => true,
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }
}
