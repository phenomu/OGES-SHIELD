<?php
/**
 * Router sederhana (front-controller pattern).
 * Handler format:
 *   "page:<template>"  — render di dalam app shell (topbar+sidebar)
 *   "auth:<template>"  — render di dalam auth shell (login/register/pending)
 *   "ctrl:Class@method" — panggil static method controller (POST actions, dll)
 *   "json:<name>"      — endpoint JSON
 *   "health"           — health check JSON
 * String polos (tanpa prefix) = page:<template> (default).
 */

declare(strict_types=1);

final class Router
{
    /** @var array<string, string> "METHOD /path" => handler */
    private array $routes = [];

    public function add(string $method, string $path, string $handler): void
    {
        $this->routes[strtoupper($method) . ' ' . $path] = $handler;
    }

    public function dispatch(string $method, string $path): void
    {
        // Normalisasi path dengan atau tanpa .php (dashboard.php ≡ /dashboard)
        $variants = [$path];
        $pathNoExt = preg_replace('/\.php$/', '', $path);
        if ($pathNoExt !== $path) {
            $variants[] = $pathNoExt;
            $variants[] = $pathNoExt . '.php';
        }

        $handler = null;
        foreach ($variants as $v) {
            $handler = $this->routes[strtoupper($method) . ' ' . $v] ?? null;
            if ($handler !== null) {
                break;
            }
        }

        if ($handler === null) {
            http_response_code(404);
            View::render('404');
            return;
        }

        // Aksi controller (POST form, dll)
        if (str_starts_with($handler, 'ctrl:')) {
            [$class, $method] = explode('@', substr($handler, 5), 2);
            $class::$method();
            return;
        }

        // Endpoint JSON (API internal untuk JS) + health
        if (str_starts_with($handler, 'json:')) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'ok', 'endpoint' => substr($handler, 5)]);
            return;
        }
        if ($handler === 'health') {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'ok', 'app' => 'oges-shield-php']);
            return;
        }

        if (str_starts_with($handler, 'auth:')) {
            View::auth(substr($handler, 5));
            return;
        }

        // default / "page:" → app shell
        View::page(str_starts_with($handler, 'page:') ? substr($handler, 5) : $handler);
    }
}
