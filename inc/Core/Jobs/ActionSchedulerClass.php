<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Jobs;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\Settings\SettingsManager;
use RuntimeException;
use Throwable;

/**
 * Abstract base class for ActionScheduler-based jobs.
 * 
 * Provides common functionality for scheduling, unscheduling, and managing
 * recurring jobs using WordPress ActionScheduler. Concrete implementations
 * must define their specific execution logic and configuration.
 */
abstract class ActionSchedulerClass
{
    use RcLoggerTrait;

    /** @var SettingsManager Settings manager instance */
    protected SettingsManager $settingsManager;

    /** @var string The ActionScheduler hook name - must be defined by concrete classes */
    protected const ACTION_HOOK = '';

    /** @var string The settings option key that controls job enablement */
    protected const ENABLE_SETTING_KEY = '';

    /** @var string The settings option key for interval configuration */
    protected const INTERVAL_SETTING_KEY = '';

    /** @var int Default interval in hours */
    protected const DEFAULT_INTERVAL_HOURS = 12;

    /** @var string Log context for this job type */
    protected const LOG_CONTEXT = 'action_scheduler_job';

    /** @var string ActionScheduler group name for all plugin jobs */
    protected const ACTION_GROUP = RANKINGCOACH_BRAND_NAME;

    /**
     * Constructor - initializes settings manager.
     */
    protected function __construct()
    {
        $this->settingsManager = SettingsManager::instance();
    }

    /**
     * Initialize the job by registering hooks and scheduling if needed.
     * This should be called during plugin initialization.
     *
     * @return void
     */
    public function initialize(): void
    {
        $this->validateConfiguration();

        // Always register the action hook (needed for cleanup and execution)
        add_action(static::ACTION_HOOK, [$this, 'execute']);

        // Register settings change listener
        add_action('update_option_' . BaseConstants::OPTION_PLUGIN_SETTINGS, [$this, 'onSettingsUpdate'], 10, 3);

        // Schedule job after ActionScheduler is ready
        if ($this->isActionSchedulerReady()) {
            $this->initializeScheduling();
        } else {
            // Wait for ActionScheduler to be ready - use init hook with higher priority
            // ActionScheduler initializes on 'init' with priority 1, so we use priority 10
            add_action('init', [$this, 'initializeScheduling'], 10);
        }
    }

    /**
     * Initialize job scheduling after ActionScheduler is ready.
     *
     * @return void
     */
    public function initializeScheduling(): void
    {
        // Double-check that ActionScheduler is ready
        if (!$this->isActionSchedulerReady()) {
            return;
        }

        // Schedule job if conditions are met
        if ($this->shouldScheduleJob()) {
            $this->scheduleJob();
        } else {
            $this->unscheduleJob();
        }
    }

    /**
     * Abstract method for job execution logic.
     * Must be implemented by concrete classes.
     *
     * @param bool $forceExecute
     * @return void
     */
    abstract public function execute(bool $forceExecute = false): void;

    /**
     * Schedule the job if not already scheduled.
     *
     * @return bool True if scheduling was successful or already scheduled, false otherwise
     */
    public function scheduleJob(): bool
    {
        if (!$this->isActionSchedulerReady()) {
            //$this->log('ActionScheduler not ready for ' . static::ACTION_HOOK, 'WARNING');
            return false;
        }

        if (!$this->shouldScheduleJob()) {
            //$this->log('Conditions not met for scheduling ' . static::ACTION_HOOK, 'INFO');
            return false;
        }

        try {
            // Check if already scheduled
            if (as_has_scheduled_action(static::ACTION_HOOK, [], static::ACTION_GROUP)) {
                //$this->log('Action already scheduled: ' . static::ACTION_HOOK, 'INFO');
                return true;
            }

            $intervalHours = $this->getIntervalHours();
            
            // Validate interval configuration
            if ($intervalHours <= 0) {
                //$this->log('Invalid interval configured for ' . static::ACTION_HOOK . ': ' . $intervalHours, 'WARNING');
                return false;
            }

            // Schedule the recurring action
            $scheduled = as_schedule_recurring_action(
                time(),
                $intervalHours * HOUR_IN_SECONDS,
                static::ACTION_HOOK,
                [],
                static::ACTION_GROUP
            );

            if ($scheduled) {
                $this->log_json([
                    'operation_type' => static::ACTION_HOOK . '_scheduled',
                    'operation_status' => 'success',
                    'context_entity' => static::LOG_CONTEXT,
                    'context_type' => 'scheduling',
                    'interval_hours' => $intervalHours,
                    'next_run' => gmdate('Y-m-d H:i:s', time() + ($intervalHours * HOUR_IN_SECONDS))
                ], static::LOG_CONTEXT);
                return true;
            }

            $this->log('Failed to schedule action: ' . static::ACTION_HOOK, 'ERROR');
            return false;

        } catch (Throwable $e) {
            $this->log_json([
                'operation_type' => static::ACTION_HOOK . '_scheduling',
                'operation_status' => 'error',
                'context_entity' => static::LOG_CONTEXT,
                'context_type' => 'scheduling',
                'error_details' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine()
            ], static::LOG_CONTEXT);
            return false;
        }
    }

