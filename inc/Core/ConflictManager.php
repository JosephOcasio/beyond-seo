<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core;

if ( !defined('ABSPATH') ) {
    exit;
}

use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;

/**
 * Class ConflictManager
 * 
 * Handles detection and resolution of plugin conflicts with rankingCoach.
 */
class ConflictManager {

	/**
	 * Singleton instance of ConflictManager.
	 *
	 * @var ConflictManager|null
	 */
	private static ?ConflictManager $instance = null;

	/**
	 * List of incompatible plugins with their file paths (relative to plugins directory) and human-readable names.
	 *
	 * @var array
	 */
	private array $incompatible_plugins = [
        'wordpress-seo/wp-seo.php' => 'Yoast SEO',
        'seo-by-rank-math/rank-math.php' => 'Rank Math SEO',
        'all-in-one-seo-pack/all_in_one_seo_pack.php' => 'All in One SEO',
        'all-in-one-seo-pack-pro/all_in_one_seo_pack.php' => 'All in One SEO Pro',
        'autodescription/autodescription.php' => 'The SEO Framework',
        'seo-ultimate/seo-ultimate.php' => 'SEO Ultimate',
        'premium-seo-pack/index.php' => 'Premium SEO Pack',
        'squirrly-seo/squirrly.php' => 'Squirrly SEO',
        'seo-press/seopress.php' => 'SEOPress',
        'slim-seo/slim-seo.php' => 'Slim SEO',
        'wp-seo-structured-data-schema/wp-seo-structured-data-schema.php' => 'WP SEO Structured Data Schema',
        'all-in-one-schemaorg-rich-snippets/index.php' => 'All In One Schema.org Rich Snippets'
	];

	/**
	 * ID for the conflict notification.
	 * 
	 * @var string
	 */
	private const NOTIFICATION_ID = 'rankingcoach-plugin-conflicts';

