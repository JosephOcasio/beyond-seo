<?php
/**
 * BeyondSEO Debug Access MU Plugin
 *
 * Provides REST endpoint for downloading debug logs with token-based authentication.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Constants (consolidated from Config class)
const BEYOND_SEO_DEBUG_TOKEN_OPTION = 'beyondseo_debug_token';
const BEYOND_SEO_DEBUG_LOG_DIR = WP_CONTENT_DIR . '/uploads/rc-logs/';
const BEYOND_SEO_DEBUG_REST_NAMESPACE = 'beyondseo/debug';
const BEYOND_SEO_DEBUG_REST_ROUTE_DOWNLOAD_LOGS = '/download-logs';
const BEYOND_SEO_DEBUG_ZIP_PREFIX = 'beyondseo_logs_';
const BEYOND_SEO_DEBUG_ZIP_FILENAME = 'beyondseo-debug-logs.zip';
const BEYOND_SEO_DEBUG_LOG_EXTENSIONS = ['*.log', '*.jsonl'];

/**
 * Main plugin class
 */
class BeyondSeoDebugAccessPlugin
{
    /**
     * Initialize the plugin
     */
    public function init(): void
    {
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
    }

    /**
     * Register REST API routes
     */
    public function registerRestRoutes(): void
    {
        register_rest_route(
            BEYOND_SEO_DEBUG_REST_NAMESPACE,
            BEYOND_SEO_DEBUG_REST_ROUTE_DOWNLOAD_LOGS,
            [
                'methods' => 'GET',
                'callback' => [$this, 'handleDownloadLogsRequest'],
                'permission_callback' => '__return_true',
                'args' => [
                    'token' => [
                        'required' => true,
                        'validate_callback' => function($param) {
                            return is_string($param) && !empty($param);
                        }
                    ]
                ]
            ]
        );
    }

    /**
     * Handle the download logs REST request
     */
    public function handleDownloadLogsRequest(\WP_REST_Request $request)
    {
        $token = $request->get_param('token');

        // Verify token
        if (!$this->verifyToken($token)) {
            return new \WP_Error(
                'invalid_token',
                'Invalid or missing token',
                ['status' => 401]
            );
        }

        // Check log directory
        if (!$this->isLogDirectoryAccessible()) {
            return new \WP_Error(
                'log_directory_not_found',
                'Log directory not found or not accessible',
                ['status' => 404]
            );
        }

        // Get log files
        $files = $this->getLogFiles();
        if (empty($files)) {
            return new \WP_Error(
                'no_log_files',
                'No log files found',
                ['status' => 404]
            );
        }

        // Create ZIP
        $zipPath = $this->createLogZip($files);
        if (!$zipPath) {
            return new \WP_Error(
                'zip_creation_failed',
                'Could not create ZIP file',
                ['status' => 500]
            );
        }

        // Send download
        $this->sendZipDownload($zipPath);
    }

    /**
     * Verify token
     */
    private function verifyToken(string $token): bool
    {
        $storedToken = get_option(BEYOND_SEO_DEBUG_TOKEN_OPTION);
        return !empty($token) && !empty($storedToken) && $token === $storedToken;
    }

    /**
     * Check if log directory is accessible
     */
    private function isLogDirectoryAccessible(): bool
    {
        return is_dir(BEYOND_SEO_DEBUG_LOG_DIR) && is_readable(BEYOND_SEO_DEBUG_LOG_DIR);
    }

    /**
     * Get log files
     */
    private function getLogFiles(): array
    {
        $files = [];
        foreach (BEYOND_SEO_DEBUG_LOG_EXTENSIONS as $pattern) {
            $files = array_merge($files, glob(BEYOND_SEO_DEBUG_LOG_DIR . $pattern));
        }
        return array_filter($files, 'is_readable');
    }

    /**
     * Create ZIP archive
     */
    private function createLogZip(array $files): string|false
    {
        $zipPath = tempnam(sys_get_temp_dir(), BEYOND_SEO_DEBUG_ZIP_PREFIX) . '.zip';
        $zip = new \ZipArchive();

        if ($zip->open($zipPath, \ZipArchive::CREATE) !== true) {
            return false;
        }

        foreach ($files as $file) {
            if (is_readable($file)) {
                $zip->addFile($file, basename($file));
            }
        }

        $zip->close();
        return $zipPath;
    }

    /**
     * Send ZIP as download
     */
    private function sendZipDownload(string $zipPath): void
    {
        if (!file_exists($zipPath)) {
            // This shouldn't happen, but handle gracefully
            return;
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . BEYOND_SEO_DEBUG_ZIP_FILENAME . '"');
        header('Content-Length: ' . filesize($zipPath));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        readfile($zipPath);
        $this->cleanupTempFile($zipPath);
        exit;
    }

    /**
     * Clean up temporary file
     */
    private function cleanupTempFile(string $filePath): void
    {
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
}

// Initialize the plugin
$plugin = new BeyondSeoDebugAccessPlugin();
$plugin->init();