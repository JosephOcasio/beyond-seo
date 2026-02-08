<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core;

if (!defined('ABSPATH')) {
    exit;
}

use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Traits\SingletonManager;
use RankingCoach\Inc\Core\DB\DatabaseManager;
use Throwable;

/**
 * Comprehensive WordPress Cache Management System
 * Implements WordPress best practices for cache handling
 * 
 * @method CacheManager getInstance(): static
 */
class CacheManager
{
    use SingletonManager;
    use RcLoggerTrait;

    /**
     * Cache types supported by the system
     */
    public const CACHE_TYPE_OBJECT = 'object';
    public const CACHE_TYPE_TRANSIENT = 'transient';
    public const CACHE_TYPE_SITE_TRANSIENT = 'site_transient';
    public const CACHE_TYPE_OPCACHE = 'opcache';
    public const CACHE_TYPE_REWRITE = 'rewrite';
    public const CACHE_TYPE_DATABASE = 'database';
    public const CACHE_TYPE_USER_META = 'user_meta';
    public const CACHE_TYPE_POST_META = 'post_meta';
    public const CACHE_TYPE_TERM_META = 'term_meta';

    /**
     * Plugin-specific cache prefixes
     */
    private const PLUGIN_PREFIXES = [
        'rankingcoach_',
        'rc_',
        'rankingcoach-',
        'rc-'
    ];

    /**
     * Cache statistics
     */
    private array $cacheStats = [];

    /**
     * Initialize cache manager
     */
    public function init(): void
    {
        // Hook into WordPress cache clearing events
        add_action('wp_cache_flush', [$this, 'onWordPressCacheFlush']);
        add_action('clean_post_cache', [$this, 'onPostCacheClean'], 10, 2);
        add_action('clean_term_cache', [$this, 'onTermCacheClean'], 10, 2);
        add_action('clean_user_cache', [$this, 'onUserCacheClean']);

        // Schedule periodic cache cleanup
        if (!wp_next_scheduled('rankingcoach_cache_cleanup')) {
            wp_schedule_event(time(), 'daily', 'rankingcoach_cache_cleanup');
        }
        add_action('rankingcoach_cache_cleanup', [$this, 'performScheduledCleanup']);
    }

    /**
     * Clear all plugin-related caches
     *
     * @return array Cache clearing results
     */
    public function clearAllPluginCaches(): array
    {
        $results = [];
        
        try {
            $results['object_cache'] = $this->clearObjectCache();
            $results['transients'] = $this->clearTransients();
            $results['site_transients'] = $this->clearSiteTransients();
            $results['opcache'] = $this->clearOpcache();
            $results['rewrite_rules'] = $this->clearRewriteRules();
            $results['database_cache'] = $this->clearDatabaseCache();
            $results['meta_cache'] = $this->clearMetaCache();
            
            $this->updateCacheStats('full_clear', true);
            $this->log('Successfully cleared all plugin caches');
            
        } catch (Throwable $e) {
            $this->log('Error clearing all caches: ' . $e->getMessage());
            $results['error'] = $e->getMessage();
        }
        
        return $results;
    }

