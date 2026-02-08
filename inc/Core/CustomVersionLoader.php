<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core;

if ( !defined('ABSPATH') ) {
	exit;
}

use Doctrine\Persistence\Mapping\MappingException;
use Exception;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Base\Traits\RcInstanceCreatorTrait;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use stdClass;
use WP_Upgrader;
use function rcdc;

/**
 * Represents a system responsible for automatically updating an application
 * or software to the latest version available.
 *
 * This class is designed to handle the process of checking for updates,
 * downloading new versions, and applying updates in a seamless manner.
 */
class CustomVersionLoader {
    use RcLoggerTrait;
    use RcInstanceCreatorTrait;

    protected static ?self $instance = null;

	protected string $current_version;
	protected string $plugin_slug;
	protected string $plugin_file;
	protected string $update_url;
	protected string $plugin_name;

	protected const HOUR_IN_SECONDS = 3600;

	public const RANKINGCOACH_UPDATE_PLUGIN_URL = 'https://wordpress.rankingcoach.com/update/archives/beyond-seo.json';

    /**
     * Auto_Updater constructor.
     *
     * @param string|null $plugin_file
     * @throws MappingException
     */
	public function __construct( ?string $plugin_file = null) {
        if(!$plugin_file) {
            $plugin_file = RANKINGCOACH_PLUGIN_BASENAME;
        }
		if( !function_exists('get_plugin_data') ){
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}

		$this->plugin_file = plugin_basename($plugin_file);
		$this->plugin_slug = dirname($this->plugin_file);
		$this->current_version = get_plugin_data( WP_PLUGIN_DIR . '/' . $this->plugin_file, false, false)['Version'];
		$this->plugin_name = get_plugin_data(WP_PLUGIN_DIR . '/' . $this->plugin_file, false, false)['Name'];
		$this->update_url = self::RANKINGCOACH_UPDATE_PLUGIN_URL;

		// Hook into the update check
		add_filter('pre_set_site_transient_update_plugins', [ $this, 'checkUpdate'] );
		// Hook into the plugin info screen
		add_filter('plugins_api', [ $this, 'pluginInfo'], 20, 3);


        // Delete Symfony cache to reset caching after update
        add_action('upgrader_process_complete', function (WP_Upgrader $upgrader, array $options = []){
            try {
                rcdc();
            } catch (MappingException $e) {
                // Doing nothing if cache clearing fails
            }
        });
        // Hook to update the plugin version option after update completion
        add_action('upgrader_process_complete', [ $this, 'syncPluginVersionOnUpdate'], 10, 2);
	}

    /**
     * Get the instance of the class
     * @param string|null $params
     * @return CustomVersionLoader
     * @throws MappingException
     */
    public static function getInstance(?string $params = null): CustomVersionLoader {
        if (null === self::$instance) {
            self::$instance = new self($params);
            // Ensure plugin version is always synchronized
            self::$instance->ensurePluginVersionSync();
        }
        return self::$instance;
    }

	/**
	 * Force update check
	 * @return void
	 */
	public static function forceUpdateCheck(): void {
		// Delete the cached update information
		delete_transient(BaseConstants::OPTION_AUTOUPDATE_PLUGIN_UPDATE_INFO);

		// Delete the WordPress core updates cache
		delete_site_transient('update_plugins');

		// Trigger the update check
		wp_clean_plugins_cache();

		// Trigger the update check
		wp_update_plugins();
	}

	/**
	 * Check for updates
	 * @param $transient
	 * @return mixed
	 */
	public function checkUpdate($transient): mixed {

		if (empty($transient->checked)) {
			return $transient;
		}

		// Get update info from your server
		$remote_info = $this->getRemoteInfo();

		if ($remote_info && version_compare($this->current_version, $remote_info->version, '<')) {
			$obj = new stdClass();
			$obj->slug = $this->plugin_slug;
			$obj->new_version = $remote_info->version;
			$obj->url = $remote_info->url;
			$obj->package = $remote_info->download_url;
			$obj->name = $this->plugin_name;
			$obj->requires = $remote_info->requires;
			$obj->tested = $remote_info->tested;
			$obj->requires_php = $remote_info->requires_php;
			// Add plugin icons for the update page
			$obj->icons = $this->getPluginIcons($remote_info);

			$transient->response[$this->plugin_file] = $obj;
		}

		//wp_die(json_encode($transient));

		return $transient;
	}

