<?php
/**
 * Router sederhana (front-controller pattern).
 * Route = [method, path, handler]; handler = nama view template di src/Views/.
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
        $key = strtoupper($method) . ' ' . $path;
        $handler = $this->routes[$key] ?? null;

        if ($handler === null) {
            http_response_code(404);
            View::render('404');
            return;
        }

        // Health endpoint = JSON, bukan HTML
        if ($handler === 'health') {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'ok', 'app' => 'oges-shield-php']);
            return;
        }

        View::render($handler);
    }
}
