<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core;

if ( !defined('ABSPATH') ) {
    exit;
}

use DDD\Infrastructure\Traits\SingletonTrait;
use JsonSerializable;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\DB\DatabaseManager;
use RankingCoach\Inc\Interfaces\PluginConfigurationInterface;
use Throwable;

/**
 * Class Configuration
 */
class PluginConfiguration implements PluginConfigurationInterface, JsonSerializable
{
    use SingletonTrait;
    use RcLoggerTrait;

    /**
     * Returns the plugin file.
     *
     * @return string
     */
    public function getPluginFile(): string {
        return RANKINGCOACH_FILE;
    }

	/**
	 * Returns the plugin version.
	 *
	 * @return string
	 */
	public function getPluginVersion(): string {
		return RANKINGCOACH_VERSION;
	}

	/**
	 * Returns the plugin name.
	 *
	 * @return string
	 */
	public function getPluginName(): string {
		return RANKINGCOACH_BRAND_NAME;
	}

	/**
	 * Returns the plugin basename.
	 *
	 * @return string
	 */
	public function getPluginBasename(): string {
		return RANKINGCOACH_PLUGIN_BASENAME;
	}

	/**
	 * Returns the plugin directory.
	 *
	 * @return string
	 */
	public function getPluginDir(): string {
		return RANKINGCOACH_PLUGIN_DIR;
	}

	/**
	 * Returns the plugin URL.
	 *
	 * @return string
	 */
	public function getPluginUrl(): string {
		return RANKINGCOACH_PLUGIN_URL;
	}

	/**
	 * Returns the plugin environment.
	 *
	 * @return string
	 */
	public function getPluginEnvironment(): string {
		return RANKINGCOACH_ENVIRONMENT;
	}

	/**
	 * Returns the plugin namespace.
	 *
	 * @return string
	 */
	public function getPluginNamespace(): string {
		return RANKINGCOACH_NAMESPACE;
	}

	/**
	 * Returns the app namespace.
	 *
	 * @return string
	 */
	public function getAppNamespace(): string {
		return RANKINGCOACH_APP_NAMESPACE;
	}

	/**
	 * Returns the configuration as an array.
	 *
	 * @return array
	 */
	public function jsonSerialize(): array {
		return [
			'plugin_file' => $this->getPluginFile(),
			'plugin_version' => $this->getPluginVersion(),
			'plugin_name' => $this->getPluginName(),
			'plugin_basename' => $this->getPluginBasename(),
			'plugin_dir' => $this->getPluginDir(),
			'plugin_url' => $this->getPluginUrl(),
			'plugin_environment' => $this->getPluginEnvironment(),
			'plugin_namespace' => $this->getPluginNamespace(),
			'app_namespace' => $this->getAppNamespace(),
		];
	}

    /**
     * Remove all options with 'rankingcoach_' prefix and related transients
     *
     * @return static
     */
    public function removeOptions(): static
    {
        // Get all options names and delete them
        $optionNames = BaseConstants::getOptionNames();
        foreach ($optionNames as $optionName) {
            delete_option($optionName);
        }

        // Performance: Clear relevant caches
        $this->clearRelatedCaches();

        // Remove options with start with 'rankingcoach_', 'rc_', 'bseo_' prefix
        $this->removePrefixedOptions();

        // Remove transients with specified patterns: _rankingcoach_,  _rc_, _bseo_
        $this->removeTransients();

        return $this;
    }

    /**
     * Remove all transients with rankingcoach patterns
     *
     * @return void
     */
    private function removeTransients(): void
    {
        try {
            $dbManager = DatabaseManager::getInstance();
            
            $transientPatterns = [
                '_transient_rankingcoach_%',
                '_transient_timeout_rankingcoach_%',
                '_transient_rc_%',
                '_transient_timeout_rc_%',
                '_transient_bseo_%',
                '_transient_timeout_bseo_%',
                '_transient_beyondseo_%',
                '_transient_timeout_beyondseo_%'
            ];

            foreach ($transientPatterns as $pattern) {
                $dbManager->db()->queryRaw(
                    "DELETE FROM " . $dbManager->db()->db->options . " WHERE option_name LIKE '" . 
                    $dbManager->db()->db->esc_like($pattern) . "'"
                );
            }

            if (is_multisite()) {
                $siteTransientPatterns = [
                    '_site_transient_rankingcoach_%',
                    '_site_transient_timeout_rankingcoach_%',
                    '_site_transient_rc_%',
                    '_site_transient_timeout_rc_%'
                ];

                foreach ($siteTransientPatterns as $pattern) {
                    $dbManager->db()->queryRaw(
                        "DELETE FROM " . $dbManager->db()->db->sitemeta . " WHERE meta_key LIKE '" . 
                        $dbManager->db()->db->esc_like($pattern) . "'"
                    );
                }
            }

            if (function_exists('wp_cache_flush_group')) {
                wp_cache_flush_group('transient');
                wp_cache_flush_group('site-transient');
            }

        } catch (Throwable $e) {
        }
    }

