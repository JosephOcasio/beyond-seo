<?php
declare(strict_types=1);

/*
Plugin Name: Plugin Install Timings
Description: Tracks plugin upload and install durations with accurate timing measurements.
Version: 5.0
Author: Romeo Tamas
*/

namespace RankingCoach\Inc\Core\Plugin;

if (!defined('ABSPATH')) exit;

use JetBrains\PhpStorm\NoReturn;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use WP_Upgrader;

/**
 * Class PluginUploadMonitor
 *
 * Tracks and logs plugin upload and installation times to help diagnose performance issues.
 * Provides an admin interface to view timing data for recent plugin installations.
 */
class PluginUploadMonitor
{
    private static string $option_key = 'plugin_install_timings';
    private static string $session_key = 'plugin_upload_timing_data';

    /**
     * Constructor - sets up hooks and initializes the plugin
     */
    public function __construct()
    {
        // Initialize session if not started
        if (!session_id() && !headers_sent()) {
            session_start();
        }

        // Add JS to track when upload starts (before the actual HTTP request begins)
        add_action('admin_footer', [$this, 'add_upload_timing_script']);

        // Track when a plugin upload form is submitted
        add_action('admin_init', [$this, 'track_upload_form'], 1);

        // Track when installation is complete
        add_action('upgrader_process_complete', [$this, 'log_install_data'], 9999, 2);

        // Admin page
        add_action('admin_menu', [$this, 'add_admin_page']);

        // AJAX handlers for JavaScript timing
        add_action('wp_ajax_rc_record_upload_start', [$this, 'record_upload_start']);
    }

