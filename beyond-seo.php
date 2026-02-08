<?php
declare(strict_types=1);

namespace RankingCoach;

// If this file is called directly, abort.
if ( !defined('ABSPATH') ) {
    exit('Direct access denied.');
}

/**
 * Professional WordPress SEO Plugin
 *
 * This is the main plugin file that initializes the SEO plugin.
 * It defines plugin metadata, sets up necessary constants, and bootstraps the plugin.
 *
 * @link              https://www.rankingcoach.com
 * @package           BeyondSEO
 *
 * @wordpress-plugin
 * Plugin Name:       BeyondSEO
 * Description:       Be found easier in Google. Effectively perform online marketing in WordPress. Assistant on SEO, Listing & Ads for better Google rankings and online visibility, and much more.
 * Version: 1.1.6
 * RcAPI Version:     v1
 * WpAPI Version:     v1
 * Requires at least: 6.2
 * Requires PHP:      8.0
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://www.rankingcoach.com/en-us/wordpress?update
 * Text Domain:       beyond-seo
 * Domain Path:       /languages/
 * Tags:              SEO, Google rankings, marketing, online visibility
 * Requires WP API:   true
 *
 * The plugin is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * The plugin is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License.
 * If not, see https://www.rankingcoach.com/en-us/wordpress.
 */

defined('RANKINGCOACH_FILE')            || define('RANKINGCOACH_FILE', __FILE__);
defined('RANKINGCOACH_DIR')             || define('RANKINGCOACH_DIR', __DIR__);
defined('RANKINGCOACH_PLUGIN_DIR')      || define('RANKINGCOACH_PLUGIN_DIR', RANKINGCOACH_DIR . '/');

/**
 * Include the core plugin class file
 */
require_once RANKINGCOACH_PLUGIN_DIR . 'inc/Core/Plugin/constants.php';
require_once RANKINGCOACH_PLUGIN_DIR . 'inc/Core/Plugin/functions.php';
require_once RANKINGCOACH_PLUGIN_DIR . 'inc/Core/Plugin/safe_polyfills.php';

/**
 * Load the plugin's core functionality
 * This includes the main plugin class and any other necessary files.
*/
use Exception;
use RankingCoach\Inc\Core\Plugin\RankingCoachPlugin;

/**
 * Bootstrap and initialize the plugin
 * 
 * This self-executing anonymous function initializes the plugin and handles any
 * exceptions that might occur during the initialization process.
 */
(function() {
    try {
        // Load plugin requirements data from constants.php via functions.php
        $plugin_data = rcppd(json_decode(RANKINGCOACH_PLUGIN_REQUIRES, true));

        // Validate plugin data
        if (!is_array($plugin_data)) {
            throw new Exception('Failed to read plugin metadata.');
        }

        // Initialize the plugin with the requirements data
        RankingCoachPlugin::instance($plugin_data)->initialize();
    } catch (Exception $e) {
        // Handle and render any exceptions that occur during initialization
        rcren($e);
    }
})();

