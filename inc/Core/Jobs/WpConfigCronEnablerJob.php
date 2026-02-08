<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Jobs;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use Throwable;

/**
 * Class WpConfigCronEnablerJob
 * 
 * Manages WordPress cron enablement by modifying wp-config.php to ensure
 * DISABLE_WP_CRON is properly configured. This job monitors the plugin setting
 * and automatically enables/disables WordPress cron functionality as needed.
 * 
 * The job creates backups before modifications and handles file permission issues gracefully.
 */
class WpConfigCronEnablerJob extends ActionSchedulerClass
{
    /** @var string The ActionScheduler hook name for cron enablement */
    protected const ACTION_HOOK = 'rc_wp_cron_enabler';

    /** @var string The settings option key that controls cron enablement service */
    protected const ENABLE_SETTING_KEY = 'enable_wp_cron_service';

    /** @var int Default check interval in hours (daily) */
    protected const DEFAULT_INTERVAL_HOURS = 24;

    /** @var string Log context for cron enablement operations */
    protected const LOG_CONTEXT = 'wp_cron_enabler';

    /** @var string The wp-config.php constant name to manage */
    private const WP_CRON_CONSTANT = 'DISABLE_WP_CRON';

    /** @var string Backup file suffix */
    private const BACKUP_SUFFIX = '.backup';

    /** @var self|null Singleton instance */
    private static ?self $instance = null;

    /**
     * Private constructor to enforce singleton pattern.
     */
    private function __construct()
    {
        parent::__construct();
    }

    /**
     * Get singleton instance.
     *
     * @return self
     */
    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Execute the WordPress cron enablement process.
     * This method is called by ActionScheduler when the scheduled action runs.
     *
     * @param bool $forceExecute
     * @return void
     */
    public function execute(bool $forceExecute = false): void
    {
        try {
            // Validate that cron enablement service is still enabled
            if (!$this->isJobEnabled()) {
                $this->log_json([
                    'operation_type' => 'wp_cron_enablement',
                    'operation_status' => 'skipped_disabled',
                    'context_entity' => 'wp_cron_enabler_job',
                    'context_type' => 'cron_management',
                    'message' => 'WP Cron enablement service disabled in settings, cleaning up schedule',
                    'timestamp' => current_time('mysql')
                ], static::LOG_CONTEXT);

                // Clean up the schedule since service is now disabled
                $this->unscheduleJob();
                return;
            }

            // Check current cron status
            $cronStatus = $this->getCurrentCronStatus();
            
            // Update last check timestamp
            update_option(BaseConstants::OPTION_WP_CRON_LAST_CHECK, current_time('mysql'));
            
            $this->log_json([
                'operation_type' => 'wp_cron_status_check',
                'operation_status' => 'completed',
                'context_entity' => 'wp_cron_enabler_job',
                'context_type' => 'cron_management',
                'cron_currently_enabled' => $cronStatus['enabled'],
                'disable_wp_cron_defined' => $cronStatus['constant_defined'],
                'disable_wp_cron_value' => $cronStatus['constant_value'],
                'timestamp' => current_time('mysql')
            ], static::LOG_CONTEXT);

            // Only modify wp-config.php if cron is currently disabled
            if (!$cronStatus['enabled']) {
                $result = $this->enableWordPressCron();
                
                $this->log_json([
                    'operation_type' => 'wp_cron_enablement',
                    'operation_status' => $result ? 'completed_successfully' : 'failed',
                    'context_entity' => 'wp_cron_enabler_job',
                    'context_type' => 'cron_management',
                    'modification_attempted' => true,
                    'result' => $result,
                    'timestamp' => current_time('mysql')
                ], static::LOG_CONTEXT);
            } else {
                $this->log_json([
                    'operation_type' => 'wp_cron_enablement',
                    'operation_status' => 'skipped_already_enabled',
                    'context_entity' => 'wp_cron_enabler_job',
                    'context_type' => 'cron_management',
                    'message' => 'WordPress cron is already enabled, no modification needed',
                    'timestamp' => current_time('mysql')
                ], static::LOG_CONTEXT);
            }

        } catch (Throwable $e) {
            $this->log_json([
                'operation_type' => 'wp_cron_enablement',
                'operation_status' => 'error',
                'context_entity' => 'wp_cron_enabler_job',
                'context_type' => 'cron_management',
                'error_details' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'timestamp' => current_time('mysql')
            ], static::LOG_CONTEXT);
        }
    }

