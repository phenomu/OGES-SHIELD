<?php
/**
 * dashboard.php — Overview (Bagian 4.3 – 4.9). Semua data asli: stats,
 * sparkline deret per hari, timeline event tool run terbaru, terminal
 * live feed, findings/audit dari DB (dummy Phase 1 sudah dipensiunkan).
 */
View::$pageTitle = 'Overview — OGES-SHIELD';
$navKey = $_GET['nav'] ?? 'overview';
$navKey = is_string($navKey) ? $navKey : 'overview';
if (!in_array($navKey, ['overview', 'operations'], true)) { $navKey = 'overview'; }
View::$activeNav = $navKey;

// ---- Data asli (Phase 6 — ganti MockData) ----
$user = Auth::user();
$isSuper = ($user['role'] ?? '') === 'superadmin';
$ownFilter = $isSuper ? '1=1' : 'wr.created_by = ' . (int) $user['id'];

// stat cards
$targetsTotal = (int) Database::run('SELECT COUNT(*) AS n FROM targets')->fetch()['n'];
$targetsInScope = (int) Database::run('SELECT COUNT(*) AS n FROM targets WHERE in_scope = 1')->fetch()['n'];
$runsTotal = (int) Database::run("SELECT COUNT(*) AS n FROM workflow_runs WHERE $ownFilter")->fetch()['n'];
$runsActive = (int) Database::run("SELECT COUNT(*) AS n FROM workflow_runs WHERE $ownFilter AND status IN ('running','awaiting_confirmation','pending')")->fetch()['n'];
$runsCompleted = (int) Database::run("SELECT COUNT(*) AS n FROM workflow_runs WHERE $ownFilter AND status = 'completed'")->fetch()['n'];
$findingsTotal = (int) Database::run("SELECT COUNT(*) AS n FROM findings f JOIN workflow_runs wr ON wr.id = f.run_id WHERE $ownFilter")->fetch()['n'];
$sevRow = Database::run(
    "SELECT SUM(f.severity='critical') AS c, SUM(f.severity='high') AS h,
            SUM(f.severity='medium') AS m, SUM(f.severity='low') AS l, SUM(f.severity='info') AS i
     FROM findings f JOIN workflow_runs wr ON wr.id = f.run_id WHERE $ownFilter"
)->fetch();
$openVulns = (int) (($sevRow['c'] ?? 0) + ($sevRow['h'] ?? 0) + ($sevRow['m'] ?? 0) + ($sevRow['l'] ?? 0));
$criticalCount = (int) ($sevRow['c'] ?? 0);

// host discovered (dari httpx events run user — latest 5)
$hostsDiscovered = (int) Database::run(
    "SELECT COALESCE(SUM(JSON_EXTRACT(re.payload, '$.record_count')),0) AS n
     FROM run_events re JOIN workflow_runs wr ON wr.id = re.run_id
     WHERE re.type='tool_completed' AND JSON_EXTRACT(re.payload,'$.tool')='subfinder' AND $ownFilter"
)->fetch()['n'];

// sparkline deret riil: 12 titik per hari (kumulatif akhir hari), sumber per key:
//   assets → targets dibuat; hosts → tool event subdomain/host ditemukan;
//   vulns/crit → findings dibuat (crit: severity=critical);
//   runs → run selesai; ai → tool_completed (aktivitas orkestrasi AI)
$__sparkDays = [];
try {
    foreach (Database::run(
        "SELECT d, IFNULL(v,0) v FROM (
           SELECT CURDATE() - INTERVAL n DAY AS d, NULL AS v FROM (SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11) nums
         ) days ORDER BY d"
    )->fetchAll() as $r) { $__sparkDays[] = $r['d']; }
} catch (Throwable $e) { $__sparkDays = []; }

function __dailySeries(array $days, string $sql, array $params = []): array {
    $counts = [];
    if ($days) {
        foreach (Database::run($sql, $params)->fetchAll() as $r) {
            $counts[$r['d']] = (int) $r['v'];
        }
    }
    return array_map(function ($d) use ($counts) { return $counts[$d] ?? 0; }, $days);
}

$sparks = [];
if ($__sparkDays) {
    $sparks['assets'] = __dailySeries($__sparkDays,
        "SELECT DATE(created_at) d, COUNT(*) v FROM targets GROUP BY d");
    $sparks['hosts'] = __dailySeries($__sparkDays,
        "SELECT DATE(created_at) d, COUNT(*) v FROM run_events " .
        "WHERE type IN ('tool_completed','finding') GROUP BY d");
    $sparks['vulns'] = __dailySeries($__sparkDays,
        "SELECT DATE(created_at) d, COUNT(*) v FROM findings WHERE severity IN ('critical','high','medium','low') GROUP BY d");
    $sparks['crit'] = __dailySeries($__sparkDays,
        "SELECT DATE(created_at) d, COUNT(*) v FROM findings WHERE severity='critical' GROUP BY d");
    $sparks['runs'] = __dailySeries($__sparkDays,
        "SELECT DATE(finished_at) d, COUNT(*) v FROM workflow_runs WHERE status IN ('completed','stopped') GROUP BY d");
    $sparks['ai'] = __dailySeries($__sparkDays,
        "SELECT DATE(created_at) d, COUNT(*) v FROM run_events WHERE type='tool_completed' GROUP BY d");
} else {
    foreach ($stats as $c) { $sparks[$c['key']] = array_fill(0, 12, (int) $c['value']); }
}