    /**
     * Remove all WordPress options with specified prefixes using secure database operations
     * Implements WordPress security best practices and optimized database queries
     *
     * @return void
     */
    private function removePrefixedOptions(): void
    {
        try {
            $dbManager = DatabaseManager::getInstance();
            
            $optionPrefixes = [
                'rankingcoach_%',
                'rc_%',
                'bseo_%',
                'beyondseo_%'
            ];

            if (!current_user_can('manage_options')) {
                return;
            }

            $dbManager->beginTransaction();

            $query = $dbManager->table('options')->select('option_name');

            $query->whereOr(function($q) use ($optionPrefixes) {
                foreach ($optionPrefixes as $prefix) {
                    $q->where('option_name', $prefix, 'LIKE');
                }
            });

            $query->where('option_name', '_transient%', 'NOT LIKE');
            
            $optionNames = $query->get();

            if (!empty($optionNames)) {
                $columnValues = [];
                foreach ($optionNames as $row) {
                    if (is_object($row)) {
                        $columnValues[] = $row->option_name;
                    } else {
                        $columnValues[] = $row['option_name'];
                    }
                }

                $result = $dbManager->table('options')
                    ->delete()
                    ->whereIn('option_name', $columnValues)
                    ->get();

                if ($result === false) {
                    $dbManager->rollback();
                    return;
                }

                wp_cache_delete_multiple($columnValues, 'options');
            }

            if (is_multisite()) {
                $this->removeMultisitePrefixedOptions($optionPrefixes);
            }

            $dbManager->commit();

            $this->log('Successfully removed all prefixed options during plugin cleanup');

        } catch (Throwable $e) {
            try {
                DatabaseManager::getInstance()->rollback();
            } catch (Throwable) {
            }
            $this->log('Critical error during option cleanup: ' . $e->getMessage());
        }
    }

    /**
     * Remove prefixed options from multisite network tables
     *
     * @param array $optionPrefixes Array of option prefixes to remove
     * @return void
     */
    private function removeMultisitePrefixedOptions(array $optionPrefixes): void
    {
        try {
            if (!current_user_can('manage_network_options')) {
                return;
            }

            $dbManager = DatabaseManager::getInstance();

            $query = $dbManager->table('sitemeta')->select('meta_key');

            $query->whereOr(function($q) use ($optionPrefixes) {
                foreach ($optionPrefixes as $prefix) {
                    $q->where('meta_key', $prefix, 'LIKE');
                }
            });

            $query->where('meta_key', '_site_transient%', 'NOT LIKE');
            
            $metaKeys = $query->get();

            if (!empty($metaKeys)) {
                $keyValues = [];
                foreach ($metaKeys as $row) {
                    if (is_object($row)) {
                        $keyValues[] = $row->meta_key;
                    } else {
                        $keyValues[] = $row['meta_key'];
                    }
                }

                $dbManager->table('sitemeta')
                    ->delete()
                    ->whereIn('meta_key', $keyValues)
                    ->get();

                wp_cache_delete_multiple($keyValues, 'site-options');
            }

            $sites = get_sites(['number' => 0]);
            foreach ($sites as $site) {
                switch_to_blog($site->blog_id);
                
                $siteQuery = $dbManager->table('options')->select('option_name');

                $siteQuery->whereOr(function($q) use ($optionPrefixes) {
                    foreach ($optionPrefixes as $prefix) {
                        $q->where('option_name', $prefix, 'LIKE');
                    }
                });

                $siteQuery->where('option_name', '_transient%', 'NOT LIKE');

                $siteOptions = $siteQuery->get();

                if (!empty($siteOptions)) {
                    $optionValues = [];
                    foreach ($siteOptions as $row) {
                        if (is_object($row)) {
                            $optionValues[] = $row->option_name;
                        } else {
                            $optionValues[] = $row['option_name'];
                        }
                    }
                    
                    $dbManager->table('options')
                        ->delete()
                        ->whereIn('option_name', $optionValues)
                        ->get();

                    wp_cache_delete_multiple($optionValues, 'options');
                }

                restore_current_blog();
            }

        } catch (Throwable $e) {
            restore_current_blog();
            $this->log('Error during multisite option cleanup: ' . $e->getMessage());
        }
    }

    /**
     * Clear WordPress caches related to options and plugin data
     * Enhanced with comprehensive cache management using CacheManager
     *
     * @return void
     */
    private function clearRelatedCaches(): void
    {
        try {
            // Use the comprehensive CacheManager for enhanced cache clearing
            if (class_exists(CacheManager::class)) {
                $cacheManager = CacheManager::getInstance();
                $cacheManager->clearAllPluginCaches();

            } else {
                // Fallback to original cache clearing methods
                $this->clearRelatedCachesFallback();
            }
        } catch (Throwable $e) {
            $this->log('Error during cache cleanup: ' . $e->getMessage());
            // Fallback to original methods if CacheManager fails
            $this->clearRelatedCachesFallback();
        }
    }

    /**
     * Fallback cache clearing method (original implementation)
     * Used when CacheManager is not available or fails
     *
     * @return void
     */
    private function clearRelatedCachesFallback(): void
    {
        try {
            if (function_exists('wp_cache_flush_group')) {
                wp_cache_flush_group('options');
                wp_cache_flush_group('site-options');
                wp_cache_flush_group('transient');
                wp_cache_flush_group('site-transient');
            }

            if (function_exists('wp_cache_flush_group')) {
                wp_cache_flush_group('options');
                wp_cache_flush_group('site-options');
            }

            if (function_exists('opcache_reset') && opcache_get_status(false)['opcache_enabled']) {
                opcache_reset();
            }

            flush_rewrite_rules(false);

            if (function_exists('wp_cache_flush_group')) {
                wp_cache_flush_group('posts');
                wp_cache_flush_group('terms');
                wp_cache_flush_group('users');
                wp_cache_flush_group('user_meta');
                wp_cache_flush_group('post_meta');
                wp_cache_flush_group('term_meta');
            }

            $dbManager = DatabaseManager::getInstance();
            if (method_exists($dbManager->db()->db, 'flush')) {
                $dbManager->db()->db->flush();
            }

            $this->log('Fallback cache clearing completed successfully');

        } catch (Throwable $e) {
            $this->log('Error during fallback cache cleanup: ' . $e->getMessage());
        }
    }
}
