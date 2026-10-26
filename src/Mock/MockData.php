<?php
/**
 * Mock data Phase 1 — SEMUA dummy, tidak ada koneksi DB/orchestrator/AI.
 * Diganti data asli di Phase 2-5.
 */

declare(strict_types=1);

final class MockData
{
    /** Top bar: engagement aktif. */
    public static function engagement(): array
    {
        return [
            'name' => 'ACME_CORP_ASSESSMENT_2026',
            'id'   => 'ENG-2026-0417',
        ];
    }

    /** Top bar: AI engine + workers/queue. */
    public static function engineStatus(): array
    {
        return [
            'ai_model'     => 'glm-5.3',
            'ai_status'    => 'online',
            'workers_used' => 4,
            'workers_total' => 8,
            'queue'        => 3,
            'notifications' => 5,
            'user'         => ['name' => 'Capt. Rakha', 'role' => 'Security Researcher'],
        ];
    }

    /** Stat cards (Bagian 4.3). */
    public static function statCards(): array
    {
        return [
            ['key' => 'assets',     'label' => 'Authorized Assets',        'value' => 12, 'total' => 14, 'trend' => '+2', 'trend_up' => true,  'icon' => 'db'],
            ['key' => 'hosts',      'label' => 'Hosts Discovered',         'value' => 87, 'total' => 124, 'trend' => '+18', 'trend_up' => true, 'icon' => 'host'],
            ['key' => 'open_vulns', 'label' => 'Open Vulnerabilities',     'value' => 34, 'total' => 61, 'trend' => '+6', 'trend_up' => true,  'icon' => 'warn', 'tone' => 'high'],
            ['key' => 'critical',   'label' => 'Critical Findings',        'value' => 7,  'total' => 9,  'trend' => '+2', 'trend_up' => true,  'icon' => 'crit', 'tone' => 'critical'],
            ['key' => 'runs',       'label' => 'Completed Workflow Runs',  'value' => 23, 'total' => 25, 'trend' => '+3', 'trend_up' => true,  'icon' => 'run'],
            ['key' => 'ai_active',  'label' => 'AI Orchestrations Active', 'value' => 2,  'total' => 8,  'trend' => '0',  'trend_up' => false, 'icon' => 'ai', 'progress' => 42],
        ];
    }

    /** Sparkline data per stat card (Chart.js line mini). */
    public static function sparklines(): array
    {
        return [
            'assets'     => [8, 9, 9, 10, 10, 11, 11, 12, 12, 12, 12, 12],
            'hosts'      => [30, 38, 42, 50, 55, 61, 63, 70, 74, 80, 84, 87],
            'open_vulns' => [12, 15, 18, 22, 21, 25, 28, 27, 30, 33, 32, 34],
            'critical'   => [1, 2, 2, 3, 4, 4, 5, 5, 6, 6, 7, 7],
            'runs'       => [2, 4, 7, 9, 11, 14, 16, 18, 19, 21, 22, 23],
            'ai_active'  => [0, 1, 2, 1, 3, 2, 1, 2, 3, 1, 2, 2],
        ];
    }

    /** Stepper 8 step Operations Overview (Bagian 4.4). */
    public static function stepper(): array
    {
        return [
            ['num' => '01', 'label' => 'Scope Validation',      'status' => 'completed', 'time' => '0m 42s'],
            ['num' => '02', 'label' => 'Reconnaissance',        'status' => 'completed', 'time' => '12m 18s'],
            ['num' => '03', 'label' => 'Service Enumeration',   'status' => 'completed', 'time' => '8m 05s'],
            ['num' => '04', 'label' => 'Vulnerability Assess.', 'status' => 'running',   'time' => '05m 12s'],
            ['num' => '05', 'label' => 'Exploitation Validation', 'status' => 'pending'],
            ['num' => '06', 'label' => 'Persistence Simulation', 'status' => 'pending'],
            ['num' => '07', 'label' => 'Cleanup & Verification', 'status' => 'pending'],
            ['num' => '08', 'label' => 'Report Generation',     'status' => 'pending'],
        ];
    }

    public static function operations(): array
    {
        return [
            'run_id' => 'RUN-2026-0417-A7F3',
            'progress_pct' => 46,
            'current_module' => 'nuclei_runner.py',
            'elapsed' => '26m 17s',
            'tasks_done' => 14,
            'tasks_total' => 31,
        ];
    }