// trend riil = delta hari terakhir vs hari sebelumnya (dari deret spark)
$__trend = [];
foreach ($sparks as $k => $series) {
    $n = count($series);
    $last = $n ? (int) $series[$n - 1] : 0;
    $prev = $n > 1 ? (int) $series[$n - 2] : 0;
    $__trend[$k] = $last - $prev;
}

// stat cards format sama seperti MockData (icon/label/value/total/trend/spark)
$stats = [
    ['key' => 'assets',  'icon' => 'db',   'label' => 'Authorized Assets', 'good' => true,    'value' => $targetsInScope, 'total' => $targetsTotal, 'trend' => (int)($__trend['assets'] ?? 0), 'trend_up' => true],
    ['key' => 'hosts',   'icon' => 'host', 'label' => 'Hosts Discovered', 'good' => true,     'value' => $hostsDiscovered, 'total' => $hostsDiscovered, 'trend' => (int)($__trend['hosts'] ?? 0), 'trend_up' => true],
    ['key' => 'vulns',   'icon' => 'warn', 'label' => 'Open Vulnerabilities', 'good' => false, 'value' => $openVulns, 'total' => $findingsTotal, 'tone' => 'warning', 'trend' => (int)($__trend['vulns'] ?? 0), 'trend_up' => false],
    ['key' => 'crit',    'icon' => 'crit', 'label' => 'Critical Findings', 'good' => false,    'value' => $criticalCount, 'total' => $findingsTotal, 'tone' => 'danger', 'trend' => (int)($__trend['crit'] ?? 0), 'trend_up' => false],
    ['key' => 'runs',    'icon' => 'run',  'label' => 'Completed Runs', 'good' => true,       'value' => $runsCompleted, 'total' => $runsTotal, 'trend' => (int)($__trend['runs'] ?? 0), 'trend_up' => true],
    ['key' => 'ai',      'icon' => 'ai',   'label' => 'AI Orchestrations Active', 'good' => true, 'value' => $runsActive, 'total' => 8, 'trend' => (int)($__trend['ai'] ?? 0), 'trend_up' => true],
];

// stepper + ops — dari run terbaru milik user
$latestRunRow = Database::run(
    "SELECT r.id, r.status, r.current_phase, r.phases, r.started_at, r.finished_at, wt.name AS template_name
     FROM workflow_runs r JOIN workflow_templates wt ON wt.id = r.template_id
     WHERE $ownFilter ORDER BY r.created_at DESC LIMIT 1"
)->fetch();
$phasesList = $latestRunRow ? (json_decode((string) $latestRunRow['phases'], true) ?: []) : [];
$phaseDone = [];
if ($latestRunRow) {
    foreach (Database::run(
        "SELECT phase, MAX(type='phase_complete') AS done FROM run_events WHERE run_id = ? GROUP BY phase",
        [$latestRunRow['id']]
    )->fetchAll(PDO::FETCH_ASSOC) as $pr) {
        $phaseDone[$pr['phase']] = (bool) $pr['done'];
    }
}
$labels = [
    'scope_validation' => 'Scope Validation', 'reconnaissance' => 'Reconnaissance',
    'service_enumeration' => 'Service Enumeration', 'vulnerability_assessment' => 'Vulnerability Assess.',
    'exploitation_validation' => 'Exploitation Validation', 'persistence_simulation' => 'Persistence Simulation',
    'cleanup_verification' => 'Cleanup & Verification', 'report_generation' => 'Report Generation',
];
$stepper = [];
$curPhaseHit = false;
foreach ($phasesList as $i => $ph) {
    $isDone = !empty($phaseDone[$ph]);
    $isRunning = !$isDone && $latestRunRow && $latestRunRow['status'] === 'running'
        && $ph === ($latestRunRow['current_phase'] ?? null);
    $stepper[] = [
        'num' => str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
        'label' => $labels[$ph] ?? str_replace('_', ' ', $ph),
        'status' => $isDone ? 'completed' : ($isRunning ? 'running' : 'pending'),
        'time' => $isDone ? '✓' : '--',
    ];
}
if (!$stepper) {
    $stepper = [['num' => '01', 'label' => 'Belum ada run', 'status' => 'pending', 'time' => '--']];
}
$doneCount = count(array_filter($stepper, fn($s) => $s['status'] === 'completed'));
$progressPct = $phasesList ? (int) round($doneCount / count($phasesList) * 100) : 0;
$toolCount = $latestRunRow ? (int) Database::run(
    "SELECT COUNT(*) AS n FROM run_events WHERE run_id = ? AND type = 'tool_completed'",
    [$latestRunRow['id']]
)->fetch()['n'] : 0;
$ops = [
    'run_id' => $latestRunRow ? 'RUN-' . substr((string) $latestRunRow['id'], 0, 12) : '—',
    'progress_pct' => $progressPct,
    'current_module' => $latestRunRow && $latestRunRow['current_phase'] ? ($labels[$latestRunRow['current_phase']] ?? $latestRunRow['current_phase']) : '—',
    'tasks_done' => $toolCount,
    'tasks_total' => $toolCount,
];

