<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit();
}

// === Environments ===
defined('RANKINGCOACH_LOCAL_ENVIRONMENT')         || define('RANKINGCOACH_LOCAL_ENVIRONMENT', 'local');
defined('RANKINGCOACH_STAGING_ENVIRONMENT')       || define('RANKINGCOACH_STAGING_ENVIRONMENT', 'staging');
defined('RANKINGCOACH_PRODUCTION_ENVIRONMENT')    || define('RANKINGCOACH_PRODUCTION_ENVIRONMENT', 'production');


// === Environment Detection ===
if ( ! defined('RANKINGCOACH_COMMON_DEV_ENVIRONMENT_HOST') ) {
    // default: https://rankingcoach.com
    // leave it NULL and will use the WP site URL
    define('RANKINGCOACH_COMMON_DEV_ENVIRONMENT_HOST', 'https://www.aradon.ro');
}
if ( ! defined('RANKINGCOACH_COMMON_DEV_ENVIRONMENT_EMAIL') ) {
    // default: romeo.tamas@gmail.com
    // leave it NULL and will use the WP admin email
    define('RANKINGCOACH_COMMON_DEV_ENVIRONMENT_EMAIL', null);
}
// prevent to send localhost or IP addresses on production
if ( ! defined('RANKINGCOACH_PRODUCTION_ENVIRONMENT_HOST') ) {
    define('RANKINGCOACH_PRODUCTION_ENVIRONMENT_HOST', 'https://www.arq.ro');
}

/**
 * Define plugin environment (local, staging, production).
 * Can be overridden in wp-config.php by defining RANKINGCOACH_ENVIRONMENT.
 * Uses EnvironmentDetector which checks:
 * 1. wp-config.php override
 * 2. Environment variables
 * 3. localhost detection
 * 4. Database domain whitelist
 * 5. Defaults to production
 */
if ( ! defined('RANKINGCOACH_ENVIRONMENT') ) {
    require_once RANKINGCOACH_PLUGIN_DIR . 'inc/Core/Environment/EnvironmentDetector.php';
    define('RANKINGCOACH_ENVIRONMENT',
        \RankingCoach\Inc\Core\Environment\EnvironmentDetector::detect()
    );
}

// === Plugin Version ===
// Read the plugin file and extract the version from the header
if( !function_exists('get_plugin_data') ){
    require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
}
$currentVersion = get_plugin_data( WP_PLUGIN_DIR . '/' . plugin_basename(RANKINGCOACH_FILE), false, false)['Version'];

// === Plugin Metadata ===
defined('RANKINGCOACH_VERSION')         || define('RANKINGCOACH_VERSION', $currentVersion);

// Dynamic brand configuration
$brandConfigPath = dirname(RANKINGCOACH_FILE) . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Plugin' . DIRECTORY_SEPARATOR . 'brand-config.json';
$brandName = null;

if (file_exists($brandConfigPath)) {
    $brandConfig = json_decode(file_get_contents($brandConfigPath), true);
    if (json_last_error() === JSON_ERROR_NONE && isset($brandConfig['brand_name'])) {
        $brandName = $brandConfig['brand_name'];
    }
}

// Use fallback from config file if brand_name is not set
if (!$brandName && file_exists($brandConfigPath)) {
    $brandConfig = json_decode(file_get_contents($brandConfigPath), true);
    if (json_last_error() === JSON_ERROR_NONE && isset($brandConfig['fallback_brand_name'])) {
        $brandName = $brandConfig['fallback_brand_name'];
    }
}

if(empty($brandName)) {
    $brandName = 'rankingCoach'; // Default brand name if not set in config
}

defined('RANKINGCOACH_BRAND_NAME')      || define('RANKINGCOACH_BRAND_NAME', $brandName);
defined('RANKINGCOACH_BRAND_SLUG')      || define('RANKINGCOACH_BRAND_SLUG', sanitize_title($brandName));

// Requirements (original preserved as JSON string)
defined('RANKINGCOACH_PLUGIN_REQUIRES') || define('RANKINGCOACH_PLUGIN_REQUIRES', json_encode([
    'WordPress_Requires' => 'Requires at least',
    'PHP_Requires'       => 'Requires PHP'
]));