    /** Security summary donut + CVSS (Bagian 4.5). */
    public static function securitySummary(): array
    {
        return [
            'open_findings' => 34,
            'severity' => [
                ['label' => 'Critical', 'value' => 7,  'color' => 'critical'],
                ['label' => 'High',     'value' => 12, 'color' => 'high'],
                ['label' => 'Medium',   'value' => 9,  'color' => 'medium'],
                ['label' => 'Low',      'value' => 4,  'color' => 'low'],
                ['label' => 'Info',     'value' => 2,  'color' => 'info'],
            ],
            'cvss' => [
                ['range' => '9.0 - 10.0', 'value' => 6],
                ['range' => '7.0 - 8.9',  'value' => 11],
                ['range' => '4.0 - 6.9',  'value' => 12],
                ['range' => '0.1 - 3.9',  'value' => 5],
            ],
        ];
    }

    /** AI Orchestration Log (Bagian 4.6 — opsi A). */
    public static function aiLog(): array
    {
        return [
            [
                'time' => '15:38:41', 'type' => 'tool_completed',
                'tool' => 'subfinder',
                'summary' => 'Ditemukan 23 subdomain dari domain utama, 4 di antaranya resolution ke IP eksternal yang sama dengan web server produksi.',
            ],
            [
                'time' => '15:39:02', 'type' => 'ai_decision',
                'tool' => 'httpx',
                'reasoning' => 'Subdomain list sudah cukup besar. Probe liveness dulu sebelum enumerasi lanjut — httpx memberi status code + tech stack untuk prioritasi target berikutnya.',
            ],
            [
                'time' => '15:41:55', 'type' => 'tool_completed',
                'tool' => 'httpx',
                'summary' => '19 dari 23 host live. 3 endpoint HTTPS dengan header server usang (nginx/1.18.0), 1 host memakai HTTP saja.',
            ],
            [
                'time' => '15:42:10', 'type' => 'ai_decision',
                'tool' => 'nuclei',
                'reasoning' => 'Tech stack usang terdeteksi. Jalankan nuclei medium+high+critical pada endpoint nginx/1.18.0 — kemungkinan besar CVE disclosure version ada.',
            ],
            [
                'time' => '15:47:31', 'type' => 'tool_completed',
                'tool' => 'nuclei',
                'summary' => '6 temuan positif: 2 CVE High (CVE-2021-23017 resolver, CVE-2019-20372 request smuggling), 3 informational disclosure, 1 missing security header.',
            ],
        ];
    }

    /** Recon target inventory (Bagian 4.7). */
    public static function reconInventory(): array
    {
        return [
            ['asset' => 'acme-corp.com',        'type' => 'domain', 'service' => 'HTTPS/443', 'status' => 'online',  'discovered' => '2026-10-24 09:12', 'scope' => 'in'],
            ['asset' => 'www.acme-corp.com',    'type' => 'domain', 'service' => 'HTTPS/443', 'status' => 'online',  'discovered' => '2026-10-24 09:12', 'scope' => 'in'],
            ['asset' => 'api.acme-corp.com',    'type' => 'domain', 'service' => 'HTTPS/443', 'status' => 'online',  'discovered' => '2026-10-24 09:15', 'scope' => 'in'],
            ['asset' => 'staging.acme-corp.com','type' => 'domain', 'service' => 'HTTPS/443', 'status' => 'online',  'discovered' => '2026-10-24 09:15', 'scope' => 'in'],
            ['asset' => 'vpn.acme-corp.com',    'type' => 'domain', 'service' => 'TCP/1194',  'status' => 'online',  'discovered' => '2026-10-24 09:18', 'scope' => 'out'],
            ['asset' => 'mail.acme-corp.com',   'type' => 'domain', 'service' => 'SMTP/25',   'status' => 'offline', 'discovered' => '2026-10-24 09:20', 'scope' => 'out'],
            ['asset' => '203.0.113.24',         'type' => 'ip',     'service' => 'HTTPS/443', 'status' => 'online',  'discovered' => '2026-10-24 10:02', 'scope' => 'in'],
            ['asset' => '203.0.113.25',         'type' => 'ip',     'service' => 'SSH/22',    'status' => 'online',  'discovered' => '2026-10-24 10:02', 'scope' => 'in'],
        ];
    }