// security summary — donut severity + cvss dari findings user
$secSum = [
    'open_findings' => $findingsTotal,
    'severity' => [
        ['label' => 'Critical', 'value' => (int) ($sevRow['c'] ?? 0), 'color' => 'critical'],
        ['label' => 'High',     'value' => (int) ($sevRow['h'] ?? 0), 'color' => 'high'],
        ['label' => 'Medium',   'value' => (int) ($sevRow['m'] ?? 0), 'color' => 'medium'],
        ['label' => 'Low',      'value' => (int) ($sevRow['l'] ?? 0), 'color' => 'low'],
        ['label' => 'Info',     'value' => (int) ($sevRow['i'] ?? 0), 'color' => 'info'],
    ],
    'cvss' => [],
];
$cvssRows = Database::run(
    "SELECT
        SUM(f.cvss_score >= 9.0) AS r1, SUM(f.cvss_score >= 7.0 AND f.cvss_score < 9.0) AS r2,
        SUM(f.cvss_score >= 4.0 AND f.cvss_score < 7.0) AS r3, SUM(f.cvss_score > 0 AND f.cvss_score < 4.0) AS r4
     FROM findings f JOIN workflow_runs wr ON wr.id = f.run_id WHERE $ownFilter"
)->fetch();
$secSum['cvss'] = [
    ['range' => '9.0–10.0', 'value' => (int) ($cvssRows['r1'] ?? 0)],
    ['range' => '7.0–8.9',  'value' => (int) ($cvssRows['r2'] ?? 0)],
    ['range' => '4.0–6.9',  'value' => (int) ($cvssRows['r3'] ?? 0)],
    ['range' => '0.1–3.9',  'value' => (int) ($cvssRows['r4'] ?? 0)],
];

// inventory recon — subdomain/host live dari tool_completed httpx run terbaru
$inventory = [];
if ($latestRunRow) {
    $evRows = Database::run(
        "SELECT payload FROM run_events WHERE run_id = ? AND type='ai_summary' AND JSON_EXTRACT(payload,'$.tool')='httpx' ORDER BY id DESC LIMIT 1",
        [$latestRunRow['id']]
    )->fetchAll(PDO::FETCH_ASSOC);
}
$inventory = Database::run(
    "SELECT t.name AS asset, t.type, t.environment, t.in_scope, t.created_at
     FROM targets t ORDER BY t.created_at DESC LIMIT 10"
)->fetchAll(PDO::FETCH_ASSOC);
$inventoryRows = [];
foreach ($inventory as $t) {
    $inventoryRows[] = [
        'asset' => (string) ($t['asset'] ?? $t['name'] ?? '—'),
        'type' => (string) ($t['type'] ?? 'domain'),
        'service' => '—',
        'status' => !empty($t['in_scope']) ? 'online' : 'offline',
        'discovered' => substr((string) ($t['created_at'] ?? ''), 0, 10) ?: '—',
        'scope' => !empty($t['in_scope']) ? 'in' : 'out',
    ];
}
$inventory = $inventoryRows;

// timeline discovery per event tool (port pola recon.php): passive =
// kumulatif event subfinder, active = kumulatif event tool lain (httpx,
// nuclei, custom) — run terbaru milik user
$timeline = ['labels' => [], 'passive' => [], 'active' => []];
if ($latestRunRow) {
    $__tl = [];
    foreach (Database::run(
        "SELECT payload, created_at FROM run_events
         WHERE run_id = ? AND type = 'tool_completed'
         ORDER BY id",
        [$latestRunRow['id']]
    )->fetchAll(PDO::FETCH_ASSOC) as $ev) {
        $pl = json_decode((string) $ev['payload'], true) ?: [];
        $__tl[] = [
            'tool' => (string) ($pl['tool'] ?? ''),
            'time' => substr((string) $ev['created_at'], 11, 5),
        ];
    }
    $__cumP = 0; $__cumA = 0;
    foreach ($__tl as $te) {
        if ($te['tool'] === 'subfinder') { $__cumP += 1; } else { $__cumA += 1; }
        $timeline['labels'][] = $te['time'] . ' ' . $te['tool'];
        $timeline['passive'][] = $__cumP;
        $timeline['active'][] = $__cumA;
    }
}
// minimal 2 titik biar garis terlihat
if (count($timeline['labels']) < 2) {
    $timeline = ['labels' => ['start', 'now'], 'passive' => [0, 0], 'active' => [0, 0]];
}

