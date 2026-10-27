<?php
/**
 * OGES-SHIELD — front controller.
 * Semua request HTTP lewat sini (DocumentRoot = public/).
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

// Autoload sederhana per konvensi folder (tanpa composer).
spl_autoload_register(static function (string $class): void {
    foreach (['Core', 'Models', 'Controllers', 'Mock'] as $dir) {
        $file = BASE_PATH . '/src/' . $dir . '/' . $class . '.php';
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});
require BASE_PATH . '/src/Core/functions.php';

Env::load(BASE_PATH . '/.env');
Session::start();

// --- Security headers (XSS / clickjacking / sniffing) ---
// CSP dengan nonce per-request: inline <script> di view wajib pakai nonce
// (View::page injects CSP_NONCE ke semua tag <script>).
define('CSP_NONCE', bin2hex(random_bytes(16)));
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'nonce-" . CSP_NONCE . "'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");

$router = new Router();

// --- Route map ---

// Publik (tanpa login)
$router->add('GET',  '/',                 'ctrl:AuthController@showLogin');
$router->add('GET',  '/login',            'ctrl:AuthController@showLogin');
$router->add('POST', '/login',            'ctrl:AuthController@login');
$router->add('GET',  '/register',         'ctrl:AuthController@showRegister');
$router->add('POST', '/register',         'ctrl:AuthController@register');
$router->add('GET',  '/pending-approval', 'auth:pending-approval');
$router->add('GET',  '/health',           'health');

// Aplikasi (wajib login — guard di bawah)
$router->add('GET',  '/dashboard',        'dashboard');
$router->add('GET',  '/assets',           'assets');
$router->add('GET',  '/recon',            'recon');
$router->add('GET',  '/vulnerabilities',  'vulnerabilities');
$router->add('GET',  '/workflows',        'workflows');
$router->add('GET',  '/findings',         'ctrl:FindingController@show');
$router->add('GET',  '/reports',          'ctrl:ReportController@index');
$router->add('GET',  '/exploitation',     'exploitation');
$router->add('GET',  '/persistence',      'persistence');
$router->add('GET',  '/audit',            'audit');
$router->add('GET',  '/audit/export',     'ctrl:AuditController@export');
$router->add('GET',  '/settings',         'settings');
$router->add('GET',  '/docs',             'docs');
$router->add('POST', '/logout',           'ctrl:AuthController@logout');
$router->add('GET',  '/run',              'ctrl:RunController@show');
$router->add('POST', '/runs/create',      'ctrl:RunController@create');
$router->add('POST', '/runs/stop',        'ctrl:RunController@stop');
$router->add('POST', '/runs/resume',      'ctrl:RunController@resume');
$router->add('POST', '/runs/delete',      'ctrl:RunController@delete');
$router->add('POST', '/engagement/select', 'ctrl:RunController@selectEngagement');
$router->add('POST', '/engagement/reset',  'ctrl:RunController@resetEngagement');
$router->add('POST', '/templates/create',  'ctrl:TemplateController@create');
$router->add('POST', '/templates/delete',  'ctrl:TemplateController@delete');
$router->add('POST', '/targets/create',   'ctrl:TargetController@create');
$router->add('POST', '/targets/scope',    'ctrl:TargetController@toggleScope');
$router->add('GET',  '/report/download',  'ctrl:ReportController@download');
$router->add('POST', '/reports/regenerate', 'ctrl:ReportController@regenerate');

// Admin (wajib superadmin — guard di bawah + di controller)
$router->add('GET',  '/admin/approvals',    'ctrl:AdminController@approvals');
$router->add('GET',  '/admin/tool-registry', 'admin/tool-registry');
$router->add('POST', '/admin/tool-registry/save', 'ctrl:ToolRegistryController@save');
$router->add('POST', '/admin/approve',      'ctrl:AdminController@approve');
$router->add('POST', '/admin/reject',       'ctrl:AdminController@reject');

// API (data nyata Phase 3+; sementara placeholder JSON)
$router->add('GET',  '/api/stream',     'json:stream');
$router->add('GET',  '/api/workflows',  'json:workflows');
$router->add('GET',  '/api/findings',   'json:findings');

// --- Guard server-side (Bagian 7: role check di server, bukan cuma UI) ---
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$publicPaths = ['/', '/login', '/register', '/pending-approval', '/health'];

if (!in_array($path, $publicPaths, true)) {
    Auth::requireLogin();
    if (str_starts_with($path, '/admin/') && !Auth::isSuperadmin()) {
        http_response_code(403);
        View::page('403');
        exit;
    }
}

$router->dispatch(
    method: $_SERVER['REQUEST_METHOD'],
    path:   $path,
);