    public static function assetTimeline(): array
    {
        return [
            'labels' => ['10/19', '10/20', '10/21', '10/22', '10/23', '10/24', '10/25', '10/26'],
            'passive' => [2, 4, 6, 9, 12, 15, 17, 18],
            'active'  => [0, 1, 3, 5, 9, 14, 17, 19],
        ];
    }

    /** Findings table + detail (Bagian 4.8). */
    public static function findings(): array
    {
        return [
            ['id' => 1, 'severity' => 'critical', 'title' => 'Remote Code Execution — ImageMagick Policy Misconfig', 'asset' => 'api.acme-corp.com', 'cvss' => 9.8, 'cwe' => 'CWE-78', 'status' => 'open', 'cve' => 'CVE-2016-3714', 'tool' => 'nuclei',
             'summary' => 'ImageMagick di server upload memungkinkan eksekusi command via MVG payload pada file gambar yang diupload.',
             'evidence' => '/storage/evidence/run-a7f3/nuclei-img-982.114.json', 'impact' => 'Attacker dapat mengeksekusi arbitrary command di server app dengan privilege proses PHP-FPM.', 'remediation' => 'Patch ImageMagick ke ≥ 7.0.1-0 dan terapkan policy.xml yang membatasi coder ke PNG/JPEG saja.',
             'refs' => ['https://cve.mitre.org/cgi-bin/cvename.cgi?name=CVE-2016-3714', 'https://owasp.org/www-community/vulnerabilities/Command_Injection']],
            ['id' => 2, 'severity' => 'critical', 'title' => 'SQL Injection — Login Form Parameter `password`', 'asset' => 'staging.acme-corp.com', 'cvss' => 9.1, 'cwe' => 'CWE-89', 'status' => 'in_progress', 'cve' => null, 'tool' => 'custom_sqli_probe',
             'summary' => 'Parameter password pada form login rentan boolean-based blind SQL injection (MySQL).',
             'evidence' => '/storage/evidence/run-a7f3/sqli-140.2.json', 'impact' => 'Bypass authentication + eksfiltrasi tabel user via UNION.', 'remediation' => 'Gunakan prepared statement (PDO bind) + input validation whitelist.',
             'refs' => ['https://owasp.org/www-community/attacks/SQL_Injection']],
            ['id' => 3, 'severity' => 'high', 'title' => 'Resolver Off-by-One — nginx 1.18.0 (CVE-2021-23017)', 'asset' => 'www.acme-corp.com', 'cvss' => 7.7, 'cwe' => 'CWE-193', 'status' => 'open', 'cve' => 'CVE-2021-23017', 'tool' => 'nuclei',
             'summary' => 'Versi nginx rentan off-by-one saat resolusi DNS upstream, memungkinkan overwrite memory 1 byte.',
             'evidence' => '/storage/evidence/run-a7f3/nuclei-ngx-77.3.json', 'impact' => 'Potensi RCE pada worker process nginx pada kondisi tertentu.', 'remediation' => 'Upgrade nginx ke ≥ 1.21.0.',
             'refs' => ['https://nvd.nist.gov/vuln/detail/CVE-2021-23017']],
            ['id' => 4, 'severity' => 'high', 'title' => 'Stored XSS — Comment Field', 'asset' => 'www.acme-corp.com', 'cvss' => 7.4, 'cwe' => 'CWE-79', 'status' => 'open', 'cve' => null, 'tool' => 'nuclei',
             'summary' => 'Field komentar blog tidak melakukan escape output, payload <script> tersimpan dan dieksekusi di browser visitor lain.',
             'evidence' => '/storage/evidence/run-a7f3/nuclei-xss-74.51.json', 'impact' => 'Session hijacking user lain termasuk admin.', 'remediation' => 'Escape output (htmlspecialchars) + CSP header.',
             'refs' => ['https://owasp.org/www-community/attacks/xss/']],
            ['id' => 5, 'severity' => 'high', 'title' => 'JWT Signature Tidak Diverifikasi', 'asset' => 'api.acme-corp.com', 'cvss' => 7.5, 'cwe' => 'CWE-347', 'status' => 'in_progress', 'cve' => null, 'tool' => 'custom_jwt_probe',
             'summary' => 'Endpoint /v2/session menerima alg=none dan signature kosong, token masih dianggap valid.',
             'evidence' => '/storage/evidence/run-a7f3/jwt-75.8.json', 'impact' => 'Forge token dengan role admin.', 'remediation' => 'Enforce HS256/RS256 dan reject alg none di library JWT.',
             'refs' => ['https://owasp.org/www-project-cheatsheets/cheatsheets/JSON_Web_Token_for_Java_Cheat_Sheet']],
            ['id' => 6, 'severity' => 'medium', 'title' => 'Server Version Disclosure — nginx/1.18.0', 'asset' => 'www.acme-corp.com', 'cvss' => 5.3, 'cwe' => 'CWE-200', 'status' => 'open', 'cve' => null, 'tool' => 'httpx',
             'summary' => 'Header Server: nginx/1.18.0 terekspos pada semua response.',
             'evidence' => '/storage/evidence/run-a7f3/httpx-53.1.json', 'impact' => 'Membantu attacker memetakan CVE spesifik versi.', 'remediation' => 'server_tokens off; di nginx.conf.',
             'refs' => []],
            ['id' => 7, 'severity' => 'medium', 'title' => 'Missing HSTS Header', 'asset' => 'acme-corp.com', 'cvss' => 4.3, 'cwe' => 'CWE-319', 'status' => 'open', 'cve' => null, 'tool' => 'nuclei',
             'summary' => 'Tidak ada header Strict-Transport-Security pada response HTTPS.',
             'evidence' => '/storage/evidence/run-a7f3/nuclei-hst-43.7.json', 'impact' => 'User dapat di-downgrade ke HTTP via MITM.', 'remediation' => 'Tambah HSTS max-age 31536000 includeSubDomains.',
             'refs' => ['https://cheatsheetseries.owasp.org/cheatsheets/HTTP_Strict_Transport_Security_Cheat_Sheet']],
            ['id' => 8, 'severity' => 'low', 'title' => 'Directory Listing Aktif — /static/backup/', 'asset' => 'www.acme-corp.com', 'cvss' => 3.1, 'cwe' => 'CWE-548', 'status' => 'open', 'cve' => null, 'tool' => 'nuclei',
             'summary' => 'Directory listing menyala di folder static backup, mengekspos file .sql lama.',
             'evidence' => '/storage/evidence/run-a7f3/nuclei-dl-31.9.json', 'impact' => 'Kebocoran data historis.', 'remediation' => 'autoindex off + pindahkan backup keluar DocumentRoot.',
             'refs' => []],
            ['id' => 9, 'severity' => 'info', 'title' => 'Technology Detected — Vue.js 3.4', 'asset' => 'www.acme-corp.com', 'cvss' => 0.0, 'cwe' => null, 'status' => 'open', 'cve' => null, 'tool' => 'httpx',
             'summary' => 'Frontend memakai Vue.js 3.4 (terdeteksi via tech-detect).',
             'evidence' => null, 'impact' => 'Informasi stack untuk planning phase berikutnya.', 'remediation' => 'Tidak perlu action — informasional.',
             'refs' => []],
        ];
    }

