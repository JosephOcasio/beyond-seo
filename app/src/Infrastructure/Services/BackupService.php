<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use DateTime;

/**
 * BackupService
 *
 * Handles export and import of plugin configuration backups.
 *
 * @since 1.2.0
 */
class BackupService
{
    /**
     * Backup metadata key
     */
    private const BACKUP_KEY = 'beyond_seo_backups';

    /**
     * List of settings to backup
     */
    private const SETTINGS_TO_BACKUP = [
        'beyond_seo_enabled',
        'beyond_seo_debug_mode',
        'beyond_seo_cache_enabled',
        'beyond_seo_cache_ttl',
        'beyond_seo_enable_xml_sitemap',
        'beyond_seo_enable_breadcrumbs',
        'beyond_seo_enable_schema_markup',
        'beyond_seo_default_separator',
        'beyond_seo_api_enabled',
        'beyond_seo_api_rate_limit',
    ];

    /**
     * Create a backup of current settings
     *
     * @param string|null $name Backup name
     * @return array<string, mixed> Backup metadata
     */
    public static function create(?string $name = null): array
    {
        $settings = [];

        foreach (self::SETTINGS_TO_BACKUP as $key) {
            $settings[$key] = get_option($key);
        }

        $backup = [
            'id' => md5(uniqid('', true)),
            'name' => $name ?? 'Backup ' . date('Y-m-d H:i:s'),
            'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
            'created_by' => wp_get_current_user()->user_email,
            'settings' => $settings,
            'version' => RANKINGCOACH_VERSION ?? '1.2.0',
            'php_version' => PHP_VERSION,
            'wordpress_version' => get_bloginfo('version'),
        ];

        $backups = self::getBackups();
        $backups[] = $backup;

        // Keep only last 50 backups
        if (count($backups) > 50) {
            array_shift($backups);
        }

        update_option(self::BACKUP_KEY, $backups);

        return $backup;
    }

    /**
     * Restore settings from backup
     *
     * @param string $backup_id Backup ID to restore
     * @return bool Success status
     */
    public static function restore(string $backup_id): bool
    {
        $backups = self::getBackups();
        $backup = null;

        foreach ($backups as $b) {
            if ($b['id'] === $backup_id) {
                $backup = $b;
                break;
            }
        }

        if (!$backup) {
            return false;
        }

        // Restore each setting
        foreach ($backup['settings'] as $key => $value) {
            update_option($key, $value);
        }

        // Log restoration
        AuditLogService::log(
            'backup_restored',
            'configuration',
            'previous',
            $backup['name'],
            'Restored from backup: ' . $backup['id']
        );

        return true;
    }

    /**
     * Export backup as JSON file
     *
     * @param string $backup_id Backup ID to export
     * @return string|null JSON content or null if backup not found
     */
    public static function exportAsJson(string $backup_id): ?string
    {
        $backups = self::getBackups();

        foreach ($backups as $backup) {
            if ($backup['id'] === $backup_id) {
                return json_encode($backup, JSON_PRETTY_PRINT);
            }
        }

        return null;
    }

    /**
     * Import backup from JSON
     *
     * @param string $json_data JSON backup data
     * @return array<string, mixed>|null Imported backup metadata or null on error
     */
    public static function importFromJson(string $json_data): ?array
    {
        $backup = json_decode($json_data, true);

        if (!is_array($backup) || !isset($backup['settings'])) {
            return null;
        }

        // Validate backup format
        if (!isset($backup['name'], $backup['timestamp'], $backup['created_by'])) {
            return null;
        }

        // Generate new ID
        $backup['id'] = md5(uniqid('', true));

        $backups = self::getBackups();
        $backups[] = $backup;

        if (count($backups) > 50) {
            array_shift($backups);
        }

        update_option(self::BACKUP_KEY, $backups);

        return $backup;
    }

    /**
     * Get all backups
     *
     * @return array<array<string, mixed>>
     */
    public static function getBackups(): array
    {
        $backups = get_option(self::BACKUP_KEY);
        return is_array($backups) ? $backups : [];
    }

    /**
     * Get backup by ID
     *
     * @return array<string, mixed>|null
     */
    public static function getBackup(string $backup_id): ?array
    {
        $backups = self::getBackups();

        foreach ($backups as $backup) {
            if ($backup['id'] === $backup_id) {
                return $backup;
            }
        }

        return null;
    }

    /**
     * Delete backup
     */
    public static function delete(string $backup_id): bool
    {
        $backups = self::getBackups();
        $original_count = count($backups);

        $backups = array_filter(
            $backups,
            fn($b) => $b['id'] !== $backup_id
        );

        if (count($backups) < $original_count) {
            update_option(self::BACKUP_KEY, $backups);
            return true;
        }

        return false;
    }

    /**
     * Get difference between two backups
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public static function compare(string $backup_id_1, string $backup_id_2): array
    {
        $backup1 = self::getBackup($backup_id_1);
        $backup2 = self::getBackup($backup_id_2);

        if (!$backup1 || !$backup2) {
            return [];
        }

        $differences = [];
        $all_keys = array_unique(
            array_merge(
                array_keys($backup1['settings']),
                array_keys($backup2['settings'])
            )
        );

        foreach ($all_keys as $key) {
            $val1 = $backup1['settings'][$key] ?? null;
            $val2 = $backup2['settings'][$key] ?? null;

            if ($val1 !== $val2) {
                $differences[$key] = [
                    'old' => $val1,
                    'new' => $val2,
                ];
            }
        }

        return $differences;
    }

    /**
     * Schedule automatic backups
     */
    public static function scheduleAutoBackup(): void
    {
        $last_backup = get_option('beyond_seo_last_auto_backup', 0);
        $backup_interval = 86400; // 24 hours

        if ((time() - $last_backup) > $backup_interval) {
            self::create('Auto-backup ' . date('Y-m-d H:i'));
            update_option('beyond_seo_last_auto_backup', time());
        }
    }

    /**
     * Cleanup old backups beyond retention policy
     *
     * @param int $keep Number of backups to keep
     * @return int Number deleted
     */
    public static function cleanup(int $keep = 20): int
    {
        $backups = self::getBackups();
        $original_count = count($backups);

        if ($original_count > $keep) {
            $backups = array_slice($backups, -$keep);
            update_option(self::BACKUP_KEY, $backups);
        }

        return $original_count - count($backups);
    }
}
