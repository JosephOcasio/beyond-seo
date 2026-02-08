<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core;

use RankingCoach\Inc\Core\Helpers\CoreHelper;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use Throwable;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * HealthManager (public-safe)
 *
 * Reduced payload automatically in production; full (safe) payload elsewhere.
 */
class HealthManager
{
    /**
     * Convert PHP ini size (e.g. "256M") to bytes.
     */
    private static function iniSizeToBytes(?string $val): ?int
    {
        if ($val === null || $val === '') {
            return null;
        }
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        $n = (int)$val;
        switch ($last) {
            case 'g': return $n * 1024 * 1024 * 1024;
            case 'm': return $n * 1024 * 1024;
            case 'k': return $n * 1024;
            default:  return (int)$val;
        }
    }

    /**
     * Safe get option helper (returns null if not available).
     */
    private static function getOptionSafe(string $key)
    {
        return function_exists('get_option') ? get_option($key) : null;
    }

    /**
     * Coarsen a version string: "8.2.28" -> "8.2.x"; "1.2" -> "1.x".
     */
    private static function coarseVersion(?string $v): ?string
    {
        if (!$v) return null;
        $parts = preg_split('/[^\d]+/', $v);
        $parts = array_values(array_filter($parts, static fn($p) => $p !== ''));
        if (count($parts) >= 2) {
            return $parts[0] . '.' . $parts[1] . '.x';
        }
        if (count($parts) === 1) {
            return $parts[0] . '.x';
        }
        return null;
    }

    /**
     * Server software family: "Apache/2.4.62 (Debian)" -> "Apache"
     */
    private static function serverFamily(?string $soft): ?string
    {
        if (!$soft) return null;
        $family = trim(explode('/', $soft, 2)[0]);
        return $family !== '' ? $family : null;
    }

    /**
     * Build a comprehensive health-check payload for diagnostics.
     * Reduced automatically in production.
     * @throws Throwable
     */
    public static function selfCheckPublic(): array
    {
        // Ensure WP helpers
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if (!function_exists('wp_get_theme')) {
            require_once ABSPATH . 'wp-includes/theme.php';
        }

        // Env type
        $envType = function_exists('wp_get_environment_type') ? wp_get_environment_type() : null;
        $isProd  = ($envType === 'production');

        // Plugin meta
        $pluginFile = defined('RANKINGCOACH_FILE') ? RANKINGCOACH_FILE : null;
        $pluginData = $pluginFile ? get_plugin_data($pluginFile, false, false) : [];
        $requiresWp  = $pluginData['RequiresWP']  ?? null;
        $requiresPhp = $pluginData['RequiresPHP'] ?? null;

        // WP + env
        global $wp_version;
        $wpVersion = $wp_version ?? (function_exists('get_bloginfo') ? get_bloginfo('version') : null);

        $pluginBasename   = defined('RANKINGCOACH_PLUGIN_BASENAME') ? RANKINGCOACH_PLUGIN_BASENAME : ($pluginFile ? plugin_basename($pluginFile) : null);
        $isActive         = $pluginBasename && function_exists('is_plugin_active') ? is_plugin_active($pluginBasename) : null;
        $isNetworkActive  = $pluginBasename && function_exists('is_plugin_active_for_network') ? is_plugin_active_for_network($pluginBasename) : null;

        // Theme (safe, no PII)
        $themeInfo = null;
        if (function_exists('wp_get_theme')) {
            $theme = wp_get_theme();
            if ($theme && $theme->exists()) {
                $parent = $theme->parent();
                $themeInfo = [
                    'name'         => $theme->get('Name'),
                    'version'      => $theme->get('Version'),
                    'template'     => $theme->get_template(),
                    'stylesheet'   => $theme->get_stylesheet(),
                    'is_child'     => (bool)$parent,
                    'parent_name'  => $parent ? $parent->get('Name') : null,
                    'parent_ver'   => $parent ? $parent->get('Version') : null,
                ];
            }
        }

        // Locale
        $locale = function_exists('get_locale') ? get_locale() : null;

        // WP Memory constants
        $wpMemoryLimit     = defined('WP_MEMORY_LIMIT') ? WP_MEMORY_LIMIT : null;
        $wpMaxMemoryLimit  = defined('WP_MAX_MEMORY_LIMIT') ? WP_MAX_MEMORY_LIMIT : null;

        // PHP ini limits
        $ini_memory_limit      = self::iniSizeToBytes(@ini_get('memory_limit') ?: null);
        $ini_max_execution     = is_numeric(@ini_get('max_execution_time')) ? (int)@ini_get('max_execution_time') : null;
        $ini_upload_max_files  = self::iniSizeToBytes(@ini_get('upload_max_filesize') ?: null);
        $ini_post_max_size     = self::iniSizeToBytes(@ini_get('post_max_size') ?: null);

        // Memory runtime
        $mem_usage = function_exists('memory_get_usage') ? @memory_get_usage(true) : null;
        $mem_peak  = function_exists('memory_get_peak_usage') ? @memory_get_peak_usage(true) : null;

        // Timezone & OPcache
        $tz = @date_default_timezone_get();
        $opcacheEnabled     = function_exists('ini_get') ? (bool) @ini_get('opcache.enable') : null;
        $opcacheEnabledCLI  = function_exists('ini_get') ? (bool) @ini_get('opcache.enable_cli') : null;

        // Log dir
        $logDir = defined('RANKINGCOACH_LOG_DIR') ? RANKINGCOACH_LOG_DIR : null;
        $logExists = $logDir ? is_dir($logDir) : false;
        $logWritable = $logDir ? wp_is_writable($logDir) : false;
        $logStats = [
            'files' => 0,
            'total_size_bytes' => 0,
            'latest_modified_at' => null,
        ];
        if ($logExists) {
            $latestMTime = null;
            $entries = @scandir($logDir) ?: [];
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') continue;
                $path = $logDir . $entry;
                if (is_file($path)) {
                    $logStats['files']++;
                    $size = @filesize($path);
                    if (is_int($size)) $logStats['total_size_bytes'] += $size;
                    $mtime = @filemtime($path);
                    if ($mtime && (!$latestMTime || $mtime > $latestMTime)) $latestMTime = $mtime;
                }
            }
            if ($latestMTime) $logStats['latest_modified_at'] = gmdate('c', $latestMTime);
        }

