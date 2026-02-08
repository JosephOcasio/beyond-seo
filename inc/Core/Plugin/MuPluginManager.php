<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Plugin;

use Exception;
use RankingCoach\Inc\Core\Base\BaseConstants;
use ZipArchive;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class MuPluginManager
 *
 * Manages must-use plugins for the RankingCoach plugin.
 *
 * @since 1.0.0
 */
class MuPluginManager
{
    /**
     * Sets up the debug access must-use plugin.
     *
     * This method installs the BeyondSEO debug access MU plugin by copying
     * the single plugin file to the mu-plugins directory and loading it.
     *
     * @return void
     * @throws Exception If the MU plugin cannot be installed
     */
    public static function setupDebugAccessPlugin(): void
    {
        try {
            $muPluginsDir = WP_CONTENT_DIR . '/mu-plugins/';

            // Ensure mu-plugins directory exists
            if (!is_dir($muPluginsDir)) {
                if (!mkdir($muPluginsDir, 0755, true)) {
                    throw new Exception('Failed to create mu-plugins directory');
                }
            }

            if (!is_writable($muPluginsDir)) {
                throw new Exception('mu-plugins directory is not writable');
            }

            $sourceFile = plugin_dir_path(RANKINGCOACH_FILE) . 'inc/Core/Plugin/mu/beyondseo-debug-access.php';
            $destinationFile = $muPluginsDir . 'beyondseo-debug-access.php';

            // Check if MU plugin already exists
            if (file_exists($destinationFile)) {
                error_log('BeyondSEO debug access MU plugin already exists');
                return;
            }

            // Copy the plugin file
            if (!copy($sourceFile, $destinationFile)) {
                throw new Exception('Failed to copy MU plugin file');
            }

            // Load the plugin file
            require_once $destinationFile;

            error_log('BeyondSEO debug access MU plugin installed successfully');

        } catch (Exception $e) {
            error_log('Failed to install BeyondSEO debug access MU plugin: ' . $e->getMessage());
        }
    }

    /**
     * Creates a ZIP file of debug logs and returns the ZIP file path.
     *
     * @param array $files Array of file paths to include in the ZIP
     * @param string $prefix Optional prefix for the temporary ZIP file name
     * @return string|false The path to the created ZIP file, or false on failure
     */
    public static function createDebugLogsZip(array $files, string $prefix = 'debug_logs_'): string|false
    {
        try {
            $zipPath = tempnam(sys_get_temp_dir(), $prefix) . '.zip';
            $zip = new ZipArchive();

            if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
                return false;
            }

            foreach ($files as $file) {
                if (is_readable($file)) {
                    $zip->addFile($file, basename($file));
                }
            }

            $zip->close();

            $installationId = get_option(BaseConstants::OPTION_INSTALLATION_ID, '_');
            $pluginVersion = get_option(BaseConstants::OPTION_PLUGIN_VERSION, '_');
            $accountId = get_option(BaseConstants::OPTION_RANKINGCOACH_ACCOUNT_ID, '_');
            $projectId = get_option(BaseConstants::OPTION_RANKINGCOACH_PROJECT_ID, '_');

            $newFilename = $prefix . $installationId . '_' . $pluginVersion . '_' . $accountId . '_' . $projectId . '.zip';
            $newZipPath = sys_get_temp_dir() . '/' . $newFilename;

            rename($zipPath, $newZipPath);
            return $newZipPath;

        } catch (Exception $e) {
            error_log('Failed to create debug logs ZIP: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Reads debug log files from the configured directory and creates a ZIP file.
     *
     * @return string|false The path to the created ZIP file, or false on failure
     */
    public static function getDebugLogsZip(): string|false
    {
        try {
            $logDir = defined('RANKINGCOACH_LOG_DIR') ? RANKINGCOACH_LOG_DIR : null;

            if (!$logDir || !is_dir($logDir)) {
                error_log('Debug logs directory does not exist: ' . $logDir);
                return false;
            }

            $files = [];
            $dirHandle = opendir($logDir);

            if ($dirHandle) {
                while (($file = readdir($dirHandle)) !== false) {
                    if ($file !== '.' && $file !== '..') {
                        $filePath = $logDir . $file;
                        if (is_file($filePath) && is_readable($filePath)) {
                            $files[] = $filePath;
                        }
                    }
                }
                closedir($dirHandle);
            }

            if (empty($files)) {
                error_log('No debug log files found in directory: ' . $logDir);
                return false;
            }

            return self::createDebugLogsZip($files);

        } catch (Exception $e) {
            error_log('Failed to get debug logs ZIP: ' . $e->getMessage());
            return false;
        }
    }
}