	/**
	 * Get plugin info
	 * @param $false
	 * @param $action
	 * @param $response
	 * @return mixed
	 */
	public function pluginInfo($false, $action, $response): mixed {
		if ($action !== 'plugin_information') {
			return $false;
		}

		if ($response->slug !== $this->plugin_slug) {
			return $false;
		}

		$remote_info = $this->getRemoteInfo();

		if (!$remote_info) {
			return $false;
		}

		$response = new stdClass();
		$response->name = $this->plugin_name;
		$response->slug = $this->plugin_slug;
		$response->version = $remote_info->version;
		$response->author = $remote_info->author;
		$response->requires = $remote_info->requires;
		$response->requires_php = $remote_info->requires_php;
		$response->tested = $remote_info->tested;
		$response->last_updated = $remote_info->last_updated;
		$response->sections = [
			'description' => $remote_info->description,
			'changelog' => $remote_info->changelog
		];
		$response->download_link = $remote_info->download_url;
		// Add plugin icons for the plugin info popup
		$response->icons = $this->getPluginIcons($remote_info);

		return $response;
	}

	/**
	 * Get remote info
	 * @return mixed
	 */
	private function getRemoteInfo(): mixed {
		// Check transient first
		$cache = get_transient(BaseConstants::OPTION_AUTOUPDATE_PLUGIN_UPDATE_INFO);
		if ($cache !== false) {
			return $cache;
		}

		// Get info from your update server
		$response = wp_remote_get($this->update_url);

		if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
			return false;
		}

		$data = json_decode(wp_remote_retrieve_body($response));

        if (property_exists($data, 'build_type' ) && $data->build_type !== 'production' && wp_get_environment_type() === 'production') {
            return false;
        }

		// Cache the response for 12 hours
		set_transient(BaseConstants::OPTION_AUTOUPDATE_PLUGIN_UPDATE_INFO, $data, 12 * self::HOUR_IN_SECONDS);

		return $data;
	}

    /**
     * Ensures the plugin version in wp_options matches the actual plugin version.
     * This acts as a safety net for cases where update hooks might not fire.
     *
     * @return void
     */
    private function ensurePluginVersionSync(): void {
        try {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . plugin_basename(RANKINGCOACH_PLUGIN_BASENAME), false, false);
            $current_version = $plugin_data['Version'] ?? '';
            $stored_version = get_option(BaseConstants::OPTION_PLUGIN_VERSION, '');

            if (!empty($current_version) && $stored_version !== $current_version) {
                $result = update_option(BaseConstants::OPTION_PLUGIN_VERSION, $current_version);
                if (!$result && get_option(BaseConstants::OPTION_PLUGIN_VERSION) !== $current_version) {
                    $this->log("Failed to update plugin version option to '{$current_version}'", 'ERROR');
                }
            }
        } catch (Exception $e) {
            $this->log('Error ensuring plugin version sync: ' . $e->getMessage(), 'ERROR');
        }
    }

    /**
     * Syncs the plugin version to the database after a plugin update.
     * Includes validation, error handling, and only writes if version changed.
     *
     * @param WP_Upgrader $upgrader The upgrader instance
     * @param array $options Update options
     * @return void
     */
    public function syncPluginVersionOnUpdate(WP_Upgrader $upgrader, array $options = []): void {
        // Only process plugin updates
        if ($options['action'] !== 'update' || $options['type'] !== 'plugin') {
            return;
        }

        // Check if our plugin is being updated
        $plugin_basename = plugin_basename(RANKINGCOACH_PLUGIN_BASENAME);
        if (!isset($options['plugins']) || !in_array($plugin_basename, $options['plugins'], true)) {
            return;
        }

        try {
            // Get the current version from plugin header
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_basename, false, false);
            $current_version = $plugin_data['Version'] ?? '';

            if (empty($current_version)) {
                $this->log('Could not determine plugin version after update', 'WARNING');
                return;
            }

            // Get the stored version
            $stored_version = get_option(BaseConstants::OPTION_PLUGIN_VERSION, '');

            // Update if versions don't match
            if ($stored_version !== $current_version) {
                $result = update_option(BaseConstants::OPTION_PLUGIN_VERSION, $current_version);
                if (!$result && get_option(BaseConstants::OPTION_PLUGIN_VERSION) !== $current_version) {
                    $this->log("Failed to save version '{$current_version}'", 'ERROR');
                }
            }

		} catch (Exception $e) {
			$this->log('Error syncing plugin version after update: ' . $e->getMessage(), 'ERROR');
		}
	}

	/**
	 * Get plugin icons for the update page
	 *
	 * @return array Array of icon URLs
	 */
	private function getPluginIcons(?object $remote_info = null): array {
		try {
			$icon_url = plugins_url('inc/Core/Admin/assets/icons/rC-color-whistle.svg', $this->plugin_file);
			
			return [
				'svg' => $icon_url,
				'1x' => $icon_url,
				'2x' => $icon_url,
				'default' => $icon_url
			];
		} catch (Exception $e) {
			$this->log('Error loading plugin icons: ' . $e->getMessage(), 'WARNING');
			return [];
		}
	}
}
