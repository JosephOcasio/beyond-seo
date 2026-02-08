<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core;

if ( !defined('ABSPATH') ) {
    exit;
}

use Exception;
use RankingCoach\Inc\Core\Api\Content\ContentApiManager;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use Throwable;

/**
 * Class CronJobManager
 */
class CronJobManager {

	use RcLoggerTrait;

	/**
	 * Singleton instance of CronJobManager.
	 *
	 * @var CronJobManager|null
	 */
	private static ?CronJobManager $instance = null;

	/**
	 * Returns the singleton instance of CronJobManager.
	 *
	 * @return CronJobManager|null
	 */
	public static function instance(): ?CronJobManager {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initializes default cron jobs during plugin activation.
	 *
	 * @return void
	 */
	public function initialize(): void {

        // Register the cron schedules
        add_filter('cron_schedules', [$this, 'define_cron_schedules']);

        // Register token validity check task
        add_action(BaseConstants::OPTION_CRONJOB_TWICE_HOURLY_EVENT, [$this, 'rankingcoach_twice_hourly_task_handler']);

		$this->add_cron_job( BaseConstants::OPTION_CRONJOB_DAILY_EVENT, 'daily', [ $this, 'rankingcoach_daily_task_handler' ] );
		$this->add_cron_job( BaseConstants::OPTION_CRONJOB_TWICE_HOURLY_EVENT, 'twice_hourly', [ $this, 'rankingcoach_twice_hourly_task_handler' ] );
		$this->add_cron_job( BaseConstants::OPTION_CRONJOB_HOURLY_EVENT, 'hourly', [ $this, 'rankingcoach_hourly_task_handler' ] );
	}

	/**
	 * Registers a new cron job with the specified recurrence interval.
	 *
	 * @param string $hook The action hook for the cron job.
	 * @param string $recurrence Recurrence interval (e.g., 'hourly', 'daily').
	 * @param callable $callback Callback function to execute.
	 *
	 * @return void
	 */
	public function add_cron_job( string $hook, string $recurrence, callable $callback ): void {
		if ( ! wp_next_scheduled( $hook ) ) {
			wp_schedule_event( time(), $recurrence, $hook );
		}
	}

	/**
	 * Removes a specified cron job.
	 *
	 * @param string $hook The action hook for the cron job.
	 *
	 * @return void
	 */
	public function remove_cron_job( string $hook ): void {
		$timestamp = wp_next_scheduled( $hook );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, $hook );
			remove_action( $hook, $hook );
		}
	}

	/**
	 * Removes all cron jobs associated with the plugin.
	 *
	 * @return void
	 */
	public function clearAllCronJobs(): void {
		$this->remove_cron_job( BaseConstants::OPTION_CRONJOB_DAILY_EVENT );
		$this->remove_cron_job( BaseConstants::OPTION_CRONJOB_TWICE_HOURLY_EVENT );
		$this->remove_cron_job( BaseConstants::OPTION_CRONJOB_HOURLY_EVENT );
	}

	/**
	 * Checks if a specified cron job is scheduled.
	 *
	 * @param string $hook The action hook for the cron job.
	 *
	 * @return bool True if scheduled, false otherwise.
	 */
	public function is_cron_job_scheduled( string $hook ): bool {
		return wp_next_scheduled( $hook ) !== false;
	}

	/**
	 * Executes a specific cron job manually.
	 *
	 * @param string $hook The action hook for the cron job.
	 *
	 * @return void
	 */
	public function run_cron_job_now( string $hook ): void {
		if ( has_action( $hook ) ) {
			do_action( $hook );
		}
	}

	/**
	 * Handles the daily task for this plugin.
	 *
	 * @return void
	 */
	public function rankingcoach_daily_task_handler(): void {
		// Logic for a daily task.
		$this->log_event( 'Daily task executed.' );
	}

    /**
     * Handles the daily task for this plugin.
     *
     * @return void
     * @throws Throwable
     */
	public function rankingcoach_twice_hourly_task_handler(): void {

		$this->log_event( 'Twice hourly task executed.' );

        // Calculate the remaining days for the refresh token
        /** @var TokensManager $tokensManager */
        $tokensManager = TokensManager::instance();
        $tokensManager->calculateRefreshTokenRemainingDays();
	}

	/**
	 * Handles the hourly task for this plugin.
	 *
	 * @return void
     * @throws Throwable
	 */
	public function rankingcoach_hourly_task_handler(): void {
		// Logic for an hourly task.
		$this->log_event( 'Hourly task executed.' );
	}

	/**
	 * Logs cron events for debugging purposes.
	 *
	 * @param string $message The message to log.
	 *
	 * @return void
	 */
	private function log_event( string $message ): void {
        // TODO: Uncomment this line
		//if ( defined( 'RANKINGCOACH_WP_DEBUG' ) && RANKINGCOACH_WP_DEBUG ) {
			$this->log( '[CronJobManager] ' . $message );
		//}
	}

	/**
	 * Defines custom cron schedules.
	 *
	 * @param array $schedules The existing cron schedules.
	 *
	 * @return array The modified cron schedules.
	 */
	public function define_cron_schedules( array $schedules ): array {
		// Add a new interval of 30 minutes
		if ( ! isset( $schedules['twice_hourly'] ) ) {
			$schedules['twice_hourly'] = [
				'interval' => MINUTE_IN_SECONDS * 30,
				'display'  => 'Every 30 minutes',
			];
		}
		// Add a new interval of 1 day
		if ( ! isset( $schedules['daily'] ) ) {
			$schedules['daily'] = [
				'interval' => DAY_IN_SECONDS,
				'display'  => 'Once Daily',
			];
		}
		// Add a new interval of 1 hour
		if ( ! isset( $schedules['hourly'] ) ) {
			$schedules['hourly'] = [
				'interval' => HOUR_IN_SECONDS,
				'display'  => 'Once Hourly'
			];
		}

		return $schedules;
	}

	/**
	 * Retrieves all scheduled cron jobs.
	 *
	 * @return array|null An array of scheduled cron jobs.
	 */
	public function get_all_scheduled_cron_jobs(): ?array {
		// Retrieve the cron option from the WordPress database
		$cron = _get_cron_array();

		if ( empty( $cron ) ) {
			return null;
		}

		$scheduled_cron_jobs = [];
		$current_time        = time();

		foreach ( $cron as $timestamp => $hooks ) {
			foreach ( $hooks as $hook => $details ) {
				foreach ( $details as $job ) {
					$scheduled_cron_jobs[] = [
						'hook'      => $hook,
						'schedule'  => $job['schedule'] ?? 'One-time',
						'args'      => $job['args'],
						'interval'  => $job['interval'] ?? null,
						'next_run'  => gmdate( 'Y-m-d H:i:s', $timestamp ),
						'time_left' => $timestamp - $current_time,
					];
				}
			}
		}

		return $scheduled_cron_jobs;
	}

	/**
	 * Validate the refresh token
	 * @param $cron_report
	 * @return void
	 */
	public function saveRefreshTokenRemainingDays($cron_report): void
	{
		// Save the report in the option table
		update_option(BaseConstants::OPTION_CRONJOB_REPORT, $cron_report);
	}
}