        // REST bases & URLs
        $legacyBase = defined('RANKINGCOACH_REST_API_BASE') ? RANKINGCOACH_REST_API_BASE : 'rankingcoach/seo';
        $appBase    = defined('RANKINGCOACH_REST_APP_BASE') ? RANKINGCOACH_REST_APP_BASE : 'rankingcoach/api';
        $healthUrl  = function_exists('rest_url') ? rest_url($legacyBase . '/self_check_public') : null;
        $ajaxUrl    = function_exists('admin_url') ? admin_url('admin-ajax.php') : null;
        $wpCronUrl  = function_exists('site_url') ? (trailingslashit(site_url()) . 'wp-cron.php') : null;

        // Permalinks
        $permalinkStructure = self::getOptionSafe('permalink_structure'); // null/'' => plain
        $permalinksPretty   = is_string($permalinkStructure) && $permalinkStructure !== '';

        // Cron config flag
        $alternateWpCron = defined('ALTERNATE_WP_CRON') ? (bool) ALTERNATE_WP_CRON : null;

        // DB snapshot
        global $wpdb;
        $dbDriver = null;
        if (isset($wpdb->use_mysqli)) {
            $dbDriver = $wpdb->use_mysqli ? 'mysqli' : 'mysql';
        } elseif (extension_loaded('mysqli')) {
            $dbDriver = 'mysqli';
        } elseif (extension_loaded('pdo_mysql')) {
            $dbDriver = 'pdo_mysql';
        }
        $tablePrefixLen = isset($wpdb->prefix) ? strlen((string) $wpdb->prefix) : null;
        $tablePrefixOk  = null;
        if ($tablePrefixLen !== null) {
            $tablePrefixOk = ($tablePrefixLen <= 32) && (preg_match('/^[A-Za-z0-9_]+$/', (string) $wpdb->prefix) === 1);
        }
        $dbInfo = [
            'driver'   => $dbDriver,
            'prefix'   => [
                'length' => $tablePrefixLen,
                'ok'     => $tablePrefixOk,
            ],
            'charset'  => $wpdb->charset ?? null,
            'collate'  => $wpdb->collate ?? null,
        ];

        // Requirements satisfaction
        $reqWpOk  = $requiresWp  ? ($wpVersion ? version_compare($wpVersion, $requiresWp, '>=') : null) : null;
        $reqPhpOk = $requiresPhp ? version_compare(PHP_VERSION, $requiresPhp, '>=') : null;

