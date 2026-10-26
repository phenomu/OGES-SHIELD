<?php
/**
 * Router sederhana (front-controller pattern).
 * Route = [method, path, handler]; handler = nama view template di src/Views/
 * atau "json:<endpoint>" untuk endpoint JSON.
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

        // Endpoint JSON (API internal untuk JS)
        if (str_starts_with($handler, 'json:')) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'ok', 'endpoint' => substr($handler, 5)]);
            return;
        }

        View::render($handler);
    }
}
