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

        $runId = is_array($resp) ? (string) ($resp['run_id'] ?? '') : '';
        if ($runId === '' || !preg_match('/^[a-f0-9-]{36}$/', $runId)) {
            Session::flash('wf_error', 'Gagal membuat run di orchestrator: ' .
                ($resp['detail'] ?? 'orchestrator tidak merespons'));
            redirect('/workflows');
        }

        Audit::log((int) $user['id'], 'run_started', [
            'run_id' => $runId, 'template' => $templateSlug,
            'target' => $target['value'],
        ]);
        redirect('/run?id=' . urlencode($runId));
    }

    /** GET /run?id=... — halaman live run. */
    public static function show(): void
    {
        $runIdGet = $_GET['id'] ?? '';
        $runId = trim(is_string($runIdGet) ? $runIdGet : '');
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

        // daftar tool dari tools.json (sumber sama dgn orchestrator) — module pipeline dinamis
        $tools = [];
        $tj = json_decode((string) @file_get_contents(BASE_PATH . '/orchestrator/config/tools.json'), true);
        if (is_array($tj) && isset($tj['tools']) && is_array($tj['tools'])) {
            foreach ($tj['tools'] as $t) {
                if (!is_array($t) || !($t['enabled'] ?? false)) {
                    continue;
                }
                $tools[] = [
                    'id'   => (string) ($t['id'] ?? ''),
                    'name' => (string) ($t['name'] ?? $t['id'] ?? ''),
                    'risk' => (string) ($t['risk_level'] ?? 'safe'),
                ];
            }
        }
        View::page('run', [
            'run'         => $run,
            'runId'       => $runId,
            'phases'      => $phases,
            'tools'       => $tools,
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

    /** POST /runs/resume — run awaiting_confirmation: kembali ke modal keputusan. */
    public static function resume(): void
    {
        Auth::requireLogin();
        $user = Auth::user();
        $runId = (string) ($_POST['run_id'] ?? '');
        if (!Session::checkCsrf((string) ($_POST['csrf'] ?? ''))) {
            Session::flash('run_error', 'Sesi kedaluwarsa — coba lagi.');
            redirect('/run?id=' . urlencode($runId));
        }
        $db = Database::pdo();
        $st = $db->prepare('SELECT status, created_by FROM workflow_runs WHERE id = ?');
        $st->execute([$runId]);
        $run = $st->fetch(PDO::FETCH_ASSOC);
        if (!$run || ((int) $run['created_by'] !== (int) $user['id'] && $user['role'] !== 'superadmin')) {
            Session::flash('run_error', 'Run tidak ditemukan / bukan milik kamu.');
            redirect('/workflows');
        }
        // resume = tampilkan kembali halaman run; modal konfirmasi auto-open
        // selama status masih awaiting_confirmation (event confirmation_required ada).
        Audit::log((int) $user['id'], 'run_resume_viewed', ['run_id' => $runId]);
        redirect('/run?id=' . urlencode($runId));
    }

    /** POST /runs/delete — hapus run + event + findings + report (konfirmasi modal). */
    public static function delete(): void
    {
        Auth::requireLogin();
        $user = Auth::user();
        $runId = (string) ($_POST['run_id'] ?? '');
        if (!Session::checkCsrf((string) ($_POST['csrf'] ?? ''))) {
            Session::flash('run_error', 'Sesi kedaluwarsa — coba lagi.');
            redirect('/run?id=' . urlencode($runId));
        }
        $db = Database::pdo();
        $st = $db->prepare('SELECT status, created_by FROM workflow_runs WHERE id = ?');
        $st->execute([$runId]);
        $run = $st->fetch(PDO::FETCH_ASSOC);
        if (!$run || ((int) $run['created_by'] !== (int) $user['id'] && $user['role'] !== 'superadmin')) {
            Session::flash('run_error', 'Run tidak ditemukan / bukan milik kamu.');
            redirect('/workflows');
        }
        if ($run['status'] === 'running' || $run['status'] === 'awaiting_confirmation') {
            // run aktif harus di-stop dulu — jangan hapus saat loop masih hidup
            Session::flash('run_error', 'Run masih berjalan — stop dulu sebelum delete.');
            redirect('/run?id=' . urlencode($runId));
        }
        Audit::log((int) $user['id'], 'run_deleted', ['run_id' => $runId]);
        // FK ON DELETE CASCADE: run_events + findings + reports ikut terhapus
        $st = $db->prepare('DELETE FROM workflow_runs WHERE id = ?');
        $st->execute([$runId]);
        Session::flash('wf_success', 'Run ' . substr($runId, 0, 8) . '… dihapus.');
        redirect('/workflows');
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
