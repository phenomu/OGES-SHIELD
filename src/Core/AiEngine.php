<?php
/**
 * AiEngine — satu sumber data status engine AI untuk semua view.
 *
 * Sebelumnya tiap view (topbar, dashboard, settings) menulis nama model
 * hardcoded ('glm-5.3'), sehingga tidak pernah ikut berubah ketika
 * AI_MODEL_PRIMARY di orchestrator/.env diganti.
 *
 * Sumber kebenaran: GET {ORCHESTRATOR_URL}/health → field `model` dan
 * `model_fallback` (dibaca orchestrator dari .env-nya sendiri).
 * Kalau orchestrator tidak bisa dihubungi → tampil "—" dan status offline,
 * BUKAN nama model karangan.
 *
 * Hasil di-cache singkat (TTL) karena dipanggil di setiap render halaman
 * (topbar selalu tampil).
 */

declare(strict_types=1);

final class AiEngine
{
    /** Detik cache hasil health check — cukup pendek agar perubahan .env cepat terlihat. */
    private const TTL = 15;
    /** Timeout koneksi ke orchestrator (detik) — jangan menahan render halaman. */
    private const TIMEOUT = 2;
    private const PLACEHOLDER = '—';

    /** @var array<string, mixed>|null */
    private static ?array $cache = null;
    private static int $cacheAt = 0;

    /**
     * Status engine ternormalisasi.
     *
     * @return array{
     *   online: bool,
     *   model: string,
     *   model_fallback: string,
     *   version: string,
     *   tools_loaded: int,
     *   ai_configured: bool,
     *   host: string
     * }
     */
    public static function status(): array
    {
        $now = time();
        if (self::$cache !== null && ($now - self::$cacheAt) < self::TTL) {
            return self::$cache;
        }

        $url = rtrim((string) Env::get('ORCHESTRATOR_URL', 'http://127.0.0.1:8100'), '/');
        $health = self::fetchHealth($url);

        $host = (string) (parse_url($url, PHP_URL_HOST) ?: '127.0.0.1');
        $port = (string) (parse_url($url, PHP_URL_PORT) ?: '8100');

        self::$cache = [
            'online'         => $health !== null && ($health['status'] ?? '') === 'ok',
            'model'          => self::pickModel($health, 'model'),
            'model_fallback' => self::pickModel($health, 'model_fallback'),
            'version'        => (string) ($health['version'] ?? ''),
            'tools_loaded'   => (int) ($health['tools_loaded'] ?? 0),
            'ai_configured'  => (bool) ($health['ai_configured'] ?? false),
            'host'           => $host . ':' . $port,
        ];
        self::$cacheAt = $now;

        return self::$cache;
    }

    /** Nama model untuk ditampilkan (sudah aman untuk HTML via e()). */
    public static function model(): string
    {
        return (string) self::status()['model'];
    }

    /** Paksa ambil ulang pada pemanggilan berikutnya (mis. setelah simpan setting). */
    public static function flush(): void
    {
        self::$cache = null;
        self::$cacheAt = 0;
    }

    /** @return array<string, mixed>|null */
    private static function fetchHealth(string $url): ?array
    {
        $target = $url . '/health';

        // curl kalau tersedia (lebih cepat + timeout ketat), fallback ke
        // file_get_contents supaya tetap jalan tanpa ekstensi curl.
        if (function_exists('curl_init')) {
            $ch = @curl_init($target);
            if ($ch !== false) {
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => self::TIMEOUT,
                    CURLOPT_CONNECTTIMEOUT => self::TIMEOUT,
                ]);
                $raw  = @curl_exec($ch);
                $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if (is_string($raw) && $code === 200) {
                    $decoded = json_decode($raw, true);
                    return is_array($decoded) ? $decoded : null;
                }
                return null;
            }
        }

        $ctx = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'timeout'       => self::TIMEOUT,
                'ignore_errors' => true,
                'header'        => "Accept: application/json\r\n",
            ],
        ]);
        $raw = @file_get_contents($target, false, $ctx);
        if (!is_string($raw)) {
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Ambil nama model dari payload health. Kalau orchestrator belum mengirim
     * field tersebut (versi lama), JANGAN karang nama model — tampilkan
     * placeholder supaya jelas bahwa datanya tidak tersedia.
     *
     * @param array<string, mixed>|null $health
     */
    private static function pickModel(?array $health, string $key): string
    {
        $v = $health[$key] ?? null;
        if (!is_string($v) || trim($v) === '') {
            return self::PLACEHOLDER;
        }
        return trim($v);
    }
}
