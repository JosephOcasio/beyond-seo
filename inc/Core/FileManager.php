<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core;

if ( !defined('ABSPATH') ) {
    exit;
}

use FilesystemIterator;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\Settings\SettingsManager;
use RankingCoach\Inc\Traits\SingletonManager;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

/**
 * Class FileManager
 *
 * Provides utility functions for packaging log files.
 */
class FileManager
{
    use SingletonManager;
    use RcLoggerTrait;

    /**
     * Register internal hooks.
     */
    public function init(): void
    {
        add_action('rc_delete_temp_zip_file', [$this, 'deleteTempZipFile']);
    }

    /**
     * Generate WordPress instance metadata for debugging purposes.
     *
     * @return array Comprehensive system and WordPress information
     */
    public function generateInstanceMetadata(): array
    {
        global $wpdb;
        
        $activePlugins = get_option('active_plugins', []);
        $pluginData = [];
        
        foreach ($activePlugins as $plugin) {
            if (function_exists('get_plugin_data')) {
                $pluginInfo = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin, false, false);
                $pluginData[] = [
                    'name' => $pluginInfo['Name'] ?? basename($plugin),
                    'version' => $pluginInfo['Version'] ?? 'unknown',
                    'file' => $plugin
                ];
            }
        }

        $theme = wp_get_theme();
        $uploads = wp_upload_dir();
        
