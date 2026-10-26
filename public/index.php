<?php
/**
 * OGES-SHIELD — front controller.
 * Semua request HTTP lewat sini (DocumentRoot = public/).
 * Router sederhana buatan sendiri, tanpa framework.
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/src/Core/Router.php';
require BASE_PATH . '/src/Core/View.php';

$router = new Router();

// --- Route map (Phase 0: hanya home placeholder; Phase 1 isi panel lain) ---
$router->add('GET', '/', 'home');
$router->add('GET', '/health', 'health');

$router->dispatch(
    method: $_SERVER['REQUEST_METHOD'],
    path: parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/'
);