// === File References ===
if (defined('RANKINGCOACH_FILE')) {
    defined('RANKINGCOACH_FILENAME') || define('RANKINGCOACH_FILENAME', strtolower(pathinfo(RANKINGCOACH_FILE, PATHINFO_FILENAME)));
    defined('RANKINGCOACH_PLUGIN_URL') || define('RANKINGCOACH_PLUGIN_URL', plugin_dir_url(RANKINGCOACH_FILE));
    defined('RANKINGCOACH_PLUGIN_BASENAME') || define('RANKINGCOACH_PLUGIN_BASENAME', plugin_basename(RANKINGCOACH_FILE));
}

if (defined('RANKINGCOACH_FILE')) {
    defined('RANKINGCOACH_PLUGIN_FOLDER_NAME') || define('RANKINGCOACH_PLUGIN_FOLDER_NAME', basename(dirname(RANKINGCOACH_FILE)));
}

// === Namespaces ===
defined('RANKINGCOACH_NAMESPACE')       || define('RANKINGCOACH_NAMESPACE', 'RankingCoach\\');
defined('RANKINGCOACH_INC_NAMESPACE')   || define('RANKINGCOACH_INC_NAMESPACE', RANKINGCOACH_NAMESPACE . 'Inc\\');
defined('RANKINGCOACH_APP_NAMESPACE')   || define('RANKINGCOACH_APP_NAMESPACE', 'App\\');

// === Environment & Debug ===
defined('RANKINGCOACH_ENABLE_LOGGING')  || define('RANKINGCOACH_ENABLE_LOGGING', true);
defined('WP_ENVIRONMENT_TYPE')          || define('WP_ENVIRONMENT_TYPE', RANKINGCOACH_ENVIRONMENT);
defined('RANKINGCOACH_WP_DEBUG')        || define('RANKINGCOACH_WP_DEBUG', (defined('WP_DEBUG') && WP_DEBUG));

// === REST API ===
defined('RANKINGCOACH_REST_API_LEGACY_BASE') || define('RANKINGCOACH_REST_API_LEGACY_BASE', 'seo');
defined('RANKINGCOACH_REST_API_BASE')     || define('RANKINGCOACH_REST_API_BASE', 'rankingcoach/' . RANKINGCOACH_REST_API_LEGACY_BASE);
defined('RANKINGCOACH_REST_API_APP_BASE') || define('RANKINGCOACH_REST_API_APP_BASE', 'api');
defined('RANKINGCOACH_REST_APP_BASE')     || define('RANKINGCOACH_REST_APP_BASE', 'rankingcoach/' . RANKINGCOACH_REST_API_APP_BASE);
defined('RANKINGCOACH_JWT_AUTH_CORS_ENABLE') || define('RANKINGCOACH_JWT_AUTH_CORS_ENABLE', false);

// === Directory Structure (Base) ===
defined('RANKINGCOACH_PLUGIN_INCLUDES_DIR')     || define('RANKINGCOACH_PLUGIN_INCLUDES_DIR', RANKINGCOACH_PLUGIN_DIR . 'inc' . DIRECTORY_SEPARATOR);
defined('RANKINGCOACH_PLUGIN_APPLICATION_DIR')  || define('RANKINGCOACH_PLUGIN_APPLICATION_DIR', RANKINGCOACH_PLUGIN_DIR . 'app' . DIRECTORY_SEPARATOR);
defined('RANKINGCOACH_PLUGIN_APP_DIR')          || define('RANKINGCOACH_PLUGIN_APP_DIR', RANKINGCOACH_PLUGIN_APPLICATION_DIR);
defined('RANKINGCOACH_PLUGIN_REACT_DIR')        || define('RANKINGCOACH_PLUGIN_REACT_DIR', RANKINGCOACH_PLUGIN_DIR . 'react' . DIRECTORY_SEPARATOR);
defined('RANKINGCOACH_PLUGIN_LANGUAGE_DIR')     || define('RANKINGCOACH_PLUGIN_LANGUAGE_DIR', RANKINGCOACH_PLUGIN_DIR . 'languages' . DIRECTORY_SEPARATOR);