    /** Tool Automation Engine (Bagian 4.9). */
    public static function modulePipeline(): array
    {
        return [
            ['name' => 'scope_guard.py',      'status' => 'done',    'duration' => '0.4s'],
            ['name' => 'subfinder_runner.py', 'status' => 'done',    'duration' => '38.2s'],
            ['name' => 'httpx_runner.py',     'status' => 'done',    'duration' => '21.7s'],
            ['name' => 'nuclei_runner.py',    'status' => 'running', 'duration' => '05:12'],
            ['name' => 'sqli_probe.py',       'status' => 'pending', 'duration' => '--'],
            ['name' => 'report_generator.py', 'status' => 'pending', 'duration' => '--'],
        ];
    }

    public static function enginePanel(): array
    {
        return [
            'runtime'   => 'online',
            'model'     => 'glm-5.3',
            'workers'   => 4,
            'workers_total' => 8,
            'queue'     => 3,
            'deps'      => 'healthy',
        ];
    }

    public static function environmentStatus(): array
    {
        return [
            ['label' => 'Target Authorization', 'status' => 'ok'],
            ['label' => 'Go Tools Engine',      'status' => 'ok'],
            ['label' => 'Python Engine',        'status' => 'ok'],
            ['label' => 'Network Access',       'status' => 'ok'],
        ];
    }

