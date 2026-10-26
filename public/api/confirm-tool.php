<?php
/**
 * api/confirm-tool.php — proxy konfirmasi tool destructive (guardrail 9.4).
 * Browser → PHP (auth + ownership + CSRF) → orchestrator /internal/confirm-tool.
 * Orchestrator TIDAK PERNAH diakses browser langsung.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/Core/Env.php';
require_once dirname(__DIR__, 2) . '/src/Core/Database.php';
require_once dirname(__DIR__, 2) . '/src/Core/Session.php';

header('Content-Type: application/json; charset=utf-8');

Env::load(dirname(__DIR__, 2) . '/.env');
Session::start();

function fail(string $msg, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

// wajib login
if (empty($_SESSION['user_id'])) {
    fail('unauthorized', 401);
}
$userId = (int) $_SESSION['user_id'];
$role = (string) ($_SESSION['role'] ?? 'user');

if (!Session::checkCsrf()) {
    fail('csrf invalid', 403);
}

$runId = trim((string) ($_POST['run_id'] ?? ''));
$confirmed = ($_POST['confirmed'] ?? '') === '1';
if (!preg_match('/^[a-f0-9-]{36}$/', $runId)) {
    fail('run_id invalid');
}

// ownership: run milik user (superadmin boleh semua)
$run = Database::run(
    'SELECT created_by FROM workflow_runs WHERE id = ? LIMIT 1',
    [$runId]
)->fetch();
if (!$run || ((int) $run['created_by'] !== $userId && $role !== 'superadmin')) {
    fail('run tidak ditemukan / bukan milik kamu', 404);
}

// proxy ke orchestrator
$url = rtrim(Env::get('ORCHESTRATOR_URL', 'http://127.0.0.1:8100'), '/')
    . '/internal/confirm-tool';
$ctx = stream_context_create([
    'http' => [
        'method'  => 'POST',
        'timeout' => 15,
        'header'  => "Content-Type: application/json\r\nAccept: application/json\r\n",
        'content' => json_encode(['run_id' => $runId, 'confirmed' => $confirmed]),
        'ignore_errors' => true,
    ],
]);
$raw = @file_get_contents($url, false, $ctx);
if ($raw === false) {
    fail('orchestrator tidak merespons', 502);
}
$decoded = json_decode($raw, true);
if (!is_array($decoded)) {
    fail('respons orchestrator invalid', 502);
}
http_response_code(200);
echo json_encode(['ok' => (bool) ($decoded['ok'] ?? false), 'confirmed' => $confirmed]);