// === Directory Structure (Core) ===
defined('RANKINGCOACH_PLUGIN_CORE_DIR')         || define('RANKINGCOACH_PLUGIN_CORE_DIR', RANKINGCOACH_PLUGIN_INCLUDES_DIR . 'Core' . DIRECTORY_SEPARATOR);
defined('RANKINGCOACH_PLUGIN_ADMIN_DIR')        || define('RANKINGCOACH_PLUGIN_ADMIN_DIR', RANKINGCOACH_PLUGIN_CORE_DIR . 'Admin' . DIRECTORY_SEPARATOR);
defined('RANKINGCOACH_PLUGIN_ADMIN_URL')        || define('RANKINGCOACH_PLUGIN_ADMIN_URL', RANKINGCOACH_PLUGIN_URL . 'inc' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Admin' . DIRECTORY_SEPARATOR);
defined('RANKINGCOACH_PLUGIN_CLI_DIR')          || define('RANKINGCOACH_PLUGIN_CLI_DIR', RANKINGCOACH_PLUGIN_INCLUDES_DIR . 'CLI' . DIRECTORY_SEPARATOR);

// === Modules ===
defined('RANKINGCOACH_PLUGIN_MODULES_DIR')          || define('RANKINGCOACH_PLUGIN_MODULES_DIR', RANKINGCOACH_PLUGIN_INCLUDES_DIR . 'Modules' . DIRECTORY_SEPARATOR);
defined('RANKINGCOACH_PLUGIN_MODULES_LIBRARY_DIR')  || define('RANKINGCOACH_PLUGIN_MODULES_LIBRARY_DIR', RANKINGCOACH_PLUGIN_MODULES_DIR . 'ModuleLibrary' . DIRECTORY_SEPARATOR);

// === Logging ===
defined('RANKINGCOACH_LOG_DIR')                     || define('RANKINGCOACH_LOG_DIR', WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'rc-logs' . DIRECTORY_SEPARATOR);
defined('RANKINGCOACH_CACHE_DIR')                   || define('RANKINGCOACH_CACHE_DIR', RANKINGCOACH_PLUGIN_APP_DIR . 'var' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR);
defined('RANKINGCOACH_LOG_VERBOSE')                 || define('RANKINGCOACH_LOG_VERBOSE', RANKINGCOACH_WP_DEBUG && RANKINGCOACH_ENABLE_LOGGING && RANKINGCOACH_ENVIRONMENT !== RANKINGCOACH_PRODUCTION_ENVIRONMENT);

// === Translated Categories Directory ===
defined('RANKINGCOACH_TRANSLATED_CATEGORIES_DIR')   || define('RANKINGCOACH_TRANSLATED_CATEGORIES_DIR', RANKINGCOACH_PLUGIN_APP_DIR . 'var' . DIRECTORY_SEPARATOR . 'translated-categories' . DIRECTORY_SEPARATOR);
defined('RANKINGCOACH_DEFAULT_CATEGORIES_DIR')      || define('RANKINGCOACH_DEFAULT_CATEGORIES_DIR', RANKINGCOACH_PLUGIN_LANGUAGE_DIR . 'categories' . DIRECTORY_SEPARATOR);

// === Capabilities ===
defined('ALLOWED_RANKINGCOACH_PAGES')               || define('ALLOWED_RANKINGCOACH_PAGES', [
    'post-new.php',
    'post.php',
    'page.php',
    'toplevel_page_rankingcoach-main',
    'admin_page_rankingcoach-main',
    RANKINGCOACH_BRAND_SLUG . '_page_rankingcoach-main',
    'admin_page_rankingcoach-settings',
    RANKINGCOACH_BRAND_SLUG . '_page_rankingcoach-settings',
    'admin_page_rankingcoach-activation',
    RANKINGCOACH_BRAND_SLUG . '_page_rankingcoach-activation',
    'admin_page_rankingcoach-registration',
    RANKINGCOACH_BRAND_SLUG . '_page_rankingcoach-registration',
    'admin_page_rankingcoach-onboarding',
    RANKINGCOACH_BRAND_SLUG . '_page_rankingcoach-onboarding',
    'admin_page_rankingcoach-generalSettings',
    RANKINGCOACH_BRAND_SLUG . '_page_rankingcoach-generalSettings',
    'admin_page_rankingcoach-cache',
    RANKINGCOACH_BRAND_SLUG . '_page_rankingcoach-cache',
    'admin_page_rankingcoach-upsell',
    RANKINGCOACH_BRAND_SLUG . '_page_rankingcoach-upsell',
]);
defined('ALLOWED_RANKINGCOACH_CUSTOM_TYPES')               || define('ALLOWED_RANKINGCOACH_CUSTOM_TYPES', [
    'post',
    'page',
    'tribe_events',
]);


/**
 * Enable FlowGuard Debug Panel
 * When enabled, displays a floating button and debug panel showing
 * channel detection and flow state information on admin pages
 */
// === Feature Flags ===
defined('BSEO_FLOW_GUARD_ENABLED') || define('BSEO_FLOW_GUARD_ENABLED', RANKINGCOACH_ENVIRONMENT !== RANKINGCOACH_PRODUCTION_ENVIRONMENT);
