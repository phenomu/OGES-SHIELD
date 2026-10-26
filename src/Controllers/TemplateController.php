<?php
/**
 * TemplateController.php — buat/hapus workflow template custom (superadmin, Phase 6).
 * Phase bebas string; phase tanpa tool valid auto-complete di orchestrator loop.
 */
declare(strict_types=1);

final class TemplateController
{
    private const PHASE_WHITELIST = [
        'scope_validation', 'reconnaissance', 'service_enumeration',
        'vulnerability_assessment', 'exploitation_validation',
        'persistence_simulation', 'cleanup_verification', 'report_generation',
    ];

    /** POST /templates/create */
    public static function create(): void
    {
        Auth::requireLogin();
        $user = Auth::user();
        if (($user['role'] ?? '') !== 'superadmin') {
            Session::flash('wf_error', 'Hanya superadmin yang bisa membuat template.');
            redirect('/workflows');
        }
        if (!Session::checkCsrf((string) ($_POST['csrf'] ?? ''))) {
            Session::flash('wf_error', 'Sesi kedaluwarsa — coba lagi.');
            redirect('/workflows');
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        $slug = trim((string) ($_POST['slug'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $phasesRaw = $_POST['phases'] ?? [];

        if ($name === '' || strlen($name) > 190) {
            Session::flash('wf_error', 'Nama template wajib diisi (max 190 karakter).');
            redirect('/workflows');
        }
        if (!preg_match('/^[a-z0-9-]{3,120}$/', $slug)) {
            Session::flash('wf_error', 'Slug wajib huruf kecil/angka/tanda minus, 3–120 karakter.');
            redirect('/workflows');
        }
        if (!is_array($phasesRaw)) {
            $phasesRaw = [];
        }
        $phases = [];
        foreach ($phasesRaw as $ph) {
            if (is_string($ph) && in_array($ph, self::PHASE_WHITELIST, true)) {
                $phases[] = $ph;
            }
        }
        $phases = array_values(array_unique($phases));
        // report_generation selalu terakhir kalau dipilih; scope_validation selalu pertama
        if (in_array('report_generation', $phases, true)) {
            $phases = array_values(array_filter($phases, fn($p) => $p !== 'report_generation'));
            $phases[] = 'report_generation';
        }
        if (count($phases) < 2) {
            Session::flash('wf_error', 'Pilih minimal 2 phase (report generation disarankan agar PDF dibuat).');
            redirect('/workflows');
        }
        // scope_validation wajib ada — guardrail: run selalu mulai dari cek scope
        if (!in_array('scope_validation', $phases, true)) {
            array_unshift($phases, 'scope_validation');
        }

        $db = Database::pdo();
        $st = $db->prepare('SELECT id FROM workflow_templates WHERE slug = ?');
        $st->execute([$slug]);
        if ($st->fetch()) {
            Session::flash('wf_error', "Slug '$slug' sudah dipakai template lain.");
            redirect('/workflows');
        }

        $st = $db->prepare(
            'INSERT INTO workflow_templates (slug, name, description, phases) VALUES (?, ?, ?, ?)'
        );
        $st->execute([$slug, $name, $description !== '' ? $description : null, json_encode($phases)]);

        Audit::log((int) $user['id'], 'template_created', ['slug' => $slug, 'phases' => $phases]);
        Session::flash('wf_success', "Template '$name' dibuat (" . count($phases) . ' phase).');
        redirect('/workflows');
    }

    /** POST /templates/delete — hanya template tanpa run (foreign key guard). */
    public static function delete(): void
    {
        Auth::requireLogin();
        $user = Auth::user();
        if (($user['role'] ?? '') !== 'superadmin') {
            Session::flash('wf_error', 'Hanya superadmin yang bisa menghapus template.');
            redirect('/workflows');
        }
        if (!Session::checkCsrf((string) ($_POST['csrf'] ?? ''))) {
            Session::flash('wf_error', 'Sesi kedaluwarsa — coba lagi.');
            redirect('/workflows');
        }
        $slug = trim((string) ($_POST['slug'] ?? ''));
        if (!preg_match('/^[a-z0-9-]{3,120}$/', $slug)) {
            Session::flash('wf_error', 'Slug tidak valid.');
            redirect('/workflows');
        }
        // lindungi template bawaan
        if (in_array($slug, ['full-web-app-assessment', 'external-recon-only'], true)) {
            Session::flash('wf_error', 'Template bawaan tidak bisa dihapus.');
            redirect('/workflows');
        }

        $db = Database::pdo();
        $st = $db->prepare('SELECT id FROM workflow_templates WHERE slug = ?');
        $st->execute([$slug]);
        $tpl = $st->fetch(PDO::FETCH_ASSOC);
        if (!$tpl) {
            Session::flash('wf_error', 'Template tidak ditemukan.');
            redirect('/workflows');
        }
        $st = $db->prepare('SELECT COUNT(*) AS n FROM workflow_runs WHERE template_id = ?');
        $st->execute([(int) $tpl['id']]);
        if ((int) $st->fetch(PDO::FETCH_ASSOC)['n'] > 0) {
            Session::flash('wf_error', 'Template sudah pernah dipakai run — tidak bisa dihapus (jejak audit).');
            redirect('/workflows');
        }

        $st = $db->prepare('DELETE FROM workflow_templates WHERE id = ?');
        $st->execute([(int) $tpl['id']]);
        Audit::log((int) $user['id'], 'template_deleted', ['slug' => $slug]);
        Session::flash('wf_success', "Template '$slug' dihapus.");
        redirect('/workflows');
    }
}