    /**
     * Get current WordPress cron status by analyzing wp-config.php and runtime state.
     *
     * @return array Status information including enabled state and constant details
     */
    private function getCurrentCronStatus(): array
    {
        $wpConfigPath = $this->getWpConfigPath();
        $constantDefined = defined(self::WP_CRON_CONSTANT);
        $constantValue = $constantDefined ? constant(self::WP_CRON_CONSTANT) : null;
        
        // WordPress cron is enabled if:
        // 1. DISABLE_WP_CRON is not defined, OR
        // 2. DISABLE_WP_CRON is explicitly set to false
        $cronEnabled = !$constantDefined || $constantValue === false;

        return [
            'enabled' => $cronEnabled,
            'constant_defined' => $constantDefined,
            'constant_value' => $constantValue,
            'wp_config_path' => $wpConfigPath,
            'wp_config_exists' => file_exists($wpConfigPath),
            'wp_config_writable' => wp_is_writable($wpConfigPath)
        ];
    }

    /**
     * Enable WordPress cron by modifying wp-config.php.
     *
     * @return bool True if successful, false otherwise
     */
    private function enableWordPressCron(): bool
    {
        $wpConfigPath = $this->getWpConfigPath();
        
        if (!file_exists($wpConfigPath)) {
            $this->log_json([
                'operation_type' => 'wp_config_modification',
                'operation_status' => 'error',
                'context_entity' => 'wp_cron_enabler_job',
                'context_type' => 'file_system',
                'error_details' => 'wp-config.php file not found',
                'wp_config_path' => $wpConfigPath,
                'timestamp' => current_time('mysql')
            ], static::LOG_CONTEXT);
            return false;
        }

        if (!wp_is_writable($wpConfigPath)) {
            $this->log_json([
                'operation_type' => 'wp_config_modification',
                'operation_status' => 'error',
                'context_entity' => 'wp_cron_enabler_job',
                'context_type' => 'file_system',
                'error_details' => 'wp-config.php is not writable',
                'wp_config_path' => $wpConfigPath,
                'file_permissions' => substr(sprintf('%o', fileperms($wpConfigPath)), -4),
                'timestamp' => current_time('mysql')
            ], static::LOG_CONTEXT);
            return false;
        }

        try {
            // Create backup before modification
            if (!$this->createWpConfigBackup($wpConfigPath)) {
                return false;
            }

            // Read current wp-config.php content
            $content = file_get_contents($wpConfigPath);
            if ($content === false) {
                throw new \RuntimeException('Failed to read wp-config.php content');
            }

            // Modify the content to enable cron
            $modifiedContent = $this->modifyWpConfigContent($content);
            
            if ($modifiedContent === $content) {
                $this->log_json([
                    'operation_type' => 'wp_config_modification',
                    'operation_status' => 'no_changes_needed',
                    'context_entity' => 'wp_cron_enabler_job',
                    'context_type' => 'file_system',
                    'message' => 'No modifications needed in wp-config.php',
                    'timestamp' => current_time('mysql')
                ], static::LOG_CONTEXT);
                return true;
            }

            // Write the modified content
            $result = file_put_contents($wpConfigPath, $modifiedContent, LOCK_EX);
            if ($result === false) {
                throw new \RuntimeException('Failed to write modified content to wp-config.php');
            }

            $this->log_json([
                'operation_type' => 'wp_config_modification',
                'operation_status' => 'completed_successfully',
                'context_entity' => 'wp_cron_enabler_job',
                'context_type' => 'file_system',
                'bytes_written' => $result,
                'backup_created' => true,
                'timestamp' => current_time('mysql')
            ], static::LOG_CONTEXT);

            return true;

        } catch (Throwable $e) {
            $this->log_json([
                'operation_type' => 'wp_config_modification',
                'operation_status' => 'error',
                'context_entity' => 'wp_cron_enabler_job',
                'context_type' => 'file_system',
                'error_details' => $e->getMessage(),
                'wp_config_path' => $wpConfigPath,
                'timestamp' => current_time('mysql')
            ], static::LOG_CONTEXT);
            return false;
        }
    }

