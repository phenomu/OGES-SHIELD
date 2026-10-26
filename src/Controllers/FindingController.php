<?php
declare(strict_types=1);

/**
 * FindingController — Phase 5: halaman findings nyambung data asli.
 * Overall summary = row reports.ownership: run milik user; superadmin semua.
 */
final class FindingController
{
    public static function show(): void
    {
        Auth::requireLogin();
        $user = Auth::user();
        $isSuper = ($user['role'] ?? '') === 'superadmin';

        $db = Database::pdo();
        $sevGet = $_GET['sev'] ?? 'all';
        $sev = is_string($sevGet) ? $sevGet : 'all';
        if (!in_array($sev, ['all', 'critical', 'high', 'medium', 'low', 'info'], true)) {
            $sev = 'all';
        }

        // ownership filter: superadmin lihat semua, user biasa miliknya sendiri
        $ownSql = $isSuper ? '' : ' AND wr.created_by = ' . (int) $user['id'];

        // --- semua findings milik user ---
        $st = $db->prepare(
            'SELECT f.id, f.run_id, f.severity, f.title, f.cvss_score, f.cwe, f.cve,
                    f.summary, f.impact, f.remediation, f.source_tool, f.created_at,
                    t.value AS asset, r.file_path AS report_path
             FROM findings f
             JOIN workflow_runs wr ON wr.id = f.run_id
             LEFT JOIN targets t ON t.id = f.asset_id
             LEFT JOIN reports r ON r.run_id = f.run_id
             WHERE 1=1' . $ownSql . '
             ORDER BY FIELD(f.severity,"critical","high","medium","low","info"), f.id DESC'
        );
        $st->execute();
        $allFindings = $st->fetchAll(PDO::FETCH_ASSOC);

        // --- severity counts (semua, tanpa filter) ---
        $counts = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'info' => 0];
        foreach ($allFindings as $f) {
            $counts[$f['severity']] = ($counts[$f['severity']] ?? 0) + 1;
        }

        // filter severity (data sudah di memori — page kecil)
        $findings = $allFindings;
        if ($sev !== 'all') {
            $findings = array_values(array_filter(
                $findings, fn($f) => $f['severity'] === $sev));
        }

        // --- overall summary run terbaru yang punya report ---
        $st2 = $db->prepare(
            'SELECT r.run_id, r.overall_summary, r.generated_at, t.value AS target_value
             FROM reports r
             JOIN workflow_runs wr ON wr.id = r.run_id
             JOIN targets t ON t.id = wr.target_id
             WHERE 1=1' . str_replace('wr.created_by', 'wr.created_by', $ownSql) . '
             ORDER BY r.generated_at DESC LIMIT 1'
        );
        $st2->execute();
        $report = $st2->fetch(PDO::FETCH_ASSOC) ?: null;

        // --- severity counts (semua, tanpa filter) ---
        $counts = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'info' => 0];
        foreach ($findings as $f) {
            $counts[$f['severity']] = ($counts[$f['severity']] ?? 0) + 1;
        }

        View::page('findings', [
            'findings'  => $findings,
            'allCount'  => count($allFindings),
            'report'    => $report,
            'sevCounts' => $counts,
            'sevFilter' => $sev,
        ]);
    }
}
