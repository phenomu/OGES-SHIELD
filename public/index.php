<?php
/**
 * OGES-SHIELD — front controller.
 * Semua request HTTP lewat sini (DocumentRoot = public/).
 * Router sederhana buatan sendiri, tanpa framework.
 *
 * Catatan routing: php -S tidak rewrite /dashboard → /dashboard.php,
 * jadi Router normalisasi ekstensi .php otomatis.
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

// php -S router mode: file statis (css/js/font/img) dilayari server langsung,
// sisanya (termasuk path yang bentrok dengan direktori assets/admin/api) lewat router.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');
    if (is_file($file)) {
        return false;
    }
}

require BASE_PATH . '/src/Core/Router.php';
require BASE_PATH . '/src/Core/View.php';
require BASE_PATH . '/src/Mock/MockData.php';

$router = new Router();

// --- Route map (Phase 1: halaman UI dummy) ---
$router->add('GET', '/', 'dashboard');           // home = overview dashboard
$router->add('GET', '/dashboard', 'dashboard');
$router->add('GET', '/assets', 'assets');
$router->add('GET', '/recon', 'recon');
$router->add('GET', '/vulnerabilities', 'vulnerabilities');
$router->add('GET', '/workflows', 'workflows');
$router->add('GET', '/findings', 'findings');
$router->add('GET', '/reports', 'reports');
$router->add('GET', '/login', 'auth:login');
$router->add('GET', '/register', 'auth:register');
$router->add('GET', '/pending-approval', 'auth:pending-approval');

// Admin (dummy Phase 1; guard superadmin server-side menyusul Phase 2)
$router->add('GET', '/admin/approvals', 'admin/approvals');
$router->add('GET', '/admin/tool-registry', 'admin/tool-registry');

// API placeholder (data nyata Phase 3+)
$router->add('GET', '/api/stream', 'json:stream');
$router->add('GET', '/api/workflows', 'json:workflows');
$router->add('GET', '/api/findings', 'json:findings');

$router->add('GET', '/health', 'health');

$router->dispatch(
    method: $_SERVER['REQUEST_METHOD'],
    path: parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/'
);
