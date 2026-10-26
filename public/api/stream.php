<?php
/**
 * api/stream.php — proxy polling event run → orchestrator (localhost only).
 * Frontend JS memanggil endpoint ini (auth session required).
 * GET /api/stream?run_id=...&after=<last_event_id>
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/Core/Env.php';
require_once dirname(__DIR__, 2) . '/src/Core/Database.php';
require_once dirname(__DIR__, 2) . '/src/Core/Session.php';
require_once dirname(__DIR__, 2) . '/src/Core/Auth.php';
require_once dirname(__DIR__, 2) . '/src/Models/User.php';
require_once dirname(__DIR__, 2) . '/src/Core/Audit.php';
require_once dirname(__DIR__, 2) . '/src/Core/functions.php';

Env::load(dirname(__DIR__, 2) . '/.env');
Session::start();
Auth::requireLogin();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$runId = $_GET['run_id'] ?? '';
$after = (int) ($_GET['after'] ?? 0);

if (!preg_match('/^[a-f0-9-]{36}$/', $runId)) {
    http_response_code(422);
    echo json_encode(['error' => 'run_id tidak valid']);
    exit;
}

// cek run milik user (atau superadmin lihat semua)
$run = Database::run(
    'SELECT id, created_by, status, current_phase FROM workflow_runs WHERE id = ? LIMIT 1',
    [$runId]
)->fetch();
$user = Auth::user();
if (!$run) {
    http_response_code(404);
    echo json_encode(['error' => 'run tidak ditemukan']);
    exit;
}
if ((int) $run['created_by'] !== (int) $user['id'] && $user['role'] !== 'superadmin') {
    http_response_code(403);
    echo json_encode(['error' => 'bukan run milik kamu']);
    exit;
}

$orchestratorUrl = rtrim((string) Env::get('ORCHESTRATOR_URL', 'http://127.0.0.1:8100'), '/');
$ctx = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 10,
        'ignore_errors' => true,
        'header' => "Accept: application/json\r\n",
    ],
]);
$raw = @file_get_contents(
    $orchestratorUrl . '/runs/' . urlencode($runId) . '/events?after=' . $after . '&limit=100'
);
if ($raw === false) {
    http_response_code(502);
    echo json_encode(['error' => 'orchestrator tidak merespons']);
    exit;
}

echo $raw;