// findings terbaru milik user — untuk panel Vulnerability Assessment + detail
$findings = Database::run(
    "SELECT f.id, f.severity, f.title, f.cvss_score, f.cwe, f.cve, f.summary,
            f.impact, f.remediation, f.evidence_path, f.source_tool, t.value AS asset
     FROM findings f JOIN workflow_runs wr ON wr.id = f.run_id
     LEFT JOIN targets t ON t.id = f.asset_id
     WHERE $ownFilter
     ORDER BY FIELD(f.severity,'critical','high','medium','low','info'), f.id DESC LIMIT 50"
)->fetchAll(PDO::FETCH_ASSOC);
$findings = array_map(function ($f) {
    return [
        'id' => (int) $f['id'], 'severity' => $f['severity'], 'title' => $f['title'],
        'cvss' => $f['cvss_score'] !== null ? (float) $f['cvss_score'] : 0.0,
        'cwe' => $f['cwe'], 'cve' => $f['cve'], 'summary' => $f['summary'],
        'impact' => $f['impact'], 'remediation' => $f['remediation'],
        'evidence' => $f['evidence_path'], 'asset' => $f['asset'] ?? '—',
        'tool' => $f['source_tool'],
        'status' => 'unverified',
    ];
}, $findings);
$firstFinding = $findings[0] ?? [
    'id' => 0, 'severity' => 'info', 'title' => 'Belum ada findings', 'cvss' => 0.0,
    'cwe' => null, 'cve' => null, 'summary' => 'Jalankan workflow untuk mengumpulkan temuan.',
    'impact' => '—', 'remediation' => '—', 'evidence' => null, 'asset' => '—',
    'tool' => '—', 'status' => 'unverified',
];

// module pipeline — tools.json enabled (sama run page)
$pipeline = [];
$tj = json_decode((string) @file_get_contents(BASE_PATH . '/orchestrator/config/tools.json'), true);
if (is_array($tj) && isset($tj['tools'])) {
    foreach ($tj['tools'] as $t) {
        if (!is_array($t) || !($t['enabled'] ?? false)) continue;
        $pipeline[] = ['id' => $t['id'], 'name' => $t['name'], 'duration' => '—', 'status' => 'pending'];
    }
}
$engineP = ['runtime' => 'online', 'model' => 'glm-5.3', 'workers' => $runsActive, 'workers_total' => 8, 'queue' => 0, 'deps' => 'ok'];
$envStatus = [
    ['label' => 'Target Authorization', 'status' => $targetsInScope > 0 ? 'online' : 'offline'],
    ['label' => 'Go Tools Engine', 'status' => 'online'],
    ['label' => 'Python Engine', 'status' => 'online'],
    ['label' => 'Network Access', 'status' => 'online'],
];

// Phase 4: AI log pakai run terbaru (asli) milik user
$user = Auth::user();
$latestRun = Database::run(
    'SELECT id, status FROM workflow_runs
     WHERE ? = \'superadmin\' OR created_by = ?
     ORDER BY created_at DESC LIMIT 1',
    [$user['role'], (int) $user['id']]
)->fetch();

$icons = [
  'db'    => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>',
  'host'  => '<rect x="2" y="3" width="20" height="6" rx="1"/><rect x="2" y="13" width="20" height="6" rx="1"/><path d="M6 6h.01M6 16h.01"/>',
  'warn'  => '<path d="M10.3 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.7 3.86a2 2 0 0 0-3.4 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
  'crit'  => '<circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/>',
  'run'   => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
  'ai'    => '<path d="M12 2a4 4 0 0 1 4 4c1.7 0 3 1.3 3 3s-1.3 3-3 3h-1v2h-2v-2H9v2H7v-2c-1.7 0-3-1.3-3-3s1.3-3 3-3a4 4 0 0 1 4-4z"/><circle cx="12" cy="8" r="1"/>',
];

function step_status_icon(string $status): string
{
    return match ($status) {
        'completed' => '<span class="step-status">✓ COMPLETED</span>',
        'running'   => '<span class="step-status">◌ RUNNING</span>',
        default     => '<span class="step-status">PENDING</span>',
    };
}
?>
<div class="page-head">
  <div>
    <h1 class="page-title">Overview</h1>
    <p class="page-sub">Executive snapshot — <?= $latestRunRow ? e(($labels[$latestRunRow['current_phase']] ?? $latestRunRow['current_phase'] ?? '—') . ' · ' . $latestRunRow['template_name']) : 'belum ada run' ?></p>
  </div>
  <div class="mono text-muted"><?= $ops['run_id'] ?></div>
</div>

<!-- 4.3 Stat cards -->
<div class="grid-stats">
  <?php foreach ($stats as $card): ?>
    <div class="stat-card<?= isset($card['tone']) ? ' tone-' . $card['tone'] : '' ?>">
      <div class="stat-head">
        <span class="stat-icon">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><?= $icons[$card['icon']] ?></svg>
        </span>
        <span class="stat-label"><?= htmlspecialchars($card['label']) ?></span>
      </div>
      <div class="stat-value-row">
        <span class="stat-value"><?= $card['value'] ?></span>
        <span class="stat-total">/ <?= $card['total'] ?></span>
        <span class="spacer"></span>
        <span class="stat-trend<?php
          $t = (int) $card['trend'];
          $good = $card['good'] ?? true;
          // panah = arah angka; warna merah bila perubahan buruk (vulns naik, asset turun)
          $badMove = ($t > 0 && !$good) || ($t < 0 && $good);
          echo $badMove ? ' down' : ''; ?>"><?= $t > 0 ? '↑' : ($t < 0 ? '↓' : '·') ?> <?= abs($t) ?></span>
      </div>
      <?php if (isset($card['progress'])): ?>
        <div class="stat-progress" title="<?= $card['progress'] ?>%"><div class="bar" style="width: <?= $card['progress'] ?>%"></div></div>
      <?php else: ?>
        <div class="stat-spark"><canvas data-spark="<?= htmlspecialchars(json_encode($sparks[$card['key']])) ?>"<?= isset($card['tone']) ? ' data-spark-tone="' . $card['tone'] . '"' : '' ?>></canvas></div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<!-- 4.4 Operations Overview + 4.5 Security Summary -->