        return [
            'generated_at' => current_time('Y-m-d H:i:s T'),
            'wordpress' => [
                'version' => get_bloginfo('version'),
                'site_url' => get_site_url(),
                'home_url' => get_home_url(),
                'admin_email' => get_option('admin_email'),
                'language' => get_locale(),
                'timezone' => get_option('timezone_string') ?: date_default_timezone_get(),
                'multisite' => is_multisite(),
                'debug_mode' => defined('WP_DEBUG') && WP_DEBUG,
                'debug_log' => defined('WP_DEBUG_LOG') && WP_DEBUG_LOG,
            ],
            'theme' => [
                'name' => $theme->get('Name'),
                'version' => $theme->get('Version'),
                'template' => $theme->get_template(),
                'stylesheet' => $theme->get_stylesheet(),
            ],
            'server' => [
                'php_version' => PHP_VERSION,
                'server_software' => isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])) : 'unknown',
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'max_input_vars' => ini_get('max_input_vars'),
            ],
            'database' => [
                'version' => $wpdb->db_version(),
                'charset' => $wpdb->charset,
                'collate' => $wpdb->collate,
                'prefix' => $wpdb->prefix,
            ],
            'rankingcoach' => [
                'plugin_version' => defined('RANKINGCOACH_VERSION') ? RANKINGCOACH_VERSION : 'unknown',
                'plugin_dir' => RANKINGCOACH_PLUGIN_DIR,
                'plugin_url' => defined('RANKINGCOACH_PLUGIN_URL') ? RANKINGCOACH_PLUGIN_URL : 'unknown',
            ],
            'uploads' => [
                'basedir' => $uploads['basedir'],
                'baseurl' => $uploads['baseurl'],
                'writable' => wp_is_writable($uploads['basedir']),
            ],
            'active_plugins' => $pluginData,
            'constants' => [
                'WP_CONTENT_DIR' => WP_CONTENT_DIR,
                'WP_PLUGIN_DIR' => WP_PLUGIN_DIR,
                'ABSPATH' => ABSPATH,
                'WP_DEBUG' => defined('WP_DEBUG') && WP_DEBUG,
                'WP_DEBUG_LOG' => defined('WP_DEBUG_LOG') && WP_DEBUG_LOG,
                'WP_DEBUG_DISPLAY' => defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY,
            ]
        ];
    }

    /**
     * Add log files from specified directories to ZIP archive.
     *
     * @param ZipArchive $zip The ZIP archive instance
     * @param array $logDirs Array of directory => prefix mappings
     * @return int Number of files added
     */
    private function addLogFilesToZip(ZipArchive $zip, array $logDirs): int
    {
        $fileCount = 0;
        
        foreach ($logDirs as $dir => $prefix) {
            if (!is_dir($dir)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->isReadable()) {
                    $relativePath = str_replace($dir . DIRECTORY_SEPARATOR, '', $file->getPathname());
                    $zip->addFile($file->getPathname(), $prefix . $relativePath);
                    $fileCount++;
                }
            }
        }
        
        return $fileCount;
    }

    /**
     * Create a ZIP archive containing plugin log files and the WordPress debug log.
     *
     * This method creates a ZIP archive containing all log files
     * @return string|null Full path to the created archive or null on failure.
     */
    public function createLogsArchive(): ?string
    {
        // Check if user has enabled log sharing
        $settingsManager = SettingsManager::instance();
        $enableLogSharing = $settingsManager->get_option('enable_user_action_and_event_logs_sharing', false);

        if (!$enableLogSharing) {
            //$this->log('Log archive creation denied: Log sharing is disabled by user settings', 'WARNING');
            return null;
        }

        add_action('rc_delete_temp_zip_file', [$this, 'deleteTempZipFile']);

        if (!class_exists(ZipArchive::class)) {
            $this->log('ZipArchive class not available', 'ERROR');
            return null;
        }

        $uploads   = wp_upload_dir();
        $zipName   = 'rankingcoach-logs-' . gmdate('Y-m-d-His') . '.zip';
        $zipPath   = trailingslashit($uploads['basedir']) . $zipName;
        $zip       = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            $this->log('Failed to create ZIP archive at ' . $zipPath, 'ERROR');
            return null;
        }

        $fileCount = 0;

        // Add WordPress instance metadata
        $metadataJson = json_encode($this->generateInstanceMetadata(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $zip->addFromString('wp_instance_debug.json', $metadataJson);
        $fileCount++;

        $logDirs = [
            RANKINGCOACH_PLUGIN_DIR . 'app/var/log'    => 'logs/',
            RANKINGCOACH_LOG_DIR => 'rc-logs/',
        ];

        // Add log files using helper method
        $fileCount += $this->addLogFilesToZip($zip, $logDirs);

        // Add WordPress debug log
        $debugLog = WP_CONTENT_DIR . '/debug.log';
        if (file_exists($debugLog) && is_readable($debugLog)) {
            $zip->addFile($debugLog, 'wordpress/debug.log');
            $fileCount++;
        }

        $zip->close();

        if ($fileCount === 0) {
            if (file_exists($zipPath)) {
                wp_delete_file($zipPath);
            }
            return null;
        }

        // Schedule cleanup
        if (!wp_next_scheduled('rc_delete_temp_zip_file', [$zipPath])) {
            wp_schedule_single_event(time() + 3600, 'rc_delete_temp_zip_file', [$zipPath]);
        }

        return $zipPath;
    }

    /**
     * Create and download a ZIP archive of all log files.
     */
    public function downloadLogsZip(): void
    {
        // Check if user has appropriate permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'You do not have permission to download log files.']);
            return;
        }

        // Check if user has enabled log sharing
        $settingsManager = SettingsManager::instance();
        $enableLogSharing = $settingsManager->get_option('enable_user_action_and_event_logs_sharing', false);

        if (!$enableLogSharing) {
            wp_send_json_error([
                'message' => 'Log sharing is disabled. The client has not agreed to share logs for debugging purposes. Please enable log sharing in plugin settings to allow log downloads.',
                'code' => 'logs_sharing_disabled'
            ]);
            return;
        }

        // Check if ZipArchive class exists
        if (!class_exists('ZipArchive')) {
            wp_send_json_error(['message' => 'ZipArchive class is not available on this server. Cannot create ZIP file.']);
            return;
        }

        // Create a temporary file for the ZIP archive
        $zipFilename = 'rankingcoach-logs-' . gmdate('Y-m-d-His') . '.zip';
        $zipPath = wp_upload_dir()['basedir'] . '/' . $zipFilename;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            wp_send_json_error(['message' => 'Failed to create ZIP archive.']);
            return;
        }

        $fileCount = 0;

        // Add WordPress instance metadata
        $metadataJson = json_encode($this->generateInstanceMetadata(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $zip->addFromString('wp_instance_debug.json', $metadataJson);
        $fileCount++;

        // Collect and add all log files using a single source of truth
        $logFiles = $this->getLogsFilesList();
        foreach ($logFiles as $logFile) {
            // Defensive checks, though getLogsFilesList guarantees existence/readability
            if (!empty($logFile['path']) && !empty($logFile['zip_path']) && is_readable($logFile['path'])) {
                $zip->addFile($logFile['path'], $logFile['zip_path']);
                $fileCount++;
            }
        }

        // Close the ZIP file
        $zip->close();

        // Check if any files were added (excluding metadata which is always added)
        if ($fileCount <= 1) {
            // Remove empty ZIP file (only contains metadata)
            if (file_exists($zipPath)) {
                wp_delete_file($zipPath);
            }
            wp_send_json_error(['message' => 'No log files found to download.']);
            return;
        }

        // Create a URL for downloading the ZIP file
        $zipUrl = wp_upload_dir()['baseurl'] . '/' . $zipFilename;

        // Schedule deletion of the ZIP file after 1 hour
        if (!wp_next_scheduled('rc_delete_temp_zip_file', [$zipPath])) {
            wp_schedule_single_event(time() + 3600, 'rc_delete_temp_zip_file', [$zipPath]);
        }

        wp_send_json_success([
            'download_url' => $zipUrl,
            'filename' => $zipFilename,
            'message' => sprintf('Successfully packaged %d log file(s) with system metadata.', $fileCount - 1)
        ]);
    }

    /**
     * Delete a temporary ZIP archive.
     */
    public function deleteTempZipFile(string $zipPath): void
    {
        if (file_exists($zipPath) && wp_is_writable($zipPath)) {
            wp_delete_file($zipPath);
        }
    }

    /**
     * Get a list of log files to include in the downloadable archive.
     *
     * Returns a list of associative arrays containing:
     * - path: absolute filesystem path to the file
     * - zip_path: target path inside the ZIP archive
     *
     * @return array<int, array{path: string, zip_path: string}>
     */
    public function getLogsFilesList(): array
    {
        $files = [];

        // Directories to scan with their prefixes inside the ZIP
        $logDirs = [
            RANKINGCOACH_LOG_DIR => 'rc-logs/',
            RANKINGCOACH_PLUGIN_DIR . 'app/var/log'    => 'logs/',
        ];

        foreach ($logDirs as $dir => $prefix) {
            if (!is_dir($dir)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->isReadable()) {
                    $relativePath = str_replace($dir . DIRECTORY_SEPARATOR, '', $file->getPathname());
                    $files[] = [
                        'path' => $file->getPathname(),
                        'zip_path' => $prefix . $relativePath,
                    ];
                }
            }
        }

        // Include WordPress debug log if present
        $debugLogPath = WP_CONTENT_DIR . '/debug.log';
        if (file_exists($debugLogPath) && is_readable($debugLogPath)) {
            $files[] = [
                'path' => $debugLogPath,
                'zip_path' => 'wordpress/debug.log',
            ];
        }

        return $files;
    }
}
