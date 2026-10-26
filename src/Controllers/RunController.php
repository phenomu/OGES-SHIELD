<?php
/**
 * RunController — buat run nyata (proxy PHP → orchestrator POST /runs),
 * halaman live run (polling /api/stream.php), stop run.
 * Orchestrator TIDAK PERNAH diakses browser langsung — hanya PHP backend.
 */

declare(strict_types=1);

final class RunController
{
    /** POST /runs/create — dari modal Set Workflow (workflows.php). */
    public static function create(): void
    {
        if (!Session::checkCsrf()) {
            Session::flash('wf_error', 'Sesi kedaluwarsa — coba lagi.');
            redirect('/workflows');
        }
        $user = Auth::user();
        $templateSlug = trim((string) ($_POST['template_slug'] ?? ''));
        $targetId = (int) ($_POST['target_id'] ?? 0);

        if ($templateSlug === '' || $targetId <= 0) {
            Session::flash('wf_error', 'Template dan target wajib dipilih.');
            redirect('/workflows');
        }

        // target wajib milik user & in_scope (guardrail: scope enforcement PHP-side juga)
        $target = Database::run(
            'SELECT id, name, value, in_scope, user_id FROM targets WHERE id = ? LIMIT 1',
            [$targetId]
        )->fetch();
        if (!$target || (int) $target['in_scope'] !== 1) {
            Session::flash('wf_error', 'Target tidak in-scope / tidak ditemukan.');
            redirect('/workflows');
        }
        if ((int) $target['user_id'] !== (int) $user['id'] && $user['role'] !== 'superadmin') {
            Session::flash('wf_error', 'Target bukan milik kamu.');
            redirect('/workflows');
        }

        $body = json_encode([
            'template_slug' => $templateSlug,
            'target_id'     => $targetId,
            'created_by'    => (int) $user['id'],
        ]);
        $resp = self::orchestratorPost('/runs', $body);

        if ($resp === null || ($resp['status'] ?? '') !== 'pending') {
            Session::flash('wf_error', 'Gagal membuat run di orchestrator: ' .
                ($resp['detail'] ?? 'orchestrator tidak merespons'));
            redirect('/workflows');
        }

        Audit::log((int) $user['id'], 'run_started', [
            'run_id' => $resp['run_id'], 'template' => $templateSlug,
            'target' => $target['value'],
        ]);
        redirect('/run?id=' . urlencode((string) $resp['run_id']));
    }

    /** GET /run?id=... — halaman live run. */
    public static function show(): void
    {
        $runId = trim((string) ($_GET['id'] ?? ''));
        if (!preg_match('/^[a-f0-9-]{36}$/', $runId)) {
            http_response_code(404);
            View::render('404');
            return;
        }
        $user = Auth::user();
        $run = Database::run(
            'SELECT r.id, r.status, r.current_phase, r.created_at, r.started_at, r.finished_at,
                    t.name AS target_name, t.value AS target_value, t.environment,
                    wt.name AS template_name, wt.slug AS template_slug,
                    wt.phases AS template_phases,
                    u.username AS created_by_username
             FROM workflow_runs r
             JOIN targets t ON t.id = r.target_id
             JOIN workflow_templates wt ON wt.id = r.template_id
             JOIN users u ON u.id = r.created_by
             WHERE r.id = ? LIMIT 1',
            [$runId]
        )->fetch();
        if (!$run) {
            http_response_code(404);
            View::render('404');
            return;
        }
        if ($run['created_by_username'] !== $user['username'] && $user['role'] !== 'superadmin') {
            http_response_code(403);
            View::page('403');
            return;
        }
        // phases snapshot utk stepper + tool pool utk module pipeline
        $phases = json_decode((string) ($run['template_phases'] ?? ''), true) ?: [];
        View::page('run', [
            'run'         => $run,
            'runId'       => $runId,
            'phases'      => $phases,
        ]);
    }

    /** POST /runs/stop — hentikan run. */
    public static function stop(): void
    {
        if (!Session::checkCsrf()) {
            Session::flash('wf_error', 'Sesi kedaluwarsa.');
            redirect('/workflows');
        }
        $runId = trim((string) ($_POST['run_id'] ?? ''));
        if (!preg_match('/^[a-f0-9-]{36}$/', $runId)) {
            redirect('/workflows');
        }
        $resp = self::orchestratorPost('/runs/' . urlencode($runId) . '/stop', '{}');
        Audit::log((int) Auth::user()['id'], 'run_stop_requested', ['run_id' => $runId]);
        redirect('/run?id=' . urlencode($runId));
    }

    /** HTTP POST ke orchestrator (internal). @return array|null decoded JSON. */
    private static function orchestratorPost(string $path, string $jsonBody): ?array
    {
        $url = rtrim((string) Env::get('ORCHESTRATOR_URL', 'http://127.0.0.1:8100'), '/') . $path;
        $ctx = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'timeout' => 15,
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
