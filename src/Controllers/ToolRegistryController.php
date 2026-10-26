<?php
/**
 * ToolRegistryController — Phase 4: tulis tools.json dari admin UI.
 * Validasi ketat client-side minimal (JSON + struktur), lalu trigger
 * POST /internal/reload-tools ke orchestrator. Kalau orchestrator menolak
 * (schema invalid versi registry), file di-REVERT — tidak pernah ada kondisi
 * tools.json rusak yang menetap.
 */

declare(strict_types=1);

final class ToolRegistryController
{
    private const TOOLS_PATH = BASE_PATH . '/orchestrator/config/tools.json';

    public static function save(): void
    {
        if (!Session::checkCsrf()) {
            Session::flash('tr_error', 'Sesi kedaluwarsa — coba lagi.');
            redirect('/admin/tool-registry');
        }
        Auth::requireSuperadmin();

        $action = (string) ($_POST['action'] ?? '');
        $backup = self::TOOLS_PATH; // baca current utk revert

        if ($action === 'raw') {
            $raw = (string) ($_POST['raw_json'] ?? '');
            $data = json_decode($raw, true);
            if (!is_array($data) || !isset($data['tools']) || !is_array($data['tools']) || $data['tools'] === []) {
                Session::flash('tr_error', 'JSON invalid atau key "tools" kosong/hilang.');
                redirect('/admin/tool-registry');
            }
            // pre-check wajib: tiap tool punya id unik string
            $ids = [];
            foreach ($data['tools'] as $t) {
                if (!is_array($t) || !isset($t['id']) || !is_string($t['id'])) {
                    Session::flash('tr_error', 'Semua entri tools wajib object dengan id string.');
                    redirect('/admin/tool-registry');
                }
                if (in_array($t['id'], $ids, true)) {
                    Session::flash('tr_error', 'Id tool duplikat: ' . $t['id']);
                    redirect('/admin/tool-registry');
                }
                $ids[] = $t['id'];
            }
            $jsonOut = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            self::writeAndReload($jsonOut, $backup);
            return;
        }

        // toggle / risk
        $toolId = (string) ($_POST['tool_id'] ?? '');
        $data = json_decode((string) file_get_contents(self::TOOLS_PATH), true);
        if (!is_array($data) || !isset($data['tools'])) {
            Session::flash('tr_error', 'tools.json saat ini tidak terbaca.');
            redirect('/admin/tool-registry');
        }
        $found = false;
        foreach ($data['tools'] as &$t) {
            if (($t['id'] ?? '') === $toolId) {
                $found = true;
                if ($action === 'toggle') {
                    $t['enabled'] = !($t['enabled'] ?? false);
                } elseif ($action === 'risk') {
                    $t['risk_level'] = match ($t['risk_level'] ?? 'safe') {
                        'safe'       => 'intrusive',
                        'intrusive'  => 'destructive',
                        default      => 'safe',
                    };
                    if ($t['risk_level'] === 'destructive') {
                        $t['requires_confirmation'] = true;
                    } else {
                        unset($t['requires_confirmation']);
                    }
                }
                break;
            }
        }
        unset($t);
        if (!$found) {
            Session::flash('tr_error', 'Tool tidak ditemukan: ' . $toolId);
            redirect('/admin/tool-registry');
        }
        $jsonOut = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        self::writeAndReload($jsonOut, $backup);
    }

    /** Tulis file + reload orchestrator; revert kalau orchestrator reject. */
    private static function writeAndReload(string $jsonOut, string $backupPath): void
    {
        $before = (string) file_get_contents($backupPath);
        file_put_contents(self::TOOLS_PATH, $jsonOut, LOCK_EX);

        $url = rtrim((string) Env::get('ORCHESTRATOR_URL', 'http://127.0.0.1:8100'), '/')
            . '/internal/reload-tools';
        $ctx = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'timeout' => 20,
                'header'  => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content' => '{}',
                'ignore_errors' => true,
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        $resp = is_string($raw) ? json_decode($raw, true) : null;

        if (is_array($resp) && ($resp['ok'] ?? false) === true) {
            Audit::log((int) Auth::user()['id'], 'tool_registry_saved', [
                'tools_loaded' => $resp['tools_loaded'] ?? null,
            ]);
            Session::flash('tr_ok', 'tools.json tersimpan — orchestrator reload OK ('
                . ($resp['tools_loaded'] ?? '?') . ' tool dimuat).');
        } else {
            // revert — registry orchestrator lebih ketat dari validasi PHP
            file_put_contents(self::TOOLS_PATH, $before, LOCK_EX);
            $detail = is_array($resp) ? (string) ($resp['detail'] ?? json_encode($resp)) : 'orchestrator tidak merespons';
            Session::flash('tr_error', 'Orchestrator MENOLAK tools.json — di-revert. Alasan: ' . $detail);
        }
        redirect('/admin/tool-registry');
    }
}