<div class="grid-mid">
  <section class="panel" id="operations">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">Operations Overview</h2>
        <p class="panel-subtitle">Penetration Testing Workflow — <?= htmlspecialchars($ops['run_id']) ?></p>
      </div>
      <span class="badge badge-cyan"><span class="dot"></span>RUNNING</span>
    </div>

    <div class="stepper">
      <?php foreach ($stepper as $st): ?>
        <div class="step <?= $st['status'] ?>">
          <span class="step-circle"><?= $st['num'] ?></span>
          <span class="step-label"><?= htmlspecialchars($st['label']) ?></span>
          <span class="step-time"><?= $st['time'] ?? '--' ?></span>
          <?= step_status_icon($st['status']) ?>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="ops-meta">
      <div class="ops-meta-item" style="flex:1;min-width:160px">
        <span class="ops-meta-label">OVERALL PROGRESS</span>
        <div class="progressbar mt-3"><div class="bar" style="width: <?= $ops['progress_pct'] ?>%"></div></div>
        <span class="ops-meta-value"><?= $ops['progress_pct'] ?>%</span>
      </div>
      <div class="ops-meta-item">
        <span class="ops-meta-label">CURRENT MODULE</span>
        <span class="ops-meta-value"><?= htmlspecialchars($ops['current_module']) ?></span>
      </div>
      <div class="ops-meta-item">
        <span class="ops-meta-label">ELAPSED TIME</span>
        <span class="ops-meta-value" data-elapsed="<?= $latestRunRow && $latestRunRow['started_at'] ? (time() - strtotime((string) $latestRunRow['started_at'])) : 0 ?>">—</span>
      </div>
      <div class="ops-meta-item">
        <span class="ops-meta-label">COMPLETED TASKS</span>
        <span class="ops-meta-value"><?= $ops['tasks_done'] ?> / <?= $ops['tasks_total'] ?></span>
      </div>
    </div>
  </section>

  <section class="panel">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">Security Summary</h2>
        <p class="panel-subtitle">Open findings breakdown</p>
      </div>
    </div>
    <div class="donut-wrap">
      <div class="donut-chart">
        <canvas data-donut="<?= htmlspecialchars(json_encode($secSum['severity'])) ?>"></canvas>
        <div class="donut-center">
          <div>
            <div class="num"><?= $secSum['open_findings'] ?></div>
            <div class="lbl">OPEN</div>
          </div>
        </div>
      </div>
      <div class="donut-legend">
        <?php
        $total = max(1, $secSum['open_findings']);
        foreach ($secSum['severity'] as $sev):
          $pct = round($sev['value'] / $total * 100);
        ?>
          <div class="legend-row">
            <span class="legend-swatch" style="background: var(--sev-<?= $sev['color'] ?>); box-shadow: 0 0 6px var(--sev-<?= $sev['color'] ?>)"></span>
            <span class="legend-label"><?= $sev['label'] ?></span>
            <span class="legend-val"><?= $sev['value'] ?></span>
            <span class="legend-pct"><?= $pct ?>%</span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="cvss-rows">
      <?php
      $cvssMax = max(array_column($secSum['cvss'], 'value'));
      $cvssColor = fn($i) => ['#F43F5E', '#EC4899', '#F59E0B', '#22D3EE'][$i] ?? '#64748B';
      foreach ($secSum['cvss'] as $i => $row): ?>
        <div class="cvss-row">
          <span class="cvss-range"><?= htmlspecialchars($row['range']) ?></span>
          <div class="cvss-bar"><div class="bar" style="width: <?= round($row['value'] / max(1, $cvssMax) * 100) ?>%; background: <?= $cvssColor($i) ?>"></div></div>
          <span class="cvss-count"><?= $row['value'] ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
</div>

