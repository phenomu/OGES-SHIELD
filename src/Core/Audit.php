<?php
/**
 * Audit — tulis baris audit_logs (guardrail 9.5: permanen, tidak bisa dihapus dari UI).
 * Audit TIDAK boleh melempar exception ke alur utama.
 */

declare(strict_types=1);

final class Audit
{
    public static function log(
        ?int $userId,
        string $action,
        array $detail = [],
        ?string $runId = null,
        ?string $toolId = null,
        ?string $targetValue = null
    ): void {
        try {
            Database::run(
                'INSERT INTO audit_logs (user_id, run_id, action, target_value, tool_id, detail)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$userId, $runId, $action, $targetValue, $toolId,
                 json_encode($detail, JSON_UNESCAPED_UNICODE) ?: null]
            );
        } catch (Throwable $e) {
            // audit gagal tidak boleh memutus alur; error hanya ke error_log
            error_log('audit_log_failed: ' . $e->getMessage());
        }
    }
}