    /**
     * Adds JavaScript to track when a plugin upload begins
     *
     * @return void
     */
    public function add_upload_timing_script(): void
    {
        // Only add on plugin install pages
        $request_uri = WordpressHelpers::sanitize_input( 'SERVER', 'REQUEST_URI' );

        if (
                ! str_contains( $request_uri, 'plugin-install.php' ) &&
                ! str_contains( $request_uri, 'update.php' )
        ) {
            return;
        }

        ?>
        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function () {
                // Target the plugin upload form
                const form = document.querySelector('form.wp-upload-form');
                if (!form) return;

                form.addEventListener('submit', function () {
                    const fileInput = form.querySelector('input[name="pluginzip"]');
                    if (!fileInput || !fileInput.files.length) return;

                    // Record start time and file info
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    const data = {
                        action: 'rc_record_upload_start',
                        filename: fileInput.files[0].name,
                        filesize: fileInput.files[0].size,
                        start_time: Math.floor(Date.now() / 1000),
                        nonce: '<?php
                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            echo wp_create_nonce('plugin_upload_timing');
                        ?>'
                    };

                    // Use navigator.sendBeacon for more reliable delivery
                    if (navigator.sendBeacon) {
                        // Convert data to URLSearchParams for sendBeacon
                        const formData = new FormData();
                        Object.keys(data).forEach(function (key) {
                            formData.append(key, data[key]);
                        });
                        navigator.sendBeacon(ajaxurl, formData);
                    } else {
                        // Fallback to fetch API
                        fetch(ajaxurl, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: new URLSearchParams(data)
                        });
                    }
                });
            });
        </script>
        <?php
    }

    /**
     * AJAX handler for recording upload start time
     * This method terminates execution with wp_die()
     *
     * @return void
     */
    #[NoReturn]
    public function record_upload_start(): void
    {
        check_ajax_referer('plugin_upload_timing', 'nonce');

        if (!current_user_can('install_plugins')) {
            wp_die('Unauthorized', 403);
        }

        // Store in session to persist across requests
        $filename = WordpressHelpers::sanitize_input( 'POST', 'filename' ) ?: 'unknown.zip';

        $filesize = WordpressHelpers::sanitize_input( 'POST', 'filesize', return: 'int');
        $filesize = $filesize ?? 0;

        $start_time = WordpressHelpers::sanitize_input( 'POST', 'start_time' );
        $start_time = $start_time ?? time();

        $_SESSION[ self::$session_key ] = [
                'filename'   => sanitize_file_name( $filename ),
                'filesize'   => absint( $filesize ),
                'start_time' => absint( $start_time ),
        ];

        // Also store as a transient as backup
        if (isset($_SESSION[ self::$session_key ])) {
            set_transient('plugin_upload_' . get_current_user_id(), $_SESSION[self::$session_key], HOUR_IN_SECONDS);
        }

        wp_die();
    }

    /**
     * Tracks when a plugin upload form is submitted
     *
     * @return void
     */
    public function track_upload_form(): void
    {
        $request_uri = WordpressHelpers::sanitize_input( 'SERVER', 'REQUEST_URI' );

        if (
            is_admin() &&
            isset($_FILES['pluginzip']) &&
            str_contains($request_uri, 'update.php?action=upload-plugin')
        ) {
            // Verify nonce from WordPress plugin upload form
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'plugin-upload')) {
                return;
            }

            // Sanitize file data
            $filename = isset($_FILES['pluginzip']['name']) ? sanitize_file_name(wp_unslash($_FILES['pluginzip']['name'])) : 'unknown.zip';
            $filesize = isset($_FILES['pluginzip']['size']) ? absint($_FILES['pluginzip']['size']) : 0;

            // If we don't have session data, create a backup
            if (empty($_SESSION[self::$session_key])) {
                $_SESSION[self::$session_key] = [
                    'filename' => $filename,
                    'filesize' => $filesize,
                    'start_time' => time() - 1
                ];
            }

            // Mark when PHP finished receiving the upload
            if (isset($_SESSION[self::$session_key])) {
                $_SESSION[self::$session_key]['received_time'] = microtime(true);
            }
        }
    }

    /**
     * Logs installation data when a plugin installation is complete
     *
     * @param WP_Upgrader $upgrader The WP_Upgrader instance (not used but required by the hook)
     * @param array $hook_extra Extra data about the installation
     * @return void
     */
    public function log_install_data(WP_Upgrader $upgrader, array $hook_extra = []): void
    {
        if (
            isset($hook_extra['type'], $hook_extra['action']) &&
            $hook_extra['type'] === 'plugin' &&
            in_array($hook_extra['action'], ['install', 'update'])
        ) {
            // Get timing data from session or transient
            $timing_data = isset($_SESSION[self::$session_key]) ? $_SESSION[self::$session_key] : null;

            if (empty($timing_data)) {
                // Try transient as backup
                $timing_data = get_transient('plugin_upload_' . get_current_user_id());
            }

            // Record completion time
            $completion_time = microtime(true);

            if (!empty($timing_data)) {
                $plugin_name = $timing_data['filename'] ?? ($hook_extra['plugin'] ?? 'unknown');
                $start_time = $timing_data['start_time'];
                $received_time = $timing_data['received_time'] ?? ($start_time + ($completion_time - $start_time) * 0.7);

                // Calculate timings
                $upload_time = round($received_time - $start_time, 2);
                $unarchive_time = round($completion_time - $received_time, 2);
                $total_time = round($completion_time - $start_time, 2);

                // Save the data
                $entry = [
                    'plugin' => $plugin_name,
                    'upload_time' => $upload_time,
                    'unarchive_time' => $unarchive_time,
                    'total_time' => $total_time,
                    'filesize' => $timing_data['filesize'] ?? 0,
                    'date' => current_time('mysql'),
                ];

                $data = get_option(self::$option_key, []);
                array_unshift($data, $entry);
                update_option(self::$option_key, array_slice($data, 0, 50));

                // Clean up
                unset($_SESSION[self::$session_key]);
                delete_transient('plugin_upload_' . get_current_user_id());
            }
        }
    }

    /**
     * Adds the admin page to the WordPress menu
     *
     * @return void
     */
    public function add_admin_page(): void
    {
        add_submenu_page(
            'plugins.php',
            'Plugin Install Timings',
            'Install Timings',
            'manage_options',
            'plugin-install-timings',
            [$this, 'render_admin_page']
        );
    }

    /**
     * Renders the admin page with timing data
     *
     * @return void
     */
    public function render_admin_page(): void
    {
        $clear_log = WordpressHelpers::sanitize_input( 'GET', 'clear_plugin_install_log' );
        $nonce     = WordpressHelpers::sanitize_input( 'GET', '_wpnonce' );

        if (! empty( $clear_log ) && ! empty( $nonce ) &&
                current_user_can( 'manage_options' ) &&
                wp_verify_nonce( $nonce, 'clear_plugin_install_log' )
        ) {
            delete_option( self::$option_key );
            echo '<div class="notice notice-success is-dismissible"><p>Log cleared.</p></div>';
        }

        $data = get_option(self::$option_key, []);
        echo '<div class="wrap"><h1>Plugin Install Timings</h1>';

        $clear_url = add_query_arg([
            'page' => 'plugin-install-timings',
            'clear_plugin_install_log' => '1',
            '_wpnonce' => wp_create_nonce('clear_plugin_install_log')
        ], admin_url('plugins.php'));

        echo '<p><a href="' . esc_url($clear_url) . '" class="button button-secondary" onclick="return confirm(\'Clear the log?\')">Clear Log</a></p>';

        if (empty($data)) {
            echo '<p>No plugin installs tracked yet.</p>';
        } else {
            echo '<table class="widefat fixed striped">';
            echo '<thead><tr>
                <th>Plugin</th>
                <th>Upload Time</th>
                <th>Unarchive Time</th>
                <th>Total Time</th>
                <th>File Size</th>
                <th>Date</th>
            </tr></thead><tbody>';

            foreach ($data as $row) {
                $filesize = isset($row['filesize']) && $row['filesize'] > 0
                    ? $this->format_filesize($row['filesize'])
                    : '—';

                echo '<tr>';
                echo '<td>' . esc_html($row['plugin']) . '</td>';
                echo '<td>' . (isset($row['upload_time']) ? esc_html($row['upload_time']) . 's' : '—') . '</td>';
                echo '<td>' . (isset($row['unarchive_time']) ? esc_html($row['unarchive_time']) . 's' : '—') . '</td>';
                echo '<td>' . (isset($row['total_time']) ? esc_html($row['total_time']) . 's' : '—') . '</td>';
                echo '<td>' . esc_html($filesize) . '</td>';
                echo '<td>' . esc_html($row['date']) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }

        echo '</div>';
    }

    /**
     * Formats a filesize in bytes to a human-readable format
     *
     * @param int $bytes The size in bytes
     * @return string The formatted filesize
     */
    private function format_filesize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }
}

// Initialize the plugin
new PluginUploadMonitor();