<!-- 4.6 AI Orchestration Log (pengganti Network Topology — Opsi A) + 4.7 Reconnaissance -->
<div class="grid-mid">
  <section class="panel">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">AI Orchestration Log</h2>
        <p class="panel-subtitle">Live feed keputusan AI — tool selesai, ringkasan, &amp; pilihan berikutnya</p>
      </div>
      <?php if ($latestRun): ?>
        <span class="badge badge-cyan"><span class="dot"></span>glm-5.3 · run <?= e(substr((string) $latestRun['id'], 0, 8)) ?></span>
      <?php else: ?>
        <span class="badge badge-cyan"><span class="dot"></span>glm-5.3</span>
      <?php endif; ?>
    </div>
    <?php if ($latestRun): ?>
    <div class="ailog" data-ai-log-run="<?= e((string) $latestRun['id']) ?>">
      <div class="ailog-entry">
        <span class="ailog-time">--:--:--</span>
        <div class="ailog-body">
          <span class="ailog-tag ai_decision">AI DECISION</span>
          <span class="ailog-tool">stream</span>
          <p class="ailog-text">menghubungkan ke event stream run terbaru…</p>
        </div>
      </div>
    </div>
    <?php else: ?>
    <div class="ailog">
      <div class="ailog-empty">Belum ada run — buat lewat Automation Workflows.</div>
    </div>
    <?php endif; ?>
  </section>

  <section class="panel">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">Reconnaissance</h2>
        <p class="panel-subtitle">Target inventory &amp; discovery timeline</p>
      </div>
    </div>
    <div data-tabs>
      <div class="tabs">
        <button type="button" class="tab-btn active" data-tab="inventory">Target Inventory</button>
        <button type="button" class="tab-btn" data-tab="dns">DNS Records</button>
        <button type="button" class="tab-btn" data-tab="subdomains">Subdomains</button>
        <button type="button" class="tab-btn" data-tab="ips">IP Addresses</button>
        <button type="button" class="tab-btn" data-tab="services">Services</button>
      </div>
      <div class="tab-pane active" data-pane="inventory">
        <div class="table-wrap">
          <table class="tbl">
            <thead>
              <tr><th>Asset</th><th>Type</th><th>Service</th><th>Status</th><th>Discovered</th><th>Scope</th></tr>
            </thead>
            <tbody>
              <?php foreach ($inventory as $row): ?>
                <tr>
                  <td class="mono cell-strong"><?= htmlspecialchars($row['asset']) ?></td>
                  <td class="mono"><?= strtoupper($row['type']) ?></td>
                  <td class="mono"><?= htmlspecialchars($row['service']) ?></td>
                  <td><span class="st-<?= $row['status'] ?> mono">● <?= strtoupper($row['status']) ?></span></td>
                  <td class="mono"><?= $row['discovered'] ?></td>
                  <td><span class="scope-badge <?= $row['scope'] === 'in' ? 'scope-in' : 'scope-out' ?>"><?= $row['scope'] === 'in' ? 'IN SCOPE' : 'OUT' ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php foreach (['dns' => 'DNS Records', 'subdomains' => 'Subdomains', 'ips' => 'IP Addresses', 'services' => 'Services'] as $paneKey => $paneLabel): ?>
        <div class="tab-pane" data-pane="<?= $paneKey ?>">
          <div class="empty-state">
            <svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.2" class="es-icon"><circle cx="12" cy="12" r="9"/><path d="M8 12h8"/></svg>
            <div class="es-title"><?= $paneLabel ?></div>
            <div class="es-desc">Coming from real scan — panel ini akan terisi otomatis dari hasil tool recon (Phase 3+).</div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="chart-box mt-3" style="height:120px">
      <canvas data-timeline="<?= htmlspecialchars(json_encode($timeline)) ?>"></canvas>
    </div>
    <div class="ops-meta">
      <div class="ops-meta-item"><span class="ops-meta-label">TOTAL ASSETS</span><span class="ops-meta-value"><?= count($inventory) ?></span></div>
      <div class="ops-meta-item"><span class="ops-meta-label">IN SCOPE</span><span class="ops-meta-value"><?= count(array_filter($inventory, fn($r) => $r['scope'] === 'in')) ?></span></div>
      <div class="ops-meta-item"><span class="ops-meta-label">ONLINE</span><span class="ops-meta-value"><?= count(array_filter($inventory, fn($r) => $r['status'] === 'online')) ?></span></div>
    </div>
  </section>
</div>