    /**
     * Unschedule all job actions.
     *
     * @return bool True if unscheduling was successful, false otherwise
     */
    public function unscheduleJob(): bool
    {
        if (!$this->isActionSchedulerReady()) {
            return false;
        }

        try {
            $unscheduled = as_unschedule_all_actions(static::ACTION_HOOK, [], static::ACTION_GROUP);

            if ($unscheduled > 0) {
                $this->log_json([
                    'operation_type' => static::ACTION_HOOK . '_cleanup',
                    'operation_status' => 'success',
                    'context_entity' => static::LOG_CONTEXT,
                    'context_type' => 'cleanup',
                    'actions_removed' => $unscheduled,
                    'timestamp' => current_time('mysql')
                ], static::LOG_CONTEXT);
            }

            return true;

        } catch (Throwable $e) {
            $this->log_json([
                'operation_type' => static::ACTION_HOOK . '_cleanup',
                'operation_status' => 'error',
                'context_entity' => static::LOG_CONTEXT,
                'context_type' => 'cleanup',
                'error_details' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'timestamp' => current_time('mysql')
            ], static::LOG_CONTEXT);
            return false;
        }
    }

    /**
     * Handle settings update to monitor changes to job settings.
     *
     * @param mixed $old_value The old option value
     * @param mixed $value The new option value
     * @param string $option The option name
     * @return void
     */
    public function onSettingsUpdate($old_value, $value, string $option): void
    {
        // Only process if ActionScheduler is ready
        if (!$this->isActionSchedulerReady()) {
            // If ActionScheduler is not ready yet, schedule the update for later
            add_action('init', function() use ($old_value, $value, $option) {
                if ($this->isActionSchedulerReady()) {
                    $this->processSettingsUpdate($old_value, $value, $option);
                }
            }, 10);
            return;
        }

        $this->processSettingsUpdate($old_value, $value, $option);
    }

    /**
     * Process settings update after ActionScheduler is ready.
     *
     * @param mixed $old_value The old option value
     * @param mixed $value The new option value
     * @param string $option The option name
     * @return void
     */
    protected function processSettingsUpdate($old_value, $value, string $option): void
    {

        // Extract the old and new values for job settings
        $oldSettings = is_array($old_value) ? $old_value : [];
        $newSettings = is_array($value) ? $value : [];

        $oldJobEnabled = $this->extractBooleanSetting($oldSettings, static::ENABLE_SETTING_KEY);
        $newJobEnabled = $this->extractBooleanSetting($newSettings, static::ENABLE_SETTING_KEY);

        $oldInterval = $this->extractIntervalSetting($oldSettings);
        $newInterval = $this->extractIntervalSetting($newSettings);

        // Handle job enablement changes
        if ($oldJobEnabled !== $newJobEnabled) {
            if (!$newJobEnabled) {
                // Job was disabled
                $this->unscheduleJob();
                $this->log_json([
                    'operation_type' => static::ACTION_HOOK . '_setting_changed',
                    'operation_status' => 'disabled',
                    'context_entity' => static::LOG_CONTEXT,
                    'context_type' => 'settings_update',
                    'message' => 'Job disabled, cleaned up scheduled actions',
                    'timestamp' => current_time('mysql')
                ], static::LOG_CONTEXT);
            } elseif ($newJobEnabled && $this->areAdditionalConditionsMet()) {
                // Job was enabled
                $this->scheduleJob();
                $this->log_json([
                    'operation_type' => static::ACTION_HOOK . '_setting_changed',
                    'operation_status' => 'enabled',
                    'context_entity' => static::LOG_CONTEXT,
                    'context_type' => 'settings_update',
                    'message' => 'Job enabled, scheduled actions',
                    'timestamp' => current_time('mysql')
                ], static::LOG_CONTEXT);
            }
        }
        // Handle interval changes (only if job is enabled)
        elseif ($newJobEnabled && $oldInterval !== $newInterval) {
            // Reschedule with new interval
            $this->unscheduleJob();
            $this->scheduleJob();
            $this->log_json([
                'operation_type' => static::ACTION_HOOK . '_interval_changed',
                'operation_status' => 'rescheduled',
                'context_entity' => static::LOG_CONTEXT,
                'context_type' => 'settings_update',
                'old_interval' => $oldInterval,
                'new_interval' => $newInterval,
                'message' => 'Job interval changed, rescheduled actions',
                'timestamp' => current_time('mysql')
            ], static::LOG_CONTEXT);
        }
    }