	/**
	 * Returns the singleton instance of ConflictManager.
	 *
	 * @return ConflictManager
	 */
	public static function getInstance(): ConflictManager {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
	
	/**
	 * Check if a simple notification style is enabled.
	 *
	 * @return bool Whether a simple notification style is enabled.
	 */
	private function isSimpleNotificationEnabled(): bool {
	    return (bool) get_option(BaseConstants::OPTION_SIMPLE_CONFLICT_NOTICE, true);
	}

    /**
     * Checks for conflicts with incompatible plugins and displays a notification if any are found.
     */
    public function checkPluginConflicts(): void {
        $conflicting_plugins = $this->getConflictingPlugins();

        if (!empty($conflicting_plugins)) {
            $this->showConflictNotification($conflicting_plugins);
        }
    }

    /**
     * Gets a list of active plugins that conflict with rankingCoach.
     * 
     * @return array Array of conflicting plugins with their file paths as keys and names as values.
     */
    private function getConflictingPlugins(): array {
        $active_plugins = get_option('active_plugins', []);

        return array_filter($this->incompatible_plugins, function ($plugin_path) use ($active_plugins) {
            return in_array($plugin_path, $active_plugins);
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Displays the appropriate conflict notification based on settings.
     * 
     * @param array $conflicting_plugins Array of conflicting plugins.
     */
    private function showConflictNotification(array $conflicting_plugins): void {
        $friendly_names = array_values($conflicting_plugins);
        $deactivate_nonce = wp_create_nonce(BaseConstants::OPTION_DEACTIVATE_CONFLICTING_PLUGINS);
        
        $message = $this->isSimpleNotificationEnabled() 
            ? $this->getSimpleNotificationHtml($friendly_names)
            : $this->getStyledNotificationHtml($friendly_names);
            
        $script = $this->getDeactivationScript($deactivate_nonce);

        // Check if the notification already exists
        $notification_manager = NotificationManager::instance();

        if (!$notification_manager?->has_notification(self::NOTIFICATION_ID)) {
            $notification_manager?->add(
                $message . $script,
                [
                    'id' => self::NOTIFICATION_ID,
                    'type' => Notification::ERROR,
                    'screen' => Notification::SCREEN_ANY,
                    'dismissible' => true,
                    'persistent' => true,
                ]
            );
        }
    }

    /**
     * Generates HTML for a simple notification style.
     * 
     * @param array $plugin_names Names of conflicting plugins.
     * @return string HTML for the simple notification.
     */
    private function getSimpleNotificationHtml(array $plugin_names): string {
        $plugin_list = '<strong>' . implode('</strong>, <strong>', $plugin_names) . '.</strong>';

	    $message = count($plugin_names) > 1
		    /* translators: %1$s: brand name of the plugin, %2$s: list of plugin names */
		    ? sprintf(__('To ensure %1$s works properly, please deactivate the conflicting plugins: %2$s', 'beyond-seo'), RANKINGCOACH_BRAND_NAME, $plugin_list)
		    /* translators: %1$s: brand name of the plugin, %2$s: plugin name */
		    : sprintf(__('To ensure %1$s works properly, please deactivate the conflicting plugin: %2$s', 'beyond-seo'), RANKINGCOACH_BRAND_NAME, $plugin_list);
        
        return '<p>' . $message . 
            ' <a href="#" id="rankingcoach-deactivate-plugins" class="rankingcoach-deactivate-plugins" rel="noopener noreferrer">' . __('Click to deactivate.', 'beyond-seo') . '</a></p>';
    }

    /**
     * Generates HTML for a styled notification.
     * 
     * @param array $plugin_names Names of conflicting plugins.
     * @return string HTML for the styled notification.
     */
    private function getStyledNotificationHtml(array $plugin_names): string {
        $single_plugin = count($plugin_names) === 1 ? $plugin_names[0] : '';
        $content = '';
        
        if (count($plugin_names) > 1) {
            $content = '<ul><li><strong>' . implode('</strong></li><li><strong>', $plugin_names) . '</strong></li></ul>';
        }

        $compatibility_message = count($plugin_names) > 1
            /* translators: %s: brand name of the plugin */
            ? sprintf(__('<strong>%s</strong> is not compatible with the following plugins:', 'beyond-seo'), RANKINGCOACH_BRAND_NAME)
            /* translators: 1: brand name of the plugin, 2: name of the single conflicting plugin */
            : sprintf(__('<strong>%1$s</strong> is not compatible with the following plugin: <strong>%2$s</strong>', 'beyond-seo'), RANKINGCOACH_BRAND_NAME, $single_plugin);
            
        $deactivation_message = count($plugin_names) > 1
            /* translators: %s: brand name of the plugin */
            ? sprintf(__('To ensure %s works properly, please deactivate the conflicting plugins.', 'beyond-seo'), RANKINGCOACH_BRAND_NAME)
            /* translators: %s: brand name of the plugin */
            : sprintf(__('To ensure %s works properly, please deactivate the conflicting plugin.', 'beyond-seo'), RANKINGCOACH_BRAND_NAME);

        return '
            <div class="rankingcoach-conflict-notice">
                <div class="rankingcoach-conflict-icon">⚠️</div>
                <div class="rankingcoach-conflict-content">
                    <h4>' . __('Plugin Conflict Detected', 'beyond-seo') . '</h4>
                    <p>' . $compatibility_message . '</p>
                    ' . $content . '
                    <p>' . $deactivation_message . ' <a href="#"  id="rankingcoach-deactivate-plugins" class="rankingcoach-deactivate-plugins" rel="noopener noreferrer">' . __('Click here to Deactivate.', 'beyond-seo') . '</a></p>
                </div>
            </div>
            <style>
                .rankingcoach-conflict-notice {
                    display: flex;
                    align-items: flex-start;
                    gap: 15px;
                    padding: 16px 16px 0 16px;
                    margin: 10px 0 20px;
                    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
                    border-radius: 6px;
                }
                .rankingcoach-conflict-icon {
                    font-size: 28px;
                    margin-top: 2px;
                }
                .rankingcoach-conflict-content {
                    flex: 1;
                    font-family: sans-serif;
                    color: #333;
                }
                .rankingcoach-conflict-content h4 {
                    margin: 0 0 12px;
                    font-size: 18px;
                    font-weight: 600;
                    color: #d35400;
                }
                .rankingcoach-conflict-content p {
                    margin: 8px 0;
                    line-height: 1.5;
                }
                .rankingcoach-conflict-content ul {
                    margin: 10px 0 12px;
                    padding-left: 20px;
                    list-style: disc;
                }
                .rankingcoach-conflict-content li {
                    margin-bottom: 4px;
                }
            </style>';
    }

    /**
     * Generates the JavaScript for the plugin deactivation functionality.
     * 
     * @param string $nonce Security nonce for the AJAX request.
     * @return string JavaScript code.
     */
    private function getDeactivationScript(string $nonce): string {
        return '<script>window.addEventListener("load", function() {
                    let deactivateBtn = document.querySelector("#rankingcoach-deactivate-plugins");
                    if (deactivateBtn) {
                        deactivateBtn.addEventListener("click", function(event) {
                            event.preventDefault();
                            let httpRequest = new XMLHttpRequest(),
                                postData = "";
                            // Build the data to send in our request
                            postData += "action=' . BaseConstants::OPTION_DEACTIVATE_CONFLICTING_PLUGINS . '";
                            postData += "&nonce=' . $nonce . '";
                            httpRequest.open("POST", "' . admin_url('admin-ajax.php') . '");
                            httpRequest.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                            httpRequest.onerror = function() {
                                window.location.reload();
                            };
                            httpRequest.onload = function() {
                                window.location.reload();
                            };
                            httpRequest.send(postData);
                        });
                    }
                });
            </script>';
    }

    /**
     * Register AJAX handlers for plugin conflict management.
     */
    public function registerAjaxHandlers(): void {
        add_action('wp_ajax_' . BaseConstants::OPTION_DEACTIVATE_CONFLICTING_PLUGINS, [$this, 'deactivateConflictingPlugins']);
    }

    /**
     * AJAX handler to deactivate conflicting plugins.
     */
    public function deactivateConflictingPlugins(): void {
        // Verify nonce for security
        $nonce = WordpressHelpers::sanitize_input('POST', 'nonce');

        if ( ! wp_verify_nonce( $nonce, BaseConstants::OPTION_DEACTIVATE_CONFLICTING_PLUGINS ) ) {
            wp_send_json_error( __( 'Invalid security token.', 'beyond-seo' ) );
            return;
        }

        // Check if the user can deactivate plugins
        if (!current_user_can('activate_plugins')) {
            wp_send_json_error(__('You do not have permission to deactivate plugins.', 'beyond-seo'));
            return;
        }

        $conflicting_plugins = $this->getConflictingPlugins();
        $deactivated = [];

        if (!empty($conflicting_plugins)) {
            foreach ($conflicting_plugins as $plugin_path => $plugin_name) {
                deactivate_plugins($plugin_path);
                $deactivated[] = $plugin_name;
            }

            // Remove the notification after deactivation
            NotificationManager::instance()?->remove_by_id(self::NOTIFICATION_ID);

            wp_send_json_success([
                /* translators: %s: list of deactivated plugin names */
                'message' => sprintf(__('Successfully deactivated: %s', 'beyond-seo'), implode(', ', $deactivated)),
                'deactivated' => $deactivated
            ]);
        } else {
            wp_send_json_error(__('No conflicting plugins found.', 'beyond-seo'));
        }

        wp_die();
    }

    // create  a function the add the conflict notification when the plugin is activated, thinking as the same
    // function will give parameter the plugin name and the plugin path, like the removeConflictNotification
    /**
     * Adds a conflict notification when a plugin is activated.
     *
     * @param string $plugin The plugin file path.
     */
    public function addConflictNotification(string $plugin): void
    {
        // Check if the activated plugin is our plugin
        if ($plugin === RANKINGCOACH_PLUGIN_BASENAME) {
            // When our plugin is activated, check for all conflicts
            $conflicting_plugins = $this->getConflictingPlugins();
            if (!empty($conflicting_plugins)) {
                $this->showConflictNotification($conflicting_plugins);
            }
            return;
        }
        
        // Check if the activated plugin is in the incompatible plugins list
        if (!array_key_exists($plugin, $this->incompatible_plugins)) {
            return;
        }

        // Get all active conflicting plugins
        $conflicting_plugins = $this->getConflictingPlugins();
        
        // If we have conflicting plugins (including the newly activated one)
        if (!empty($conflicting_plugins)) {
            $notification_manager = NotificationManager::instance();
            
            // If notification already exists, update it with the new list
            if ($notification_manager?->has_notification(self::NOTIFICATION_ID)) {
                $notification_manager?->remove_by_id(self::NOTIFICATION_ID);
            }
            
            // Show notification with all conflicting plugins
            $this->showConflictNotification($conflicting_plugins);
        }
    }

    /**
     * Removes or updates the conflict notification when a plugin is deactivated.
     *
     * @param string $plugin The plugin file path.
     */
    public function removeConflictNotification(string $plugin): void
    {
        $notification_manager = NotificationManager::instance();
        
        // If our plugin is being deactivated, remove all conflict notifications
        if ($plugin === RANKINGCOACH_PLUGIN_BASENAME) {
            if ($notification_manager?->has_notification(self::NOTIFICATION_ID)) {
                $notification_manager?->remove_by_id(self::NOTIFICATION_ID);
            }
            return;
        }
        
        // Check if the deactivated plugin is in the incompatible plugins list
        if (!array_key_exists($plugin, $this->incompatible_plugins)) {
            return;
        }

        // Check if we have a notification before proceeding
        if (!$notification_manager?->has_notification(self::NOTIFICATION_ID)) {
            return;
        }
        
        // Get all active plugins before checking conflicts
        $active_plugins = get_option('active_plugins', []);
        
        // Check if the plugin being deactivated is still in the active list
        // (WordPress calls our hook before actually removing it from the active list)
        $plugin_key = array_search($plugin, $active_plugins);
        if ($plugin_key !== false) {
            // Remove the plugin from our temporary active plugins list
            unset($active_plugins[$plugin_key]);
        }
        
        // Check for remaining conflicts after this plugin is deactivated
        $remaining_conflicts = array_filter($this->incompatible_plugins, function($plugin_path) use ($active_plugins) {
            return in_array($plugin_path, $active_plugins);
        }, ARRAY_FILTER_USE_KEY);

        $notification_manager?->remove_by_id(self::NOTIFICATION_ID);
        if (!empty($remaining_conflicts)) {
            // If there are still conflicting plugins active, update the notification
            $this->showConflictNotification($remaining_conflicts);
        }
    }
}