        // Cron snapshot
        $cronSummary = [
            'total' => 0,
            'by_hook' => [],
            'next' => [],
            'config'  => [
                'ALTERNATE_WP_CRON' => $alternateWpCron,
            ],
        ];
        if (function_exists('wp_get_ready_cron_jobs')) {
            $jobs = wp_get_ready_cron_jobs();
            foreach ($jobs as $timestamp => $hooks) {
                foreach ($hooks as $hook => $instances) {
                    $cronSummary['total'] += count($instances);
                    $cronSummary['by_hook'][$hook] = ($cronSummary['by_hook'][$hook] ?? 0) + count($instances);
                }
            }
        }
        if (function_exists('_get_cron_array')) {
            $cronArray = _get_cron_array();
            if (is_array($cronArray)) {
                $nextItems = [];
                foreach ($cronArray as $ts => $hooks) {
                    foreach ($hooks as $hook => $instances) {
                        foreach ($instances as $sig => $data) {
                            $nextItems[] = [
                                'hook'        => $hook,
                                'next_run_iso'=> gmdate('c', (int)$ts),
                                'interval'    => isset($data['interval']) ? (int)$data['interval'] : null,
                            ];
                        }
                    }
                }
                usort($nextItems, static function($a, $b) {
                    return strcmp($a['next_run_iso'], $b['next_run_iso']);
                });
                $cronSummary['next'] = array_slice($nextItems, 0, 5);
            }
        }

        // Extensions of interest
        $extensions = ['curl','mbstring','json','dom','libxml','zip','openssl','pdo_mysql','mysqli'];
        $extStatus = [];
        foreach ($extensions as $ext) {
            $extStatus[$ext] = extension_loaded($ext);
        }

        $rankingCoachOptions = [
            'options' => CoreHelper::getOptions('rankingcoach_', [
                'onboarding_made_on_wp_last_update', 'onboarding_made_on_wp', 'onboarding_made_on_rc_last_update', 'onboarding_made_on_rc',
                'onboarding_url', 'subscription', 'max_allowed_keywords',
                'activation_code', 'installation_date', 'onboarding_collect_date',
                'location_id', 'project_id', 'account_id',
            ]),
        ];

        // Full (default) payload
        $full = array_merge($rankingCoachOptions, [
            'plugin' => [
                'name'           => defined('RANKINGCOACH_BRAND_NAME') ? RANKINGCOACH_BRAND_NAME : ($pluginData['Name'] ?? 'rankingCoach'),
                'version'        => defined('RANKINGCOACH_VERSION') ? RANKINGCOACH_VERSION : ($pluginData['Version'] ?? null),
                'basename'       => $pluginBasename,
                'environment'    => defined('RANKINGCOACH_ENVIRONMENT') ? RANKINGCOACH_ENVIRONMENT : $envType,
                'wp_debug'       => defined('RANKINGCOACH_WP_DEBUG') ? (bool)RANKINGCOACH_WP_DEBUG : (defined('WP_DEBUG') ? (bool)WP_DEBUG : null),
                'active'         => $isActive,
                'network_active' => $isNetworkActive,
            ],
            'requirements' => [
                'requires_wp'  => $requiresWp,
                'requires_php' => $requiresPhp,
                'satisfied'    => [
                    'wp'  => $reqWpOk,
                    'php' => $reqPhpOk,
                ],
            ],
            'wp' => [
                'version'     => $wpVersion,
                'multisite'   => function_exists('is_multisite') ? is_multisite() : null,
                'site_url'    => function_exists('site_url') ? site_url() : null,
                'home_url'    => function_exists('home_url') ? home_url() : null,
                'locale'      => $locale,
                'theme'       => $themeInfo,
                'permalinks'  => [
                    'structure' => $permalinkStructure,
                    'pretty'    => $permalinksPretty,
                ],
                'memory'      => [
                    'WP_MEMORY_LIMIT'       => $wpMemoryLimit,
                    'WP_MAX_MEMORY_LIMIT'   => $wpMaxMemoryLimit,
                    'usage_bytes'           => $mem_usage,
                    'peak_bytes'            => $mem_peak,
                ],
                'db' => $dbInfo,
            ],
            'php' => [
                'version'   => PHP_VERSION,
                'sapi'      => PHP_SAPI,
                'extensions'=> $extStatus,
                'ini'       => [
                    'memory_limit_bytes'       => $ini_memory_limit,
                    'max_execution_time_sec'   => $ini_max_execution,
                    'upload_max_filesize_bytes'=> $ini_upload_max_files,
                    'post_max_size_bytes'      => $ini_post_max_size,
                ],
                'timezone' => $tz,
                'opcache'  => [
                    'enable'    => $opcacheEnabled,
                    'enable_cli'=> $opcacheEnabledCLI,
                ],
            ],
            'server' => [
                'software' => WordpressHelpers::sanitize_input('SERVER', 'SERVER_SOFTWARE'),
                'https'    => function_exists('is_ssl') ? is_ssl() : null,
                'admin_ajax_url' => $ajaxUrl,
                'wp_cron_url'    => $wpCronUrl,
            ],
            'filesystem' => [
                'log_dir' => [
                    'path'     => defined('RANKINGCOACH_LOG_DIR') ? RANKINGCOACH_LOG_DIR : null,
                    'exists'   => $logExists,
                    'writable' => $logWritable,
                    'stats'    => $logStats,
                ],
                'plugin_dir' => [
                    'path'     => defined('RANKINGCOACH_PLUGIN_DIR') ? RANKINGCOACH_PLUGIN_DIR : null,
                    'writable' => defined('RANKINGCOACH_PLUGIN_DIR') ? wp_is_writable(RANKINGCOACH_PLUGIN_DIR) : null,
                ],
            ],
            'rest' => [
                'legacy_base'     => $legacyBase,
                'app_base'        => $appBase,
                'health_endpoint' => $healthUrl,
                'cors_enabled'    => defined('RANKINGCOACH_JWT_AUTH_CORS_ENABLE') ? (bool)RANKINGCOACH_JWT_AUTH_CORS_ENABLE : null,
            ],
            'cron' => $cronSummary,
            'timestamp' => gmdate('c'),
        ]);

