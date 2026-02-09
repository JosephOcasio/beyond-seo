<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use DateTime;

/**
 * SchedulerService
 *
 * Manages scheduled tasks and background jobs via WordPress cron.
 *
 * @since 1.2.0
 */
class SchedulerService
{
    /**
     * Scheduled tasks key
     */
    private const TASKS_KEY = 'beyond_seo_scheduled_tasks';

    /**
     * Task execution history key
     */
    private const HISTORY_KEY = 'beyond_seo_task_history';

    /**
     * Available task types
     */
    private const TASK_TYPES = [
        'sitemap_regenerate' => 'Regenerate XML Sitemap',
        'cache_clear' => 'Clear Cache',
        'audit_log_cleanup' => 'Cleanup Audit Logs',
        'backup_create' => 'Create Backup',
        'analytics_purge' => 'Purge Analytics Data',
        'health_check' => 'Run Health Check',
    ];

    /**
     * Register a scheduled task
     *
     * @param string $task_type Task type
     * @param string $schedule Recurrence (hourly, daily, weekly, monthly)
     */
    public static function schedule(string $task_type, string $schedule = 'daily'): bool
    {
        if (!isset(self::TASK_TYPES[$task_type])) {
            return false;
        }

        $task = [
            'id' => md5($task_type . time()),
            'type' => $task_type,
            'schedule' => $schedule,
            'enabled' => true,
            'created_at' => (new DateTime())->format('Y-m-d H:i:s'),
            'last_run' => null,
            'next_run' => self::calculateNextRun($schedule),
        ];

        $tasks = self::getTasks();
        $tasks[] = $task;

        update_option(self::TASKS_KEY, $tasks);

        // Register with WordPress cron
        self::registerCronJob($task);

        return true;
    }

    /**
     * Execute all due tasks
     */
    public static function executeScheduledTasks(): void
    {
        $tasks = self::getTasks();
        $now = (new DateTime())->format('Y-m-d H:i:s');

        foreach ($tasks as &$task) {
            if (!$task['enabled']) {
                continue;
            }

            if ($task['next_run'] && strtotime($task['next_run']) <= strtotime($now)) {
                self::executeTask($task);

                $task['last_run'] = $now;
                $task['next_run'] = self::calculateNextRun($task['schedule']);
            }
        }

        update_option(self::TASKS_KEY, $tasks);
    }

    /**
     * Execute a single task
     *
     * @return bool Success status
     */
    private static function executeTask(array $task): bool
    {
        $start_time = microtime(true);

        try {
            match ($task['type']) {
                'sitemap_regenerate' => self::executeSitemapRegenerate(),
                'cache_clear' => self::executeCacheClear(),
                'audit_log_cleanup' => self::executeAuditLogCleanup(),
                'backup_create' => self::executeBackupCreate(),
                'analytics_purge' => self::executeAnalyticsPurge(),
                'health_check' => self::executeHealthCheck(),
                default => false,
            };

            $duration = microtime(true) - $start_time;

            self::recordTaskExecution($task, true, null, $duration);

            return true;
        } catch (\Exception $e) {
            self::recordTaskExecution($task, false, $e->getMessage());
            return false;
        }
    }

    /**
     * Record task execution in history
     */
    private static function recordTaskExecution(
        array $task,
        bool $success,
        ?string $error = null,
        float $duration = 0
    ): void {
        $history = get_option(self::HISTORY_KEY, []);

        $record = [
            'task_id' => $task['id'],
            'task_type' => $task['type'],
            'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
            'success' => $success,
            'error' => $error,
            'duration_ms' => $duration * 1000,
        ];

        $history[] = $record;

        // Keep only last 1000 records
        if (count($history) > 1000) {
            $history = array_slice($history, -1000);
        }

        update_option(self::HISTORY_KEY, $history);
    }

    /**
     * Get all scheduled tasks
     *
     * @return array<array<string, mixed>>
     */
    public static function getTasks(): array
    {
        $tasks = get_option(self::TASKS_KEY);
        return is_array($tasks) ? $tasks : [];
    }

    /**
     * Get task execution history
     *
     * @param string|null $task_type Filter by type
     * @param int $limit Result limit
     * @return array<array<string, mixed>>
     */
    public static function getHistory(?string $task_type = null, int $limit = 100): array
    {
        $history = get_option(self::HISTORY_KEY, []);

        if ($task_type) {
            $history = array_filter(
                $history,
                fn($h) => $h['task_type'] === $task_type
            );
        }

        return array_slice(array_reverse($history), 0, $limit);
    }

    /**
     * Disable a task
     */
    public static function disable(string $task_id): bool
    {
        $tasks = self::getTasks();

        foreach ($tasks as &$task) {
            if ($task['id'] === $task_id) {
                $task['enabled'] = false;
                update_option(self::TASKS_KEY, $tasks);
                return true;
            }
        }

        return false;
    }

    /**
     * Enable a task
     */
    public static function enable(string $task_id): bool
    {
        $tasks = self::getTasks();

        foreach ($tasks as &$task) {
            if ($task['id'] === $task_id) {
                $task['enabled'] = true;
                $task['next_run'] = self::calculateNextRun($task['schedule']);
                update_option(self::TASKS_KEY, $tasks);
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate next run time
     */
    private static function calculateNextRun(string $schedule): string
    {
        $next = match ($schedule) {
            'hourly' => new DateTime('+1 hour'),
            'daily' => new DateTime('+1 day'),
            'weekly' => new DateTime('+1 week'),
            'monthly' => new DateTime('+1 month'),
            default => new DateTime('+1 day'),
        };

        return $next->format('Y-m-d H:i:s');
    }

    /**
     * Regenerate sitemap
     */
    private static function executeSitemapRegenerate(): void
    {
        do_action('beyond_seo_regenerate_sitemap');
    }

    /**
     * Clear cache
     */
    private static function executeCacheClear(): void
    {
        wp_cache_flush();
        delete_transient('beyond_seo_cache_*');
    }

    /**
     * Cleanup audit logs
     */
    private static function executeAuditLogCleanup(): void
    {
        AuditLogService::purgeOldLogs(180); // Keep 6 months
    }

    /**
     * Create automatic backup
     */
    private static function executeBackupCreate(): void
    {
        BackupService::create('Automatic backup ' . date('Y-m-d H:i'));
    }

    /**
     * Purge old analytics
     */
    private static function executeAnalyticsPurge(): void
    {
        AnalyticsService::purgeOldData(90); // Keep 3 months
    }

    /**
     * Run health check
     */
    private static function executeHealthCheck(): void
    {
        $validator = new EnvironmentSecurityValidator();
        $validator->validate();

        if (!empty($validator->getErrors())) {
            do_action('beyond_seo_health_check_failed', $validator->getErrors());
        }
    }

    /**
     * Register cron job with WordPress
     */
    private static function registerCronJob(array $task): void
    {
        $hook = 'beyond_seo_scheduled_' . $task['type'];

        if (!wp_next_scheduled($hook)) {
            wp_schedule_event(time(), $task['schedule'], $hook);
        }
    }

    /**
     * Get available task types
     *
     * @return array<string, string>
     */
    public static function getAvailableTypes(): array
    {
        return self::TASK_TYPES;
    }
}