    /**
     * Check if the job is enabled in settings.
     *
     * @return bool
     */
    public function isJobEnabled(): bool
    {
        if (empty(static::ENABLE_SETTING_KEY)) {
            return true; // If no setting key defined, assume enabled
        }
        return (bool)$this->settingsManager->get_option(static::ENABLE_SETTING_KEY, false);
    }

    /**
     * Get the job interval in hours from settings.
     *
     * @return int
     */
    protected function getIntervalHours(): int
    {
        return static::DEFAULT_INTERVAL_HOURS;
    }

    /**
     * Check if job should be scheduled based on current conditions.
     * Can be overridden by concrete classes for additional conditions.
     *
     * @return bool
     */
    public function shouldScheduleJob(): bool
    {
        return $this->isJobEnabled() && 
               $this->areAdditionalConditionsMet() && 
               $this->isActionSchedulerReady();
    }

    /**
     * Check additional conditions for job scheduling.
     * Override in concrete classes for specific requirements.
     *
     * @return bool
     */
    protected function areAdditionalConditionsMet(): bool
    {
        return true;
    }

    /**
     * Check if ActionScheduler is available and functional.
     *
     * @return bool
     */
    protected function isActionSchedulerAvailable(): bool
    {
        return function_exists('as_has_scheduled_action') && 
               function_exists('as_schedule_recurring_action') && 
               function_exists('as_unschedule_all_actions');
    }

    /**
     * Check if ActionScheduler is fully ready (data store initialized).
     *
     * @return bool
     */
    protected function isActionSchedulerReady(): bool
    {
        if (!$this->isActionSchedulerAvailable()) {
            return false;
        }

        // Check if ActionScheduler data store is initialized
        try {
            // Use the official ActionScheduler method if available
            if (class_exists('Action_Scheduler') && method_exists('Action_Scheduler', 'is_initialized')) {
                return \Action_Scheduler::is_initialized();
            }
            
            // Fallback: check if the init action has been fired
            return did_action('action_scheduler_init') > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Check if the job action is currently scheduled.
     *
     * @return bool
     */
    public function isScheduled(): bool
    {
        if (!$this->isActionSchedulerReady()) {
            return false;
        }

        return as_has_scheduled_action(static::ACTION_HOOK);
    }

    /**
     * Validate that concrete class has properly configured constants.
     *
     * @throws RuntimeException If configuration is invalid
     */
    private function validateConfiguration(): void
    {
        if (empty(static::ACTION_HOOK)) {
            throw new RuntimeException('ACTION_HOOK constant must be defined in concrete class');
        }
    }

    /**
     * Extract boolean setting from settings array.
     *
     * @param array $settings Settings array
     * @param string $key Setting key
     * @return bool
     */
    private function extractBooleanSetting(array $settings, string $key): bool
    {
        if (empty($key)) {
            return false;
        }
        return isset($settings[$key]) && (bool)$settings[$key];
    }

    /**
     * Extract interval setting from settings array.
     *
     * @param array $settings Settings array
     * @return int
     */
    private function extractIntervalSetting(array $settings): int
    {
        if (empty(static::INTERVAL_SETTING_KEY)) {
            return static::DEFAULT_INTERVAL_HOURS;
        }
        return (int)($settings[static::INTERVAL_SETTING_KEY] ?? static::DEFAULT_INTERVAL_HOURS);
    }
}
