<?php
/**
 * View — render template dari src/Views/ dengan layout minimal.
 */

declare(strict_types=1);

final class View
{
    public static function render(string $template, array $data = []): void
    {
        $file = BASE_PATH . '/src/Views/' . $template . '.php';
        if (!is_file($file)) {
            http_response_code(500);
            echo 'View not found: ' . htmlspecialchars($template);
            return;
        }
        extract($data, EXTR_SKIP);
        require $file;
    }
}