    /** Halaman workflows.php (1D). */
    public static function workflowTemplates(): array
    {
        return [
            ['slug' => 'full-web-app-assessment', 'name' => 'Full Web App Assessment', 'phases' => 7, 'runs' => 14, 'last' => '2026-10-25 16:40', 'desc' => 'Alur lengkap: scope → recon → enum → vuln → exploit → cleanup → report.'],
            ['slug' => 'external-recon-only',     'name' => 'External Recon Only',     'phases' => 4, 'runs' => 9,  'last' => '2026-10-26 08:15', 'desc' => 'Discovery & enumerasi tanpa scanning instrusif.'],
        ];
    }

    public static function recentRuns(): array
    {
        return [
            ['id' => 'RUN-2026-0417-A7F3', 'target' => 'acme-corp.com',    'template' => 'Full Web App Assessment', 'status' => 'running',   'progress' => 46],
            ['id' => 'RUN-2026-0416-C2D1', 'target' => 'staging.acme-corp.com', 'template' => 'External Recon Only', 'status' => 'completed', 'progress' => 100],
            ['id' => 'RUN-2026-0415-9E4B', 'target' => '203.0.113.24',     'template' => 'Full Web App Assessment', 'status' => 'completed', 'progress' => 100],
            ['id' => 'RUN-2026-0414-55A0', 'target' => 'acme-corp.com',    'template' => 'External Recon Only',  'status' => 'failed',    'progress' => 62],
        ];
    }

    /** Halaman findings.php (1D). */
    public static function overallSummary(): string
    {
        return "Assessment terhadap engagement ACME_CORP_ASSESSMENT_2026 menemukan 34 open findings "
            . "dengan 7 berkategori critical. Kombinasi Remote Code Execution pada endpoint upload API "
            . "dan SQL injection di form login staging memberi jalur kompromi end-to-end: dari unauthenticated "
            . "RCE hingga akses database langsung.\n\n"
            . "Kedua temuan critical tersebut berada di aset berbeda namun share infrastruktur PHP-FPM yang sama, "
            . "sehingga patching perlu dilakukan serentak agar tidak ada lateral movement lewat worker process. "
            . "Tematan high lainnya (JWT alg=none, stored XSS, nginx off-by-one) menambah permukaan serangan "
            . "yang bisa dipakai sebagai foothold alternatif.\n\n"
            . "Rekomendasi prioritas: (1) patch ImageMagick + policy.xml pada hari yang sama, "
            . "(2) refactor query login ke prepared statement, (3) enforce verifikasi signature JWT "
            . "sebelum endpoint API lain diekspos ke produksi.";
    }

    /** Halaman reports.php (1D). */
    public static function reports(): array
    {
        return [
            ['id' => 1, 'run' => 'RUN-2026-0416-C2D1', 'target' => 'staging.acme-corp.com', 'generated' => '2026-10-26 09:02', 'findings' => 11, 'pages' => 8, 'size' => '412 KB'],
            ['id' => 2, 'run' => 'RUN-2026-0415-9E4B', 'target' => '203.0.113.24',          'generated' => '2026-10-25 18:33', 'findings' => 6,  'pages' => 5, 'size' => '298 KB'],
        ];
    }

    /** admin/approvals.php (1D). */
    public static function pendingUsers(): array
    {
        return [
            ['id' => 4, 'name' => 'Dewi Anggraini',  'email' => 'dewi.a@team.id',   'username' => 'dewia',   'reason' => 'Pentester tim red — perlu akses untuk engagement Kartika.', 'registered' => '2026-10-25 11:20'],
            ['id' => 5, 'name' => 'Bayu Pratama',    'email' => 'bayu@contractor.id', 'username' => 'bayup',  'reason' => 'Konsultan eksternal untuk validasi exploit phase.',      'registered' => '2026-10-26 07:45'],
            ['id' => 6, 'name' => 'Sari Wulandari',  'email' => 'sari.w@team.id',   'username' => 'sariw',   'reason' => 'Blue team — butuh lihat findings untuk mitigasi.',        'registered' => '2026-10-26 08:10'],
        ];
    }
}