        // If not production, return full payload
        if (!$isProd) {
            return $full;
        }

        // Reduced payload for production
        $coarseWp     = self::coarseVersion(is_string($wpVersion) ? $wpVersion : null);
        $coarsePhp    = self::coarseVersion(PHP_VERSION);
        $coarsePlugin = self::coarseVersion($full['plugin']['version'] ?? null);
        $themeReduced = null;
        if (is_array($themeInfo)) {
            $themeReduced = [
                'name'     => $themeInfo['name'] ?? null,
                'version'  => self::coarseVersion($themeInfo['version'] ?? null),
                'is_child' => $themeInfo['is_child'] ?? null,
            ];
        }

        return array_merge($rankingCoachOptions, [
            'plugin' => [
                'name'           => $full['plugin']['name'],
                'version'        => $coarsePlugin,
                'active'         => $full['plugin']['active'],
                // intentionally not exposing: basename, environment, wp_debug, network_active
            ],
            'wp' => [
                'version'     => $coarseWp,
                'multisite'   => $full['wp']['multisite'],
                'locale'      => $full['wp']['locale'],
                'theme'       => $themeReduced,
                'permalinks'  => [
                    // expose only pretty flag
                    'pretty' => $full['wp']['permalinks']['pretty'],
                ],
                'memory'      => [
                    'WP_MEMORY_LIMIT'     => $full['wp']['memory']['WP_MEMORY_LIMIT'],
                    'WP_MAX_MEMORY_LIMIT' => $full['wp']['memory']['WP_MAX_MEMORY_LIMIT'],
                ],
                'db' => [
                    'driver'  => $full['wp']['db']['driver'],
                    'charset' => $full['wp']['db']['charset'],
                    'collate' => $full['wp']['db']['collate'],
                    // do not expose prefix length/ok in prod
                ],
                // do not expose site_url/home_url in prod
            ],
            'php' => [
                'version' => $coarsePhp,
                // do not expose: sapi, extensions, ini*, timezone, opcache
            ],
            'server' => [
                'software_family' => self::serverFamily($full['server']['software'] ?? null),
                'https'           => $full['server']['https'],
                // do not expose admin_ajax_url/wp_cron_url in prod
            ],
            'filesystem' => [
                'log_dir' => [
                    'exists'   => $full['filesystem']['log_dir']['exists'],
                    'writable' => $full['filesystem']['log_dir']['writable'],
                    'stats'    => [
                        'files'             => $full['filesystem']['log_dir']['stats']['files'],
                        'total_size_bytes'  => $full['filesystem']['log_dir']['stats']['total_size_bytes'],
                        // omit latest_modified_at if vrei și mai „strict”; îl păstrăm aici
                        'latest_modified_at'=> $full['filesystem']['log_dir']['stats']['latest_modified_at'],
                    ],
                ],
                'plugin_dir' => [
                    'writable' => $full['filesystem']['plugin_dir']['writable'],
                ],
                // do not expose absolute paths in prod
            ],
            'rest' => [
                'legacy_base'  => $legacyBase,
                // do not expose app_base/health_endpoint in prod
            ],
            'cron' => [
                'total'  => $full['cron']['total'],
                'config' => $full['cron']['config'],
                // do not expose by_hook/next in prod
            ],
            'timestamp' => $full['timestamp'],
        ]);
    }
}