    /**
     * Modify wp-config.php content to enable WordPress cron.
     *
     * @param string $content Original wp-config.php content
     * @return string Modified content
     */
    private function modifyWpConfigContent(string $content): string
    {
        $patterns = [
            // Pattern 1: define( 'DISABLE_WP_CRON', true );
            '/define\s*\(\s*[\'"]DISABLE_WP_CRON[\'"]\s*,\s*true\s*\)\s*;/i',
            // Pattern 2: define('DISABLE_WP_CRON', true);
            '/define\s*\(\s*[\'"]DISABLE_WP_CRON[\'"]\s*,\s*true\s*\)\s*;/i',
            // Pattern 3: Any variation with whitespace
            '/define\s*\(\s*[\'"]DISABLE_WP_CRON[\'"]\s*,\s*true\s*\)\s*;/im'
        ];

        $replacement = "// define( 'DISABLE_WP_CRON', true ); // Previously disabled - kept for reference\n// WordPress Cron has been re-enabled by SEO WP Cron Enabler at client request\n// to ensure proper execution of scheduled tasks and plugin functionality\ndefine( 'DISABLE_WP_CRON', false );";
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return preg_replace($pattern, $replacement, $content, 1);
            }
        }

        // If no existing DISABLE_WP_CRON found, look for a good place to add it
        // Try to add it after the last define() statement before the "stop editing" comment
        $insertPattern = '/(\/\*\s*That\'s all, stop editing.*?\*\/)/i';
        if (preg_match($insertPattern, $content)) {
            $insertContent = "\n// WordPress Cron enabled by SEO WP Cron Enabler at client request\n// to ensure proper execution of scheduled tasks and plugin functionality\ndefine( 'DISABLE_WP_CRON', false );\n\n";
            return preg_replace($insertPattern, $insertContent . '$1', $content);
        }

        return $content;
    }

    /**
     * Create a backup of wp-config.php before modification.
     *
     * @param string $wpConfigPath Path to wp-config.php
     * @return bool True if backup created successfully, false otherwise
     */
    private function createWpConfigBackup(string $wpConfigPath): bool
    {
        $backupPath = $wpConfigPath . self::BACKUP_SUFFIX . '.' . gmdate('Y-m-d-H-i-s');
        
        try {
            $result = copy($wpConfigPath, $backupPath);
            if (!$result) {
                throw new \RuntimeException('Failed to create backup file');
            }

            $this->log_json([
                'operation_type' => 'wp_config_backup',
                'operation_status' => 'completed_successfully',
                'context_entity' => 'wp_cron_enabler_job',
                'context_type' => 'file_system',
                'backup_path' => $backupPath,
                'original_path' => $wpConfigPath,
                'timestamp' => current_time('mysql')
            ], static::LOG_CONTEXT);

            return true;

        } catch (Throwable $e) {
            $this->log_json([
                'operation_type' => 'wp_config_backup',
                'operation_status' => 'error',
                'context_entity' => 'wp_cron_enabler_job',
                'context_type' => 'file_system',
                'error_details' => $e->getMessage(),
                'backup_path' => $backupPath,
                'original_path' => $wpConfigPath,
                'timestamp' => current_time('mysql')
            ], static::LOG_CONTEXT);
            return false;
        }
    }

    /**
     * Get the path to wp-config.php file.
     *
     * @return string Path to wp-config.php
     */
    private function getWpConfigPath(): string
    {
        // First try the standard location (WordPress root)
        $standardPath = ABSPATH . 'wp-config.php';
        if (file_exists($standardPath)) {
            return $standardPath;
        }

        // Try one level up (common in some installations)
        $parentPath = dirname(ABSPATH) . '/wp-config.php';
        if (file_exists($parentPath)) {
            return $parentPath;
        }

        // Return standard path as fallback
        return $standardPath;
    }

    /**
     * Check additional conditions for cron enablement scheduling.
     * No additional conditions required for this job.
     *
     * @return bool Always true for this job
     */
    protected function areAdditionalConditionsMet(): bool
    {
        return WordpressHelpers::isOnboardingCompleted();
    }

    /**
     * Get current WordPress cron status for external access.
     *
     * @return array Current cron status information
     */
    public function getCronStatus(): array
    {
        return $this->getCurrentCronStatus();
    }

    /**
     * Manually enable WordPress cron (for admin/testing purposes).
     *
     * @return bool True if successful, false otherwise
     */
    public function enableCronNow(): bool
    {
        if (!$this->isJobEnabled()) {
            $this->log('Cannot enable cron manually: service is disabled', 'WARNING');
            return false;
        }

        try {
            return $this->enableWordPressCron();
        } catch (Throwable $e) {
            $this->log('Manual cron enablement failed: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }
}
