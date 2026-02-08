<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Sitemap;

if ( !defined('ABSPATH') ) {
    exit;
}

use RankingCoach\Inc\Core\Settings\SettingsManager;

/**
 * Handles our sitemaps.
 */
class Sitemap {
    public function __construct() {
        $this->disableSitemap();
        $this->registerCleanupHooks();
    }

    /**
     * Register cleanup hooks for plugin deactivation
     */
    private function registerCleanupHooks(): void
    {
        register_deactivation_hook(__FILE__, function() {
            // Clean up rewrite rules
            delete_option('rankingcoach_sitemap_initialized');
            delete_option('rankingcoach_sitemap_trailing_slash_fixed');
            delete_option('rankingcoach_flush_rewrite_rules');

            // Re-enable WordPress core sitemap
            remove_filter('wp_sitemaps_enabled', '__return_false');

            // Remove generated sitemap files
            $upload_dir = wp_upload_dir();

            if (!empty($upload_dir['error'])) {
                return;
            }

            if (!function_exists('WP_Filesystem')) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }

            if (!WP_Filesystem()) {
                return;
            }

            global $wp_filesystem;

            $pattern = trailingslashit($upload_dir['basedir']) . 'sitemap-*.xml';
            $sitemap_files = glob($pattern);

            if ($sitemap_files) {
                foreach ($sitemap_files as $file) {
                    if (
                        $wp_filesystem->exists($file) &&
                        $wp_filesystem->is_writable($file)
                    ) {
                        $wp_filesystem->delete($file);
                    }
                }
            }

            // Flush rewrite rules to clean up
            flush_rewrite_rules();
        });
    }

    /**
     * Initializes the sitemap functionality.
     */
    public function init(): void
    {
        // Schedule sitemap regeneration
        add_action('rankingcoach_static_sitemap_regeneration', function () {
            (new Generator())->generate();
        });

        // Regenerate sitemap when a post is published
        add_action('wp_insert_post', function ($post_id, $post) {
            // Only regenerate for published posts
            if ($post->post_status === 'publish' && SettingsManager::instance()->sitemap->enabled) {
                (new Generator())->generate();
            }
        }, 10, 2);

        // Prevent WordPress from adding trailing slashes to sitemap.xml
        add_filter('redirect_canonical', function($redirect_url, $requested_url) {
            if (preg_match('/sitemap\.xml$/', $requested_url)) {
                return false;
            }
            return $redirect_url;
        }, 10, 2);

        // Add rewrite rule for sitemap.xml
        add_action('init', function() {
            $sitemap_rule = 'sitemap\.xml';

            // Handle multisite subdirectory installations
            if (is_multisite() && !is_subdomain_install()) {
                $current_blog = get_blog_details();
                if ($current_blog && $current_blog->path !== '/') {
                    $sitemap_rule = trim($current_blog->path, '/') . '/sitemap\.xml';
                }
            }

            // Handle both with and without trailing slash
            add_rewrite_rule('^' . $sitemap_rule . '$', 'index.php?rankingcoach_sitemap=general', 'top');
            add_rewrite_rule('^' . $sitemap_rule . '/$', 'index.php?rankingcoach_sitemap=general', 'top');
            add_rewrite_tag('%rankingcoach_sitemap%', '([^&]+)');

            // Check if we need to flush rewrite rules
            if (get_option('rankingcoach_flush_rewrite_rules', false)) {
                flush_rewrite_rules();
                delete_option('rankingcoach_flush_rewrite_rules');
            }
        });

        // Set flag to flush rewrite rules once
        if (!get_option('rankingcoach_sitemap_initialized', false)) {
            update_option('rankingcoach_flush_rewrite_rules', true);
            update_option('rankingcoach_sitemap_initialized', true);
        }

        // Force flush rewrite rules once after plugin update to apply new sitemap rules
        if (!get_option('rankingcoach_sitemap_trailing_slash_fixed', false)) {
            update_option('rankingcoach_flush_rewrite_rules', true);
            update_option('rankingcoach_sitemap_trailing_slash_fixed', true);
        }

        // Handle sitemap requests
        add_action('template_redirect', function() {
            global $wp_query;

            if (isset($wp_query->query_vars['rankingcoach_sitemap'])) {
                $type = $wp_query->query_vars['rankingcoach_sitemap'];

                // Generate sitemap if it doesn't exist
                $upload_dir = wp_upload_dir();
                $sitemap_path = trailingslashit($upload_dir['basedir']) . "sitemap-$type.xml";

                if (!file_exists($sitemap_path)) {
                    $xml = (new Generator())->generate($type);
                } else {
                    $xml = file_get_contents($sitemap_path);
                }

                // Output the sitemap
                header('Content-Type: application/xml; charset=UTF-8');
                if ($xml && preg_match('/<\?xml/i', $xml)) {
                    // Only output if it's valid XML (basic check)
                    echo wp_kses($xml, [
                        'urlset' => ['xmlns' => true],
                        'url' => [],
                        'loc' => [],
                        'lastmod' => [],
                        'changefreq' => [],
                        'priority' => [],
                        'sitemap' => [],
                        'sitemapindex' => ['xmlns' => true]
                    ]);
                } else {
                    // Log error or handle invalid XML
                    error_log('Invalid sitemap XML detected');
                    echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';
                }
                exit;
            }
        });
    }

    /**
     * Checks if sitemap exists (either as physical file or via rewrite rules)
     */
    public static function sitemapExists(string $url): bool
    {
        // Check if our custom sitemap is enabled
        if (SettingsManager::instance()->sitemap->enabled) {
            // Parse URL to check if it's requesting sitemap.xml
            $parsed = wp_parse_url($url);
            $path = $parsed['path'] ?? '';
            
            // Handle multisite subdirectory installations
            if (is_multisite() && !is_subdomain_install()) {
                $current_blog = get_blog_details();
                if ($current_blog && $current_blog->path !== '/') {
                    $expected_path = rtrim($current_blog->path, '/') . '/sitemap.xml';
                } else {
                    $expected_path = '/sitemap.xml';
                }
            } else {
                $expected_path = '/sitemap.xml';
            }
            
            // Check if URL matches our sitemap path
            if ($path === $expected_path) {
                return true;
            }
        }
        
        // Check if physical file exists
        $upload_dir = wp_upload_dir();
        $sitemap_path = trailingslashit($upload_dir['basedir']) . 'sitemap-general.xml';
        
        return file_exists($sitemap_path);
    }

    /**
     * Disables the WordPress core sitemap if our sitemap is enabled.
     *
     * This prevents conflicts between the core sitemap and our custom sitemap.
     */
    protected function disableSitemap(): void
    {
        // Only disable WordPress core sitemap if our sitemap IS enabled
        // This fixes the critical logic error - was inverted before
        if (SettingsManager::instance()->sitemap->enabled) {
            remove_action('init', 'wp_sitemaps_get_server');
            add_filter('wp_sitemaps_enabled', '__return_false');
        }
    }
}
