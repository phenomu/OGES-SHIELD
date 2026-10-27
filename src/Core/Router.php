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

    /**
     * Normalisasi path request jadi daftar kandidat yang dicoba berurutan.
     *
     * Kenapa perlu: public/ punya direktori nyata yang namanya sama dengan route
     * (`assets/`, `admin/`, `api/`). Saat browser membuka /assets, Apache
     * (mod_dir) otomatis membalas 301 ke /assets/ karena itu direktori. Request
     * /assets/ lalu sampai ke front controller dengan trailing slash, sedangkan
     * route terdaftar sebagai /assets → tanpa normalisasi hasilnya 404.
     * Berlaku untuk semua route, bukan cuma /assets.
     *
     * Aturan:
     *   /assets/        ≡ /assets
     *   /dashboard.php  ≡ /dashboard
     *   /assets/.php    → /assets
     *   /               tetap "/" (root tidak pernah dipangkas jadi "")
     *
     * @return list<string>
     */
    private function variants(string $path): array
    {
        $out = [];
        $push = static function (string $p) use (&$out): void {
            if ($p === '') {
                $p = '/';
            }
            if (!in_array($p, $out, true)) {
                $out[] = $p;
            }
        };

        /** Buang trailing slash, jaga root tetap "/". */
        $stripSlash = static function (string $p): string {
            if ($p === '/' || $p === '') {
                return '/';
            }
            $p = rtrim($p, '/');
            return $p === '' ? '/' : $p;
        };

        /** Buang ekstensi .php. */
        $stripExt = static function (string $p): string {
            $p = preg_replace('/\.php$/', '', $p) ?? $p;
            return $p === '' ? '/' : $p;
        };

        $push($path);

        // Bentuk kanonik: tanpa trailing slash, tanpa .php, tanpa trailing slash
        // (dibersihkan dua kali karena "/x/.php" menyisakan "/x/").
        $canonical = $stripSlash($stripExt($stripSlash($path)));
        $push($canonical);
        $push($stripSlash($path));
        $push($stripSlash($stripExt($path)));

        // Alias ekstensi .php untuk bentuk kanonik dan ber-trailing-slash —
        // hanya kalau belum berakhiran .php dan bukan root (hindari "/.php"
        // atau "/x.php.php" yang cuma memboroskan lookup).
        $withSlash = $stripSlash($path);
        foreach ([$canonical, $withSlash] as $base) {
            if ($base !== '/' && !str_ends_with($base, '.php')) {
                $push($base . '.php');
            }
        }

        return $out;
    }

    public function dispatch(string $method, string $path): void
    {
        $handler = null;
        foreach ($this->variants($path) as $v) {
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
