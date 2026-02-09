<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use DateTime;

/**
 * AuditLogService
 *
 * Records all plugin configuration changes for compliance and debugging.
 *
 * @since 1.2.0
 */
class AuditLogService
{
    /**
     * Audit log table option key
     */
    private const AUDIT_LOG_KEY = 'beyond_seo_audit_log';

    /**
     * Record a configuration change
     *
     * @param string $action Action performed (e.g., 'update', 'delete', 'export')
     * @param string $setting Setting key changed
     * @param mixed $old_value Previous value
     * @param mixed $new_value New value
     * @param string|null $notes Additional notes
     */
    public static function log(
        string $action,
        string $setting,
        mixed $old_value,
        mixed $new_value,
        ?string $notes = null
    ): void {
        $log = [
            'id' => md5(uniqid('', true)),
            'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
            'user_id' => get_current_user_id(),
            'user_email' => wp_get_current_user()->user_email,
            'action' => sanitize_text_field($action),
            'setting' => sanitize_text_field($setting),
            'old_value' => self::sanitizeForLog($old_value),
            'new_value' => self::sanitizeForLog($new_value),
            'ip_address' => self::getClientIp(),
            'notes' => $notes ? sanitize_text_field($notes) : null,
        ];

        $logs = self::getLogs();
        $logs[] = $log;

        // Keep only last 10,000 audit logs
        if (count($logs) > 10000) {
            $logs = array_slice($logs, -10000);
        }

        update_option(self::AUDIT_LOG_KEY, $logs);
    }

    /**
     * Get audit logs with optional filtering
     *
     * @param array<string, mixed> $filters Filter criteria
     * @param int $limit Result limit
     * @return array<array<string, mixed>>
     */
    public static function getLogs(array $filters = [], int $limit = 100): array
    {
        $logs = self::getAllLogs();

        // Filter by action
        if (!empty($filters['action'])) {
            $logs = array_filter(
                $logs,
                fn($log) => $log['action'] === $filters['action']
            );
        }

        // Filter by setting
        if (!empty($filters['setting'])) {
            $logs = array_filter(
                $logs,
                fn($log) => $log['setting'] === $filters['setting']
            );
        }

        // Filter by user
        if (!empty($filters['user_id'])) {
            $logs = array_filter(
                $logs,
                fn($log) => $log['user_id'] === (int) $filters['user_id']
            );
        }

        // Filter by date range
        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $from = strtotime($filters['date_from']);
            $to = strtotime($filters['date_to']);

            $logs = array_filter(
                $logs,
                fn($log) => strtotime($log['timestamp']) >= $from && strtotime($log['timestamp']) <= $to
            );
        }

        // Return latest first
        $logs = array_reverse($logs);

        return array_slice($logs, 0, $limit);
    }

    /**
     * Get audit log statistics
     *
     * @return array<string, mixed>
     */
    public static function getStatistics(): array
    {
        $logs = self::getAllLogs();

        $stats = [
            'total_changes' => count($logs),
            'changes_today' => 0,
            'changes_this_week' => 0,
            'changes_this_month' => 0,
            'by_action' => [],
            'by_user' => [],
            'most_changed_setting' => null,
        ];

        $today = date('Y-m-d');
        $week_ago = date('Y-m-d', strtotime('-7 days'));
        $month_ago = date('Y-m-d', strtotime('-30 days'));
        $setting_changes = [];

        foreach ($logs as $log) {
            $log_date = substr($log['timestamp'], 0, 10);

            if ($log_date === $today) {
                $stats['changes_today']++;
            }
            if ($log_date >= $week_ago) {
                $stats['changes_this_week']++;
            }
            if ($log_date >= $month_ago) {
                $stats['changes_this_month']++;
            }

            // Count by action
            $action = $log['action'];
            $stats['by_action'][$action] = ($stats['by_action'][$action] ?? 0) + 1;

            // Count by user
            $user_email = $log['user_email'];
            $stats['by_user'][$user_email] = ($stats['by_user'][$user_email] ?? 0) + 1;

            // Count setting changes
            $setting = $log['setting'];
            $setting_changes[$setting] = ($setting_changes[$setting] ?? 0) + 1;
        }

        // Get most changed setting
        if (!empty($setting_changes)) {
            arsort($setting_changes);
            $stats['most_changed_setting'] = key($setting_changes);
        }

        return $stats;
    }

    /**
     * Export audit logs as CSV
     *
     * @return string CSV formatted content
     */
    public static function exportAsCSV(): string
    {
        $logs = self::getAllLogs();
        $csv = "Timestamp,User,Action,Setting,Old Value,New Value,IP Address,Notes\n";

        foreach ($logs as $log) {
            $csv .= sprintf(
                "\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                $log['timestamp'],
                $log['user_email'],
                $log['action'],
                $log['setting'],
                $log['old_value'],
                $log['new_value'],
                $log['ip_address'],
                $log['notes'] ?? ''
            );
        }

        return $csv;
    }

    /**
     * Clear old audit logs (retention policy)
     *
     * @param int $days Days to retain
     * @return int Number of deleted logs
     */
    public static function purgeOldLogs(int $days = 180): int
    {
        $logs = self::getAllLogs();
        $cutoff = date('Y-m-d', strtotime("-{$days} days"));
        $original_count = count($logs);

        $logs = array_filter(
            $logs,
            fn($log) => substr($log['timestamp'], 0, 10) >= $cutoff
        );

        update_option(self::AUDIT_LOG_KEY, $logs);

        return $original_count - count($logs);
    }

    /**
     * Sanitize value for storage in log
     */
    private static function sanitizeForLog(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return json_encode($value) ?: '[]';
        }

        $str = (string) $value;

        // Redact sensitive values
        if (stripos($str, 'password') !== false || strlen($str) > 100) {
            return '[REDACTED]';
        }

        return substr($str, 0, 200);
    }

    /**
     * Get client IP address
     */
    private static function getClientIp(): string
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return sanitize_text_field($_SERVER['HTTP_CLIENT_IP']);
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return sanitize_text_field(trim($ips[0]));
        }

        return sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    }

    /**
     * Get all audit logs
     *
     * @return array<array<string, mixed>>
     */
    private static function getAllLogs(): array
    {
        $logs = get_option(self::AUDIT_LOG_KEY);
        return is_array($logs) ? $logs : [];
    }
}