<!-- 4.8 Vulnerability Assessment + 4.9 Tool Automation Engine -->
<div class="grid-bottom">
  <section class="panel">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">Vulnerability Assessment</h2>
        <p class="panel-subtitle">Temuan aktif — klik baris untuk detail</p>
      </div>
    </div>
    <div data-tabs>
      <div class="tabs">
        <button type="button" class="tab-btn active" data-tab="f-list">Findings</button>
        <button type="button" class="tab-btn" data-tab="f-asset">By Asset</button>
        <button type="button" class="tab-btn" data-tab="f-cvss">By CVSS</button>
        <button type="button" class="tab-btn" data-tab="f-cwe">By CWE</button>
      </div>
      <div class="tab-pane active" data-pane="f-list">
        <div class="table-wrap" style="max-height:320px; overflow-y:auto">
          <table class="tbl" id="findings-table">
            <thead><tr><th>Severity</th><th>Title</th><th>Asset</th><th>CVSS</th><th>CWE</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach ($findings as $f): ?>
                <tr class="finding-row<?= $f['id'] === $firstFinding['id'] ? ' row-selected' : '' ?>" data-finding="<?= $f['id'] ?>">
                  <td><span class="sev sev-<?= $f['severity'] ?>"><?= strtoupper($f['severity']) ?></span></td>
                  <td class="cell-strong"><?= htmlspecialchars($f['title']) ?></td>
                  <td class="mono"><?= htmlspecialchars($f['asset']) ?></td>
                  <td class="mono"><?= $f['cvss'] !== null ? number_format((float) $f['cvss'], 1) : '--' ?></td>
                  <td class="mono"><?= $f['cwe'] ?? '--' ?></td>
                  <td><span class="mono text-muted"><?= strtoupper(str_replace('_', ' ', $f['status'])) ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php foreach (['f-asset' => 'By Asset', 'f-cvss' => 'By CVSS', 'f-cwe' => 'By CWE'] as $k2 => $l2): ?>
        <div class="tab-pane" data-pane="<?= $k2 ?>">
          <div class="empty-state"><div class="es-title"><?= $l2 ?></div><div class="es-desc">Agregasi per <?= strtolower(str_replace('f-', '', $k2)) ?> — menyusul bersama data scan nyata.</div></div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="panel">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">Tool Automation Engine</h2>
        <p class="panel-subtitle">Runtime <?= strtoupper($engineP['runtime']) ?> · model <?= htmlspecialchars($engineP['model']) ?></p>
      </div>
    </div>
    <div class="ops-meta" style="margin-bottom: var(--sp-3); padding-top:0; border-top:none">
      <div class="ops-meta-item"><span class="ops-meta-label">RUNTIME</span><span class="ops-meta-value" style="color: var(--status-success)"><?= strtoupper($engineP['runtime']) ?></span></div>
      <div class="ops-meta-item"><span class="ops-meta-label">WORKERS</span><span class="ops-meta-value"><?= $engineP['workers'] ?> / <?= $engineP['workers_total'] ?></span></div>
      <div class="ops-meta-item"><span class="ops-meta-label">QUEUE</span><span class="ops-meta-value"><?= $engineP['queue'] ?></span></div>
      <div class="ops-meta-item"><span class="ops-meta-label">DEPS</span><span class="ops-meta-value" style="color: var(--status-success)"><?= strtoupper($engineP['deps']) ?></span></div>
    </div>
    <div class="pipeline" id="engine-pipeline">
      <?php foreach ($pipeline as $m): ?>
        <div class="module-card" data-module="<?= htmlspecialchars($m['id']) ?>">
          <span class="module-name"><?= htmlspecialchars($m['name']) ?></span>
          <span class="module-duration"><?= $m['duration'] ?></span>
          <span class="module-status <?= $m['status'] ?>"><?= strtoupper($m['status']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
    <?php if ($latestRun): ?>
    <div class="terminal mt-3" data-terminal="main" data-run-id="<?= e($latestRun['id']) ?>"></div>
    <p class="panel-subtitle mt-3 mono">LIVE FEED: run <?= e(substr((string)$latestRun['id'], 0, 8)) ?> · <?= e($latestRun['status']) ?></p>
    <?php else: ?>
    <div class="terminal mt-3" data-terminal="main"></div>
    <p class="panel-subtitle mt-3">Belum ada run — buat workflow di menu Automation Workflows.</p>
    <?php endif; ?>
    <div class="term-actions">
      <button type="button" class="btn btn-sm btn-primary" data-term-toggle="main">Live Logs: ON</button>
      <button type="button" class="btn btn-sm" data-term-clear="main">Clear</button>
    </div>

    <div class="mt-4" style="display:grid; grid-template-columns: 1fr 1fr; gap: var(--sp-3)">
      <div class="panel" style="padding: var(--sp-3)">
        <h3 class="panel-title" style="font-size: var(--fs-sm)">Quick Actions</h3>
        <div class="quick-actions mt-3">
          <button type="button" class="btn btn-primary">Run Assessment</button>
          <button type="button" class="btn" onclick="location.href='/reports'">View Full Report</button>
          <button type="button" class="btn" onclick="location.href='/assets'">Manage Targets</button>
          <button type="button" class="btn btn-danger" data-modal-open="modal-stop">Stop All Tasks</button>
        </div>
      </div>
      <div class="panel" style="padding: var(--sp-3)">
        <h3 class="panel-title" style="font-size: var(--fs-sm)">Environment Status</h3>
        <div class="env-status mt-3">
          <?php foreach ($envStatus as $e): ?>
            <div class="env-item"><span><?= htmlspecialchars($e['label']) ?></span><span class="st <?= $e['status'] ?>"><?= strtoupper($e['status']) ?></span></div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>
</div>

<!-- Detail finding panel (4.8 kanan) — data via JS dari window.FINDINGS -->
<div class="grid-bottom" id="vuln-detail-row">
  <section class="panel">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">Finding Detail</h2>
        <p class="panel-subtitle">Field lengkap temuan terpilih</p>
      </div>
      <span class="mono text-muted" id="fd-id">#<?= $firstFinding['id'] ?></span>
    </div>
    <div class="finding-detail" id="finding-detail">
      <div class="fd-head">
        <span class="fd-title" id="fd-title"><?= htmlspecialchars($firstFinding['title']) ?></span>
        <span class="sev sev-<?= $firstFinding['severity'] ?>" id="fd-sev"><?= strtoupper($firstFinding['severity']) ?></span>
        <span class="fd-score sev-<?= $firstFinding['severity'] ?>" id="fd-cvss"><?= $firstFinding['cvss'] !== null ? number_format((float) $firstFinding['cvss'], 1) : '--' ?></span>
      </div>
      <div class="fd-fields">
        <div class="fd-field"><span class="fd-label">ASSET</span><span class="fd-value mono" id="fd-asset"><?= htmlspecialchars($firstFinding['asset']) ?></span></div>
        <div class="fd-field"><span class="fd-label">CWE / CVE</span><span class="fd-value mono" id="fd-cwe"><?= htmlspecialchars(($firstFinding['cwe'] ?? '--') . ' / ' . ($firstFinding['cve'] ?? '--')) ?></span></div>
        <div class="fd-field"><span class="fd-label">SUMMARY</span><span class="fd-value" id="fd-summary"><?= htmlspecialchars($firstFinding['summary']) ?></span></div>
        <div class="fd-field"><span class="fd-label">EVIDENCE</span><span class="fd-value mono" id="fd-evidence"><?= htmlspecialchars($firstFinding['evidence'] ?? '--') ?></span></div>
        <div class="fd-field"><span class="fd-label">IMPACT</span><span class="fd-value" id="fd-impact"><?= htmlspecialchars($firstFinding['impact']) ?></span></div>
        <div class="fd-field"><span class="fd-label">REMEDIATION</span><span class="fd-value" id="fd-remediation"><?= htmlspecialchars($firstFinding['remediation']) ?></span></div>
        <div class="fd-field"><span class="fd-label">VERIFICATION</span><span class="fd-value" id="fd-verification">UNVERIFIED · source <?= htmlspecialchars($firstFinding['tool']) ?></span></div>
      </div>
    </div>
  </section>
  <section class="panel" id="audit">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">Audit Trail (Ringkas)</h2>
        <p class="panel-subtitle">5 event eksekusi terakhir — log lengkap di menu Audit Logs</p>
      </div>
    </div>
    <div class="ailog">
      <?php
      $auditTrail = Database::run(
        'SELECT created_at, action, SUBSTRING(CAST(detail AS CHAR), 1, 90) AS detail
         FROM audit_logs ORDER BY id DESC LIMIT 5'
      )->fetchAll(PDO::FETCH_ASSOC);
      $auditTrail = array_map(fn($a) => [substr((string) $a['created_at'], 11, 8), $a['action'] . ' ' . ($a['detail'] ?? ''), $a['action'], 'ok'], $auditTrail);
      foreach ($auditTrail as $a): ?>
        <div class="ailog-entry">
          <span class="ailog-time"><?= $a[0] ?></span>
          <div class="ailog-body"><span class="mono" style="font-size:11px; color: var(--text-secondary)"><?= htmlspecialchars($a[1]) ?></span></div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
</div>

<script>
window.FINDINGS = <?= json_encode($findings) ?>;
(function () {
  var rows = document.querySelectorAll(".finding-row");
  rows.forEach(function (row) {
    row.addEventListener("click", function () {
      var f = window.FINDINGS.find(function (x) { return String(x.id) === row.dataset.finding; });
      if (!f) return;
      rows.forEach(function (r) { r.classList.toggle("row-selected", r === row); });
      var set = function (id, val) { var el = document.getElementById(id); if (el) el.textContent = val; };
      set("fd-id", "#" + f.id);
      set("fd-title", f.title);
      set("fd-sev", f.severity.toUpperCase());
      set("fd-cvss", f.cvss != null ? Number(f.cvss).toFixed(1) : "--");
      set("fd-asset", f.asset);
      set("fd-cwe", (f.cwe || "--") + " / " + (f.cve || "--"));
      set("fd-summary", f.summary);
      set("fd-evidence", f.evidence || "--");
      set("fd-impact", f.impact);
      set("fd-remediation", f.remediation);
      set("fd-verification", "UNVERIFIED · source " + f.tool);
      ["fd-sev", "fd-cvss"].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.className = el.className.replace(/sev-\w+/, "sev-" + f.severity);
      });
    });
  });
})();
</script>

<!-- Modal Stop All Tasks (konfirmasi aksi destruktif) -->
<div class="modal-backdrop" id="modal-stop">
  <div class="modal">
    <div class="modal-head">
      <span class="modal-title">Stop All Tasks?</span>
      <button type="button" class="modal-close" data-modal-close>&times;</button>
    </div>
    <div class="modal-body">
      <p class="fd-value">Semua run aktif milikmu (<?= (int) $runsActive ?> run) akan dihentikan paksa. Tool yang sedang jalan di-terminate, phase berjalan ditandai <span class="sev sev-high">STOPPED</span>. Aksi ini tidak bisa dibatalkan.</p>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn" data-modal-close>Cancel</button>
      <button type="button" class="btn btn-danger" data-modal-close id="btn-stop-all">Stop All</button>
    </div>
  </div>
</div>