    /**
     * Clear WordPress object cache for plugin data
     *
     * @return bool Success status
     */
    public function clearObjectCache(): bool
    {
        try {
            $cleared = 0;
            
            // Clear specific cache groups if supported
            if (function_exists('wp_cache_flush_group')) {
                $groups = ['options', 'site-options', 'posts', 'terms', 'users'];
                foreach ($groups as $group) {
                    if (wp_cache_flush_group($group)) {
                        $cleared++;
                    }
                }
            }
            
            // Clear individual cache keys
            $cacheKeys = $this->getPluginCacheKeys();
            foreach ($cacheKeys as $key => $group) {
                if (wp_cache_delete($key, $group)) {
                    $cleared++;
                }
            }
            
            // Fallback: flush cache groups instead of entire cache for safety
            if (function_exists('wp_cache_flush_group')) {
                $groups = ['options', 'transient', 'site-transient'];
                foreach ($groups as $group) {
                    if (wp_cache_flush_group($group)) {
                        $cleared++;
                    }
                }
            }
            
            $this->updateCacheStats('object_cache_cleared', $cleared);
            return true;
            
        } catch (Throwable $e) {
            $this->log('Error clearing object cache: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Clear plugin-specific transients
     *
     * @return int Number of transients cleared
     */
    public function clearTransients(): int
    {
        global $wpdb;
        $cleared = 0;
        
        try {
            $dbManager = DatabaseManager::getInstance();
            
            foreach (self::PLUGIN_PREFIXES as $prefix) {
                // Clear regular transients
                $transientPattern = '_transient_' . $prefix . '%';
                $timeoutPattern = '_transient_timeout_' . $prefix . '%';
                
                $result1 = $dbManager->db()
                    ->table($wpdb->options)
                    ->delete()
                    ->whereLike('option_name', $transientPattern)
                    ->get();
                
                $result2 = $dbManager->db()
                    ->table($wpdb->options)
                    ->delete()
                    ->whereLike('option_name', $timeoutPattern)
                    ->get();
                
                $cleared += ((int) $result1 + (int) $result2);
            }
            
            // Clear from object cache
            if (function_exists('wp_cache_flush_group')) {
                wp_cache_flush_group('transient');
            }
            
            $this->updateCacheStats('transients_cleared', $cleared);
            
        } catch (Throwable $e) {
            $this->log('Error clearing transients: ' . $e->getMessage());
        }
        
        return $cleared;
    }

    /**
     * Clear plugin-specific site transients (multisite)
     *
     * @return int Number of site transients cleared
     */
    public function clearSiteTransients(): int
    {
        if (!is_multisite()) {
            return 0;
        }
        
        global $wpdb;
        $cleared = 0;
        
        try {
            $dbManager = DatabaseManager::getInstance();
            
            foreach (self::PLUGIN_PREFIXES as $prefix) {
                $transientPattern = '_site_transient_' . $prefix . '%';
                $timeoutPattern = '_site_transient_timeout_' . $prefix . '%';
                
                $result1 = $dbManager->db()
                    ->table($wpdb->sitemeta)
                    ->delete()
                    ->whereLike('meta_key', $transientPattern)
                    ->get();
                
                $result2 = $dbManager->db()
                    ->table($wpdb->sitemeta)
                    ->delete()
                    ->whereLike('meta_key', $timeoutPattern)
                    ->get();
                
                $cleared += ((int) $result1 + (int) $result2);
            }
            
            // Clear from object cache
            if (function_exists('wp_cache_flush_group')) {
                wp_cache_flush_group('site-transient');
            }
            
            $this->updateCacheStats('site_transients_cleared', $cleared);
            
        } catch (Throwable $e) {
            $this->log('Error clearing site transients: ' . $e->getMessage());
        }
        
        return $cleared;
    }

    /**
     * Clear OPcache if available
     *
     * @return bool Success status
     */
    public function clearOpcache(): bool
    {
        try {
            if (function_exists('opcache_reset') && opcache_get_status(false)['opcache_enabled']) {
                opcache_reset();
                $this->updateCacheStats('opcache_cleared', true);
                return true;
            }
            return false;
            
        } catch (Throwable $e) {
            $this->log('Error clearing OPcache: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Clear WordPress rewrite rules cache
     *
     * @return bool Success status
     */
    public function clearRewriteRules(): bool
    {
        try {
            flush_rewrite_rules(false);
            $this->updateCacheStats('rewrite_rules_cleared', true);
            return true;
            
        } catch (Throwable $e) {
            $this->log('Error clearing rewrite rules: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Clear database query cache
     *
     * Avoids RESET QUERY CACHE on MySQL 8+ or when privileges are missing.
     * Suppresses DB errors for this operation to prevent log noise.
     *
     * @return bool Success status
     */
    public function clearDatabaseCache(): bool
    {
        global $wpdb;
        
        try {
            $dbManager = DatabaseManager::getInstance();
            
            // Clear WordPress database cache (safe)
            if (method_exists($wpdb, 'flush')) {
                $wpdb->flush();
            }

            // Attempt to clear MySQL query cache only when supported and enabled (MySQL < 8)
            $version = (string) $dbManager->db()->get_var('SELECT VERSION()');
            $major = (int) $version;
            $supportsQueryCache = ($major > 0 && $major < 8);

            if ($supportsQueryCache) {
                // Check if query cache is enabled and has size
                // Use @@ variables to get values directly
                $type = (string) $dbManager->db()->get_var('SELECT @@query_cache_type');
                $size = (int) $dbManager->db()->get_var('SELECT @@query_cache_size');

                if ($type && strtoupper($type) !== 'OFF' && $size > 0) {
                    // Suppress errors to avoid RELOAD privilege error logs
                    $prevSuppress = $wpdb->suppress_errors(true);
                    try {
                        $dbManager->queryRaw('RESET QUERY CACHE');
                    } finally {
                        $wpdb->suppress_errors($prevSuppress);
                    }
                }
            }
            
            $this->updateCacheStats('database_cache_cleared', true);
            return true;
            
        } catch (Throwable $e) {
            $this->log('Error clearing database cache: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Clear plugin-specific meta caches
     *
     * @return int Number of meta entries cleared
     */
    public function clearMetaCache(): int
    {
        $cleared = 0;
        
        try {
            // Clear user meta cache
            if (function_exists('wp_cache_flush_group')) {
                wp_cache_flush_group('user_meta');
                wp_cache_flush_group('usermeta');
                $cleared++;
            }
            
            // Clear post meta cache
            if (function_exists('wp_cache_flush_group')) {
                wp_cache_flush_group('post_meta');
                wp_cache_flush_group('postmeta');
                $cleared++;
            }
            
            // Clear term meta cache
            if (function_exists('wp_cache_flush_group')) {
                wp_cache_flush_group('term_meta');
                wp_cache_flush_group('termmeta');
                $cleared++;
            }
            
            $this->updateCacheStats('meta_cache_cleared', $cleared);
            
        } catch (Throwable $e) {
            $this->log('Error clearing meta cache: ' . $e->getMessage());
        }
        
        return $cleared;
    }

    /**
     * Get cache statistics
     *
     * @return array Cache statistics
     */
    public function getCacheStats(): array
    {
        $stats = get_option('rankingcoach_cache_stats', []);
        
        // Add system cache information
        $stats['system'] = [
            'object_cache_enabled' => wp_using_ext_object_cache(),
            'opcache_enabled' => function_exists('opcache_get_status') && opcache_get_status(false)['opcache_enabled'] ?? false,
            'multisite' => is_multisite(),
            'cache_plugins' => $this->detectCachePlugins(),
        ];
        
        return $stats;
    }

    /**
     * Get detailed cache information
     *
     * @return array Detailed cache information
     */
    public function getCacheInfo(): array
    {
        global $wpdb;
        
        $info = [];
        
        try {
            $dbManager = DatabaseManager::getInstance();
            
            // Count plugin transients
            $transientCount = 0;
            foreach (self::PLUGIN_PREFIXES as $prefix) {
                $query = $dbManager->db()
                    ->table($wpdb->options)
                    ->select('COUNT(*) as cnt');
                
                $query->whereOr(function ($q) use ($prefix) {
                    $q->whereLike('option_name', '_transient_' . $prefix . '%');
                    $q->whereLike('option_name', '_transient_timeout_' . $prefix . '%');
                });
                
                $result = $query->first();
                $count = $result ? (int) $result->cnt : 0;
                $transientCount += $count;
            }
            $info['transients'] = $transientCount;
            
            // Count site transients (multisite)
            if (is_multisite()) {
                $siteTransientCount = 0;
                foreach (self::PLUGIN_PREFIXES as $prefix) {
                    $query = $dbManager->db()
                        ->table($wpdb->sitemeta)
                        ->select('COUNT(*) as cnt');
                    
                    $query->whereOr(function ($q) use ($prefix) {
                        $q->whereLike('meta_key', '_site_transient_' . $prefix . '%');
                        $q->whereLike('meta_key', '_site_transient_timeout_' . $prefix . '%');
                    });
                    
                    $result = $query->first();
                    $count = $result ? (int) $result->cnt : 0;
                    $siteTransientCount += $count;
                }
                $info['site_transients'] = $siteTransientCount;
            }
            
            // OPcache information
            if (function_exists('opcache_get_status')) {
                $opcacheStatus = opcache_get_status(false);
                $info['opcache'] = [
                    'enabled' => $opcacheStatus['opcache_enabled'] ?? false,
                    'memory_usage' => $opcacheStatus['memory_usage'] ?? null,
                    'scripts_cached' => $opcacheStatus['opcache_statistics']['num_cached_scripts'] ?? 0,
                ];
            }
            
            // Object cache information
            $info['object_cache'] = [
                'enabled' => wp_using_ext_object_cache(),
                'type' => $this->getObjectCacheType(),
            ];
            
            // Cache plugins
            $info['cache_plugins'] = $this->detectCachePlugins();
            
        } catch (Throwable $e) {
            $this->log('Error getting cache info: ' . $e->getMessage());
            $info['error'] = $e->getMessage();
        }
        
        return $info;
    }

    /**
     * Perform scheduled cache cleanup
     */
    public function performScheduledCleanup(): void
    {
        try {
            // Clear expired transients
            $this->clearExpiredTransients();
            
            // Clear old cache statistics
            $this->cleanupOldStats();
            
            $this->log('Scheduled cache cleanup completed');
            
        } catch (Throwable $e) {
            $this->log('Error during scheduled cleanup: ' . $e->getMessage());
        }
    }

    /**
     * Clear expired transients
     *
     * @return int Number of expired transients cleared
     */
    public function clearExpiredTransients(): int
    {
        global $wpdb;
        $cleared = 0;
        
        try {
            $dbManager = DatabaseManager::getInstance();
            
            // Clear expired regular transients
            $expiredResults = $dbManager->db()
                ->table($wpdb->options)
                ->select("REPLACE(option_name, '_transient_timeout_', '') as transient_name")
                ->whereLike('option_name', '_transient_timeout_%')
                ->whereRaw('option_value < UNIX_TIMESTAMP()')
                ->get();
            
            foreach ($expiredResults as $row) {
                delete_transient($row->transient_name);
                $cleared++;
            }
            
            // Clear expired site transients (multisite)
            if (is_multisite()) {
                $expiredSiteResults = $dbManager->db()
                    ->table($wpdb->sitemeta)
                    ->select("REPLACE(meta_key, '_site_transient_timeout_', '') as transient_name")
                    ->whereLike('meta_key', '_site_transient_timeout_%')
                    ->whereRaw('meta_value < UNIX_TIMESTAMP()')
                    ->get();
                
                foreach ($expiredSiteResults as $row) {
                    delete_site_transient($row->transient_name);
                    $cleared++;
                }
            }
            
            $this->updateCacheStats('expired_transients_cleared', $cleared);
            
        } catch (Throwable $e) {
            $this->log('Error clearing expired transients: ' . $e->getMessage());
        }
        
        return $cleared;
    }

    /**
     * Event handlers
     */
    public function onWordPressCacheFlush(): void
    {
        $this->updateCacheStats('wp_cache_flush_triggered', time());
    }

    public function onPostCacheClean(int $postId): void
    {
        $this->updateCacheStats('post_cache_cleaned', $postId);
    }

    public function onTermCacheClean(array $termIds, string $taxonomy = '', bool $cleanTaxonomy = true): void
    {
        $this->updateCacheStats('term_cache_cleaned', count($termIds));
    }

    public function onUserCacheClean(int $userId): void
    {
        $this->updateCacheStats('user_cache_cleaned', $userId);
    }

    /**
     * Helper methods
     */
    private function getPluginCacheKeys(): array
    {
        // Return array of cache keys that might be used by the plugin
        return [
            'rankingcoach_settings' => 'options',
            'rankingcoach_tokens' => 'options',
            'rankingcoach_user_data' => 'options',
            'rc_api_cache' => 'options',
        ];
    }

    private function detectCachePlugins(): array
    {
        $plugins = [];
        
        if (defined('WP_CACHE') && WP_CACHE) {
            $plugins[] = 'WP_CACHE enabled';
        }
        
        if (class_exists('WP_Rocket')) {
            $plugins[] = 'WP Rocket';
        }
        
        if (class_exists('W3_Plugin_TotalCache')) {
            $plugins[] = 'W3 Total Cache';
        }
        
        if (class_exists('WpFastestCache')) {
            $plugins[] = 'WP Fastest Cache';
        }
        
        if (function_exists('wp_cache_clear_cache')) {
            $plugins[] = 'WP Super Cache';
        }
        
        if (class_exists('LiteSpeed_Cache')) {
            $plugins[] = 'LiteSpeed Cache';
        }
        
        return $plugins;
    }

    private function getObjectCacheType(): string
    {
        if (!wp_using_ext_object_cache()) {
            return 'none';
        }
        
        if (class_exists('Redis')) {
            return 'Redis';
        }
        
        if (class_exists('Memcached')) {
            return 'Memcached';
        }
        
        return 'unknown';
    }

    private function updateCacheStats(string $key, $value): void
    {
        $stats = get_option('rankingcoach_cache_stats', []);
        $stats[$key] = $value;
        $stats['last_updated'] = time();
        update_option('rankingcoach_cache_stats', $stats);
    }

    private function cleanupOldStats(): void
    {
        $stats = get_option('rankingcoach_cache_stats', []);
        $cutoff = time() - (30 * DAY_IN_SECONDS); // Keep 30 days
        
        foreach ($stats as $key => $value) {
            if (is_numeric($value) && $value < $cutoff) {
                unset($stats[$key]);
            }
        }
        
        update_option('rankingcoach_cache_stats', $stats);
    }

    /**
     * Cleanup on plugin deactivation
     */
    public function cleanup(): void
    {
        // Remove scheduled events
        wp_clear_scheduled_hook('rankingcoach_cache_cleanup');
        
        // Clear plugin cache statistics
        delete_option('rankingcoach_cache_stats');
    }
}
