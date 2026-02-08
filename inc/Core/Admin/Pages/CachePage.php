<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Admin\Pages;

if (!defined('ABSPATH')) {
    exit;
}

use RankingCoach\Inc\Core\Admin\AdminManager;
use RankingCoach\Inc\Core\Admin\AdminPage;
use RankingCoach\Inc\Core\CacheManager;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Traits\SingletonManager;

/**
 * Cache Management Page
 * Provides interface for cache management and monitoring
 * 
 * @method CachePage getInstance(): static
 */
class CachePage extends AdminPage
{
    use SingletonManager;

    public string $name = 'cache';
    public static AdminManager|null $managerInstance = null;

    /**
     * CachePage constructor
     */
    public function __construct()
    {
        parent::__construct();
        
        // Handle AJAX requests
        add_action('wp_ajax_rankingcoach_clear_cache', [$this, 'handleClearCacheAjax']);
        add_action('wp_ajax_rankingcoach_get_cache_info', [$this, 'handleGetCacheInfoAjax']);
    }

    /**
     * Get page name
     *
     * @return string
     */
    public function page_name(): string
    {
        return $this->name;
    }

    /**
     * Render page content
     *
     * @param callable|null $failCallback
     * @return void
     */
    public function page_content(?callable $failCallback = null): void
    {
        // Security check
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'beyond-seo'));
        }

        // Check activation status
        if (!WordpressHelpers::isActivationCompleted()) {
            if (self::$managerInstance instanceof AdminManager) {
                self::$managerInstance->redirectPage('activation');
            }
            if (is_callable($failCallback)) {
                $failCallback();
            }
            wp_die();
        }

        // Handle form submissions
        $this->handleFormSubmissions();

        // Get cache information
        $cacheManager = CacheManager::getInstance();
        $cacheInfo = $cacheManager->getCacheInfo();
        $cacheStats = $cacheManager->getCacheStats();

        // Render the page
        $this->renderPage($cacheInfo, $cacheStats);
    }

    /**
     * Handle form submissions
     */
    private function handleFormSubmissions(): void
    {
        if (!wp_verify_nonce(WordpressHelpers::sanitize_input('POST', '_wpnonce'), 'rankingcoach_cache_action')) {
            return;
        }

        $action = WordpressHelpers::sanitize_input('POST', 'action');
        $cacheManager = CacheManager::getInstance();

        switch ($action) {
            case 'clear_all_cache':
                $results = $cacheManager->clearAllPluginCaches();
                $this->addAdminNotice(
                    __('All plugin caches have been cleared successfully.', 'beyond-seo'),
                    'success'
                );
                break;

            case 'clear_object_cache':
                $result = $cacheManager->clearObjectCache();
                $message = $result 
                    ? __('Object cache cleared successfully.', 'beyond-seo')
                    : __('Failed to clear object cache.', 'beyond-seo');
                $this->addAdminNotice($message, $result ? 'success' : 'error');
                break;

            case 'clear_transients':
                $cleared = $cacheManager->clearTransients();
                $this->addAdminNotice(
                    /* translators: %d: number of transients cleared */
                    sprintf(__('Cleared %d transients.', 'beyond-seo'), $cleared),
                    'success'
                );
                break;

            case 'clear_opcache':
                $result = $cacheManager->clearOpcache();
                $message = $result 
                    ? __('OPcache cleared successfully.', 'beyond-seo')
                    : __('OPcache not available or failed to clear.', 'beyond-seo');
                $this->addAdminNotice($message, $result ? 'success' : 'warning');
                break;

            case 'clear_expired_transients':
                $cleared = $cacheManager->clearExpiredTransients();
                $this->addAdminNotice(
                    /* translators: %d: number of expired transients cleared */
                    sprintf(__('Cleared %d expired transients.', 'beyond-seo'), $cleared),
                    'success'
                );
                break;

            case 'enable_redis_cache':
                $result = $this->enableRedisObjectCache();
                $message = $result['success'] 
                    ? __('Redis Object Cache enabled successfully.', 'beyond-seo')
                    /* translators: %s: error message */
                    : sprintf(__('Failed to enable Redis Object Cache: %s', 'beyond-seo'), $result['message']);
                $this->addAdminNotice($message, $result['success'] ? 'success' : 'error');
                break;

            case 'enable_memcached_cache':
                $result = $this->enableMemcachedObjectCache();
                $message = $result['success'] 
                    ? __('Memcached Object Cache enabled successfully.', 'beyond-seo')
                    /* translators: %s: error message */
                    : sprintf(__('Failed to enable Memcached Object Cache: %s', 'beyond-seo'), $result['message']);
                $this->addAdminNotice($message, $result['success'] ? 'success' : 'error');
                break;

            case 'enable_apcu_cache':
                $result = $this->enableApcuObjectCache();
                $message = $result['success'] 
                    ? __('APCu Object Cache enabled successfully.', 'beyond-seo')
                    /* translators: %s: error message */
                    : sprintf(__('Failed to enable APCu Object Cache: %s', 'beyond-seo'), $result['message']);
                $this->addAdminNotice($message, $result['success'] ? 'success' : 'error');
                break;

            case 'disable_object_cache':
                $result = $this->disableObjectCache();
                $message = $result['success'] 
                    ? __('Object Cache disabled successfully.', 'beyond-seo')
                    /* translators: %s: error message */
                    : sprintf(__('Failed to disable Object Cache: %s', 'beyond-seo'), $result['message']);
                $this->addAdminNotice($message, $result['success'] ? 'success' : 'error');
                break;

            case 'test_object_cache':
                $result = $this->testObjectCache();
                $message = $result['success'] 
                    ? __('Object Cache test passed successfully.', 'beyond-seo')
                    /* translators: %s: error message */
                    : sprintf(__('Object Cache test failed: %s', 'beyond-seo'), $result['message']);
                $this->addAdminNotice($message, $result['success'] ? 'success' : 'error');
                break;
        }
    }

    /**
     * Render the cache management page
     *
     * @param array $cacheInfo
     * @param array $cacheStats
     */
    private function renderPage(array $cacheInfo, array $cacheStats): void
    {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Cache Management', 'beyond-seo'); ?></h1>
            
            <?php $this->renderAdminNotices(); ?>
            
            <div class="rankingcoach-cache-dashboard">
                <div class="cache-overview">
                    <?php $this->renderCacheOverview($cacheInfo); ?>
                </div>
                
                <div class="cache-actions">
                    <?php $this->renderCacheActions(); ?>
                </div>
                
                <div class="object-cache-management">
                    <?php $this->renderObjectCacheManagementCard(); ?>
                </div>
                
                <div class="cache-details">
                    <?php $this->renderCacheDetails($cacheInfo, $cacheStats); ?>
                </div>
            </div>
        </div>

        <style>
            .rankingcoach-cache-dashboard {
                display: grid;
                grid-template-columns: 1fr 1fr 1fr;
                grid-gap: 20px;
                margin-top: 20px;
            }
            
            .cache-details {
                grid-column: 1 / -1;
            }
            
            /* Responsive design pentru mobile/tablet */
            @media (max-width: 1024px) {
                .rankingcoach-cache-dashboard {
                    grid-template-columns: 1fr;
                }
                
                .cache-details {
                    grid-column: 1;
                }
            }
            
            .cache-card {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 20px;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
            }
            
            .cache-card h2 {
                margin-top: 0;
                border-bottom: 1px solid #eee;
                padding-bottom: 10px;
            }
            
            .cache-status {
                display: flex;
                align-items: center;
                margin: 10px 0;
            }
            
            .status-indicator {
                width: 12px;
                height: 12px;
                border-radius: 50%;
                margin-right: 8px;
            }
            
            .status-enabled { background-color: #46b450; }
            .status-disabled { background-color: #dc3232; }
            .status-warning { background-color: #ffb900; }
            
            .cache-actions-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                grid-gap: 10px;
            }
            
            .cache-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 15px;
            }
            
            .cache-table th,
            .cache-table td {
                padding: 8px 12px;
                text-align: left;
                border-bottom: 1px solid #eee;
            }
            
            .cache-table th {
                background-color: #f9f9f9;
                font-weight: 600;
            }
            
            .refresh-button {
                float: right;
                margin-bottom: 10px;
            }
        </style>

        <script>
            jQuery(document).ready(function($) {
                // Auto-refresh cache info every 30 seconds
                setInterval(function() {
                    refreshCacheInfo();
                }, 30000);
                
                // Manual refresh button
                $('.refresh-cache-info').on('click', function(e) {
                    e.preventDefault();
                    refreshCacheInfo();
                });
                
                function refreshCacheInfo() {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'rankingcoach_get_cache_info',
                            nonce: '<?php echo esc_js(wp_create_nonce('rankingcoach_cache_info')); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                updateCacheDisplay(response.data);
                            }
                        }
                    });
                }
                
                function updateCacheDisplay(data) {
                    // Update transient counts
                    if (data.transients !== undefined) {
                        $('.transient-count').text(data.transients);
                    }
                    if (data.site_transients !== undefined) {
                        $('.site-transient-count').text(data.site_transients);
                    }
                    
                    // Update last refresh time
                    $('.last-refresh').text(new Date().toLocaleTimeString());
                }
            });
        </script>
        <?php
    }

    /**
     * Render cache overview section
     *
     * @param array $cacheInfo
     */
    private function renderCacheOverview(array $cacheInfo): void
    {
        ?>
        <div class="cache-card">
            <h2><?php echo esc_html__('Cache Overview', 'beyond-seo'); ?></h2>
            
            <div class="cache-status">
                <span class="status-indicator <?php echo wp_using_ext_object_cache() ? 'status-enabled' : 'status-disabled'; ?>"></span>
                <strong><?php echo esc_html__('Object Cache:', 'beyond-seo'); ?></strong>
                <?php if (wp_using_ext_object_cache()): ?>
                    <?php echo esc_html__('Enabled', 'beyond-seo'); ?>
                    <?php if (isset($cacheInfo['object_cache']['type'])): ?>
                        (<?php echo esc_html($cacheInfo['object_cache']['type']); ?>)
                    <?php endif; ?>
                <?php else: ?>
                    <?php echo esc_html__('Disabled', 'beyond-seo'); ?>
                <?php endif; ?>
            </div>
            
            <div class="cache-status">
                <span class="status-indicator <?php echo (isset($cacheInfo['opcache']['enabled']) && $cacheInfo['opcache']['enabled']) ? 'status-enabled' : 'status-disabled'; ?>"></span>
                <strong><?php echo esc_html__('OPcache:', 'beyond-seo'); ?></strong>
                <?php if (isset($cacheInfo['opcache']['enabled']) && $cacheInfo['opcache']['enabled']): ?>
                    <?php echo esc_html__('Enabled', 'beyond-seo'); ?>
                    <?php if (isset($cacheInfo['opcache']['scripts_cached'])): ?>
                        (<?php 
                        /* translators: %d: number of scripts cached */
                        echo esc_html(sprintf(__('%d scripts cached', 'beyond-seo'), $cacheInfo['opcache']['scripts_cached'])); ?>)
                    <?php endif; ?>
                <?php else: ?>
                    <?php echo esc_html__('Disabled', 'beyond-seo'); ?>
                <?php endif; ?>
            </div>
            
            <div class="cache-status">
                <span class="status-indicator <?php echo is_multisite() ? 'status-enabled' : 'status-disabled'; ?>"></span>
                <strong><?php echo esc_html__('Multisite:', 'beyond-seo'); ?></strong>
                <?php echo is_multisite() ? esc_html__('Yes', 'beyond-seo') : esc_html__('No', 'beyond-seo'); ?>
            </div>
            
            <?php if (!empty($cacheInfo['cache_plugins'])): ?>
                <div class="cache-status">
                    <span class="status-indicator status-enabled"></span>
                    <strong><?php echo esc_html__('Cache Plugins:', 'beyond-seo'); ?></strong>
                    <?php echo esc_html(implode(', ', $cacheInfo['cache_plugins'])); ?>
                </div>
            <?php endif; ?>
            
            <p><small><?php echo esc_html__('Last updated:', 'beyond-seo'); ?> <span class="last-refresh"><?php echo esc_html(current_time('H:i:s')); ?></span></small></p>
        </div>
        <?php
    }

    /**
     * Render cache actions section
     */
    private function renderCacheActions(): void
    {
        ?>
        <div class="cache-card">
            <h2><?php echo esc_html__('Cache Actions', 'beyond-seo'); ?></h2>
            
            <div class="cache-actions-grid">
                <form method="post" style="margin: 0;">
                    <?php wp_nonce_field('rankingcoach_cache_action'); ?>
                    <input type="hidden" name="action" value="clear_all_cache">
                    <button type="submit" class="button button-primary button-large" style="width: 100%;">
                        <?php echo esc_html__('Clear All Caches', 'beyond-seo'); ?>
                    </button>
                </form>
                
                <form method="post" style="margin: 0;">
                    <?php wp_nonce_field('rankingcoach_cache_action'); ?>
                    <input type="hidden" name="action" value="clear_object_cache">
                    <button type="submit" class="button button-secondary button-large" style="width: 100%;">
                        <?php echo esc_html__('Clear Object Cache', 'beyond-seo'); ?>
                    </button>
                </form>
                
                <form method="post" style="margin: 0;">
                    <?php wp_nonce_field('rankingcoach_cache_action'); ?>
                    <input type="hidden" name="action" value="clear_transients">
                    <button type="submit" class="button button-secondary button-large" style="width: 100%;">
                        <?php echo esc_html__('Clear Transients', 'beyond-seo'); ?>
                    </button>
                </form>
                
                <form method="post" style="margin: 0;">
                    <?php wp_nonce_field('rankingcoach_cache_action'); ?>
                    <input type="hidden" name="action" value="clear_opcache">
                    <button type="submit" class="button button-secondary button-large" style="width: 100%;">
                        <?php echo esc_html__('Clear OPcache', 'beyond-seo'); ?>
                    </button>
                </form>
            </div>

            <br>

            <form method="post" style="margin: 0;">
                <?php wp_nonce_field('rankingcoach_cache_action'); ?>
                <input type="hidden" name="action" value="clear_expired_transients">
                <button type="submit" class="button button-secondary" style="width: 100%;">
                    <?php echo esc_html__('Clear Expired Transients Only', 'beyond-seo'); ?>
                </button>
            </form>

            <p><small><?php echo esc_html__('Use "Clear All Caches" for complete cache reset. Individual actions target specific cache types.', 'beyond-seo'); ?></small></p>
        </div>
        <?php
    }

    /**
     * Render cache details section
     *
     * @param array $cacheInfo
     * @param array $cacheStats
     */
    private function renderCacheDetails(array $cacheInfo, array $cacheStats): void
    {
        ?>
        <div class="cache-card">
            <h2>
                <?php echo esc_html__('Cache Details', 'beyond-seo'); ?>
                <button class="button button-small refresh-cache-info refresh-button">
                    <?php echo esc_html__('Refresh', 'beyond-seo'); ?>
                </button>
            </h2>
            
            <table class="cache-table">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Cache Type', 'beyond-seo'); ?></th>
                        <th><?php echo esc_html__('Count/Status', 'beyond-seo'); ?></th>
                        <th><?php echo esc_html__('Details', 'beyond-seo'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php echo esc_html__('Transients', 'beyond-seo'); ?></td>
                        <td><span class="transient-count"><?php echo esc_html($cacheInfo['transients'] ?? 0); ?></span></td>
                        <td><?php echo esc_html__('Plugin-specific transients in database', 'beyond-seo'); ?></td>
                    </tr>
                    
                    <?php if (is_multisite()): ?>
                    <tr>
                        <td><?php echo esc_html__('Site Transients', 'beyond-seo'); ?></td>
                        <td><span class="site-transient-count"><?php echo esc_html($cacheInfo['site_transients'] ?? 0); ?></span></td>
                        <td><?php echo esc_html__('Network-wide transients (multisite)', 'beyond-seo'); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <tr>
                        <td><?php echo esc_html__('Object Cache', 'beyond-seo'); ?></td>
                        <td><?php echo wp_using_ext_object_cache() ? esc_html__('Active', 'beyond-seo') : esc_html__('Inactive', 'beyond-seo'); ?></td>
                        <td>
                            <?php if (wp_using_ext_object_cache()): ?>
                                <?php 
                                /* translators: %s: cache type name */
                                echo esc_html(sprintf(__('Type: %s', 'beyond-seo'), $cacheInfo['object_cache']['type'] ?? __('Unknown', 'beyond-seo'))); ?>
                            <?php else: ?>
                                <?php echo esc_html__('Using default WordPress object cache', 'beyond-seo'); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <td><?php echo esc_html__('OPcache', 'beyond-seo'); ?></td>
                        <td>
                            <?php if (isset($cacheInfo['opcache']['enabled']) && $cacheInfo['opcache']['enabled']): ?>
                                <?php echo esc_html__('Enabled', 'beyond-seo'); ?>
                            <?php else: ?>
                                <?php echo esc_html__('Disabled', 'beyond-seo'); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (isset($cacheInfo['opcache']['memory_usage'])): ?>
                                <?php 
                                /* translators: %s: memory usage in megabytes */
                                echo esc_html(sprintf(__('Memory: %s MB', 'beyond-seo'), round($cacheInfo['opcache']['memory_usage']['used_memory'] / 1024 / 1024, 2))); ?>
                            <?php else: ?>
                                <?php echo esc_html__('PHP bytecode cache', 'beyond-seo'); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <?php if (!empty($cacheStats)): ?>
            <h3><?php echo esc_html__('Cache Statistics', 'beyond-seo'); ?></h3>
            <table class="cache-table">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Statistic', 'beyond-seo'); ?></th>
                        <th><?php echo esc_html__('Value', 'beyond-seo'); ?></th>
                        <th><?php echo esc_html__('Last Updated', 'beyond-seo'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cacheStats as $key => $value): ?>
                        <?php if ($key === 'system' || $key === 'last_updated') continue; ?>
                        <tr>
                            <td><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?></td>
                            <td><?php echo esc_html(is_array($value) ? json_encode($value) : $value); ?></td>
                            <td>
                                <?php if (isset($cacheStats['last_updated'])): ?>
                                    <?php echo esc_html(human_time_diff($cacheStats['last_updated']) . ' ago'); ?>
                                <?php else: ?>
                                    <?php echo esc_html__('Unknown', 'beyond-seo'); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Handle AJAX request for clearing cache
     */
    public function handleClearCacheAjax(): void
    {
        // Security checks
        if (!isset($_POST['nonce']) || !wp_verify_nonce(WordpressHelpers::sanitize_input('POST', 'nonce'), 'rankingcoach_cache_action')) {
            wp_die(esc_html__('Security check failed', 'beyond-seo'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'beyond-seo'));
        }

        $cacheType = isset($_POST['cache_type']) ? WordpressHelpers::sanitize_input('POST', 'cache_type') : 'all';
        $cacheManager = CacheManager::getInstance();

        $result = false;
        $message = '';

        switch ($cacheType) {
            case 'all':
                $results = $cacheManager->clearAllPluginCaches();
                $result = !isset($results['error']);
                $message = $result ? __('All caches cleared', 'beyond-seo') : $results['error'];
                break;
            case 'object':
                $result = $cacheManager->clearObjectCache();
                $message = $result ? __('Object cache cleared', 'beyond-seo') : __('Failed to clear object cache', 'beyond-seo');
                break;
            case 'transients':
                $cleared = $cacheManager->clearTransients();
                $result = true;
                /* translators: %d: number of transients cleared */
                $message = sprintf(__('Cleared %d transients', 'beyond-seo'), $cleared);
                break;
        }

        wp_send_json_success([
            'success' => $result,
            'message' => $message
        ]);
    }

    /**
     * Handle AJAX request for getting cache info
     */
    public function handleGetCacheInfoAjax(): void
    {
        // Security checks
        if (!isset($_POST['nonce']) || !wp_verify_nonce(WordpressHelpers::sanitize_input('POST', 'nonce'), 'rankingcoach_cache_info')) {
            wp_die(esc_html__('Security check failed', 'beyond-seo'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'beyond-seo'));
        }

        $cacheManager = CacheManager::getInstance();
        $cacheInfo = $cacheManager->getCacheInfo();

        wp_send_json_success($cacheInfo);
    }

    /**
     * Add admin notice
     *
     * @param string $message
     * @param string $type
     */
    private function addAdminNotice(string $message, string $type = 'info'): void
    {
        add_action('admin_notices', function() use ($message, $type) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr($type),
                esc_html($message)
            );
        });
    }

    /**
     * Render admin notices
     */
    private function renderAdminNotices(): void
    {
        // This will be called by WordPress automatically
        do_action('admin_notices');
    }

    /**
     * Render Object Cache Management Card
     */
    private function renderObjectCacheManagementCard(): void
    {
        ?>
        <div class="cache-card">
            <h2><?php echo esc_html__('Object Cache Management', 'beyond-seo'); ?></h2>
            <?php $this->renderObjectCacheManagement(); ?>
        </div>
        <?php
    }

    /**
     * Render Object Cache Management section
     */
    private function renderObjectCacheManagement(): void
    {
        $availableCache = $this->detectAvailableObjectCache();
        $objectCacheEnabled = wp_using_ext_object_cache();
        $objectCacheFile = WP_CONTENT_DIR . '/object-cache.php';
        $objectCacheExists = file_exists($objectCacheFile);
        
        ?>
        <div style="margin-bottom: 15px;">
            <strong><?php echo esc_html__('Current Status:', 'beyond-seo'); ?></strong>
            <?php if ($objectCacheEnabled): ?>
                <span style="color: #46b450;">✅ <?php echo esc_html__('Enabled', 'beyond-seo'); ?></span>
            <?php else: ?>
                <span style="color: #dc3232;">❌ <?php echo esc_html__('Disabled', 'beyond-seo'); ?></span>
            <?php endif; ?>
        </div>

        <?php if (!empty($availableCache)): ?>
            <div style="margin-bottom: 15px;">
                <strong><?php echo esc_html__('Available Systems:', 'beyond-seo'); ?></strong>
                <ul style="margin: 5px 0 0 20px;">
                    <?php foreach ($availableCache as $key => $cache): ?>
                        <li>
                            <strong><?php echo esc_html($cache['name']); ?></strong>
                            <?php if ($cache['status']): ?>
                                <span style="color: #46b450;">✅ <?php echo esc_html__('Connected', 'beyond-seo'); ?></span>
                            <?php else: ?>
                                <span style="color: #dc3232;">❌ <?php echo esc_html__('Not Connected', 'beyond-seo'); ?></span>
                            <?php endif; ?>
                            <br><small><?php echo esc_html($cache['description']); ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="cache-actions-grid" style="margin-bottom: 15px;">
                <?php foreach ($availableCache as $key => $cache): ?>
                    <?php if ($cache['status']): ?>
                        <form method="post" style="margin: 0;">
                            <?php wp_nonce_field('rankingcoach_cache_action'); ?>
                            <input type="hidden" name="action" value="enable_<?php echo esc_attr($key); ?>_cache">
                            <button type="submit" class="button button-secondary" style="width: 100%;">
                                <?php 
                                /* translators: %s: cache system name */
                                echo esc_html(sprintf(__('Enable %s', 'beyond-seo'), $cache['name'])); ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <button class="button button-secondary" disabled style="width: 100%;">
                            <?php 
                            /* translators: %s: cache system name */
                            echo esc_html(sprintf(__('%s Not Available', 'beyond-seo'), $cache['name'])); ?>
                        </button>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
                <strong><?php echo esc_html__('No Object Cache Systems Available', 'beyond-seo'); ?></strong><br>
                <small><?php echo esc_html__('Install Redis, Memcached, or APCu to enable object caching.', 'beyond-seo'); ?></small>
            </div>
        <?php endif; ?>

        <div class="cache-actions-grid">
            <?php if ($objectCacheExists): ?>
                <form method="post" style="margin: 0;">
                    <?php wp_nonce_field('rankingcoach_cache_action'); ?>
                    <input type="hidden" name="action" value="disable_object_cache">
                    <button type="submit" class="button button-secondary" style="width: 100%;">
                        <?php echo esc_html__('Disable Object Cache', 'beyond-seo'); ?>
                    </button>
                </form>
            <?php endif; ?>
            
            <form method="post" style="margin: 0;">
                <?php wp_nonce_field('rankingcoach_cache_action'); ?>
                <input type="hidden" name="action" value="test_object_cache">
                <button type="submit" class="button button-secondary" style="width: 100%;">
                    <?php echo esc_html__('Test Object Cache', 'beyond-seo'); ?>
                </button>
            </form>
        </div>

        <?php if ($objectCacheExists): ?>
            <div style="background: #d1ecf1; border: 1px solid #bee5eb; padding: 10px; border-radius: 4px; margin-top: 10px;">
                <small>
                    <strong><?php echo esc_html__('Object Cache File:', 'beyond-seo'); ?></strong>
                    <?php echo esc_html($objectCacheFile); ?>
                </small>
            </div>
        <?php endif; ?>
        <?php
    }

    /**
     * Object Cache Management Methods
     */

    /**
     * Detect available object cache systems
     *
     * @return array
     */
    private function detectAvailableObjectCache(): array
    {
        $available = [];

        // Check Redis
        if (class_exists('Redis') || extension_loaded('redis')) {
            $available['redis'] = [
                'name' => 'Redis',
                'available' => true,
                'status' => $this->checkRedisConnection(),
                'description' => __('High-performance in-memory data structure store', 'beyond-seo')
            ];
        }

        // Check Memcached
        if (class_exists('Memcached') || extension_loaded('memcached')) {
            $available['memcached'] = [
                'name' => 'Memcached',
                'available' => true,
                'status' => $this->checkMemcachedConnection(),
                'description' => __('Distributed memory object caching system', 'beyond-seo')
            ];
        }

        // Check APCu
        if (extension_loaded('apcu') && function_exists('apcu_enabled') && apcu_enabled()) {
            $available['apcu'] = [
                'name' => 'APCu',
                'available' => true,
                'status' => true,
                'description' => __('User cache for PHP (shared memory)', 'beyond-seo')
            ];
        }

        return $available;
    }

    /**
     * Check Redis connection
     *
     * @return bool
     */
    private function checkRedisConnection(): bool
    {
        try {
            if (!class_exists('Redis')) {
                return false;
            }

            $redis = new Redis();
            $host = defined('WP_REDIS_HOST') ? WP_REDIS_HOST : '127.0.0.1';
            $port = defined('WP_REDIS_PORT') ? WP_REDIS_PORT : 6379;
            
            $connected = $redis->connect($host, $port, 1);
            if ($connected) {
                $redis->ping();
                $redis->close();
                return true;
            }
        } catch (Exception $e) {
            // Connection failed
        }

        return false;
    }

    /**
     * Check Memcached connection
     *
     * @return bool
     */
    private function checkMemcachedConnection(): bool
    {
        try {
            if (!class_exists('Memcached')) {
                return false;
            }

            $memcached = new Memcached();
            $memcached->addServer('127.0.0.1', 11211);
            
            $stats = $memcached->getStats();
            return !empty($stats);
        } catch (Exception $e) {
            // Connection failed
        }

        return false;
    }

    /**
     * Enable Redis Object Cache
     *
     * @return array
     */
    private function enableRedisObjectCache(): array
    {
        try {
            // Check if Redis is available
            if (!class_exists('Redis')) {
                return [
                    'success' => false,
                    'message' => __('Redis PHP extension is not installed.', 'beyond-seo')
                ];
            }

            // Check Redis connection
            if (!$this->checkRedisConnection()) {
                return [
                    'success' => false,
                    'message' => __('Cannot connect to Redis server. Please ensure Redis is running.', 'beyond-seo')
                ];
            }

            // Create object-cache.php file
            $objectCacheContent = $this->getRedisObjectCacheContent();
            $objectCachePath = WP_CONTENT_DIR . '/object-cache.php';

            if (file_put_contents($objectCachePath, $objectCacheContent) === false) {
                return [
                    'success' => false,
                    'message' => __('Failed to create object-cache.php file. Check file permissions.', 'beyond-seo')
                ];
            }

            return [
                'success' => true,
                'message' => __('Redis Object Cache enabled successfully.', 'beyond-seo')
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Enable Memcached Object Cache
     *
     * @return array
     */
    private function enableMemcachedObjectCache(): array
    {
        try {
            // Check if Memcached is available
            if (!class_exists('Memcached')) {
                return [
                    'success' => false,
                    'message' => __('Memcached PHP extension is not installed.', 'beyond-seo')
                ];
            }

            // Check Memcached connection
            if (!$this->checkMemcachedConnection()) {
                return [
                    'success' => false,
                    'message' => __('Cannot connect to Memcached server. Please ensure Memcached is running.', 'beyond-seo')
                ];
            }

            // Create object-cache.php file
            $objectCacheContent = $this->getMemcachedObjectCacheContent();
            $objectCachePath = WP_CONTENT_DIR . '/object-cache.php';

            if (file_put_contents($objectCachePath, $objectCacheContent) === false) {
                return [
                    'success' => false,
                    'message' => __('Failed to create object-cache.php file. Check file permissions.', 'beyond-seo')
                ];
            }

            return [
                'success' => true,
                'message' => __('Memcached Object Cache enabled successfully.', 'beyond-seo')
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Enable APCu Object Cache
     *
     * @return array
     */
    private function enableApcuObjectCache(): array
    {
        try {
            // Check if APCu is available
            if (!extension_loaded('apcu') || !function_exists('apcu_enabled') || !apcu_enabled()) {
                return [
                    'success' => false,
                    'message' => __('APCu PHP extension is not installed or enabled.', 'beyond-seo')
                ];
            }

            // Create object-cache.php file
            $objectCacheContent = $this->getApcuObjectCacheContent();
            $objectCachePath = WP_CONTENT_DIR . '/object-cache.php';

            if (file_put_contents($objectCachePath, $objectCacheContent) === false) {
                return [
                    'success' => false,
                    'message' => __('Failed to create object-cache.php file. Check file permissions.', 'beyond-seo')
                ];
            }

            return [
                'success' => true,
                'message' => __('APCu Object Cache enabled successfully.', 'beyond-seo')
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Disable Object Cache
     *
     * @return array
     */
    private function disableObjectCache(): array
    {
        try {
            $objectCachePath = WP_CONTENT_DIR . '/object-cache.php';

            if (!file_exists($objectCachePath)) {
                return [
                    'success' => false,
                    'message' => __('Object cache is not currently enabled.', 'beyond-seo')
                ];
            }

            if (!wp_delete_file($objectCachePath)) {
                return [
                    'success' => false,
                    'message' => __('Failed to remove object-cache.php file. Check file permissions.', 'beyond-seo')
                ];
            }

            return [
                'success' => true,
                'message' => __('Object Cache disabled successfully.', 'beyond-seo')
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Test Object Cache functionality
     *
     * @return array
     */
    private function testObjectCache(): array
    {
        try {
            $testKey = 'rankingcoach_cache_test_' . time();
            $testValue = 'test_value_' . wp_rand(1000, 9999);

            // Test set
            $setResult = wp_cache_set($testKey, $testValue, 'test_group', 300);
            if (!$setResult) {
                return [
                    'success' => false,
                    'message' => __('Failed to set cache value.', 'beyond-seo')
                ];
            }

            // Test get
            $getValue = wp_cache_get($testKey, 'test_group');
            if ($getValue !== $testValue) {
                return [
                    'success' => false,
                    /* translators: 1: expected cache value, 2: actual cache value received */
                    'message' => sprintf(__('Cache value mismatch. Expected: %1$s, Got: %2$s', 'beyond-seo'), $testValue, $getValue)
                ];
            }

            // Test delete
            $deleteResult = wp_cache_delete($testKey, 'test_group');
            if (!$deleteResult) {
                return [
                    'success' => false,
                    'message' => __('Failed to delete cache value.', 'beyond-seo')
                ];
            }

            // Verify deletion
            $verifyDelete = wp_cache_get($testKey, 'test_group');
            if ($verifyDelete !== false) {
                return [
                    'success' => false,
                    'message' => __('Cache value was not properly deleted.', 'beyond-seo')
                ];
            }

            return [
                'success' => true,
                'message' => __('All cache operations completed successfully.', 'beyond-seo')
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get Redis object-cache.php content
     *
     * @return string
     */
    private function getRedisObjectCacheContent(): string
    {
        return '<?php
/**
 * Redis Object Cache Drop-in
 * Generated by RankingCoach Plugin
 */

if (!defined("WP_REDIS_HOST")) {
    define("WP_REDIS_HOST", "127.0.0.1");
}

if (!defined("WP_REDIS_PORT")) {
    define("WP_REDIS_PORT", 6379);
}

if (!defined("WP_REDIS_DATABASE")) {
    define("WP_REDIS_DATABASE", 0);
}

if (!defined("WP_REDIS_PREFIX")) {
    define("WP_REDIS_PREFIX", "rankingcoach_");
}

class WP_Object_Cache {
    private $redis;
    private $cache = [];
    private $cache_hits = 0;
    private $cache_misses = 0;

    public function __construct() {
        $this->redis = new Redis();
        try {
            $this->redis->connect(WP_REDIS_HOST, WP_REDIS_PORT);
            if (defined("WP_REDIS_PASSWORD") && WP_REDIS_PASSWORD) {
                $this->redis->auth(WP_REDIS_PASSWORD);
            }
            $this->redis->select(WP_REDIS_DATABASE);
        } catch (Exception $e) {
            $this->redis = null;
        }
    }

    public function add($key, $data, $group = "default", $expire = 0) {
        if ($this->get($key, $group) !== false) {
            return false;
        }
        return $this->set($key, $data, $group, $expire);
    }

    public function set($key, $data, $group = "default", $expire = 0) {
        $cache_key = $this->key($key, $group);
        $this->cache[$cache_key] = $data;

        if ($this->redis) {
            try {
                if ($expire > 0) {
                    return $this->redis->setex($cache_key, $expire, serialize($data));
                } else {
                    return $this->redis->set($cache_key, serialize($data));
                }
            } catch (Exception $e) {
                return false;
            }
        }
        return true;
    }

    public function get($key, $group = "default", $force = false, &$found = null) {
        $cache_key = $this->key($key, $group);

        if (!$force && isset($this->cache[$cache_key])) {
            $found = true;
            $this->cache_hits++;
            return $this->cache[$cache_key];
        }

        if ($this->redis) {
            try {
                $value = $this->redis->get($cache_key);
                if ($value !== false) {
                    $found = true;
                    $this->cache_hits++;
                    $data = unserialize($value);
                    $this->cache[$cache_key] = $data;
                    return $data;
                }
            } catch (Exception $e) {
                // Fall through to cache miss
            }
        }

        $found = false;
        $this->cache_misses++;
        return false;
    }

    public function delete($key, $group = "default") {
        $cache_key = $this->key($key, $group);
        unset($this->cache[$cache_key]);

        if ($this->redis) {
            try {
                return $this->redis->del($cache_key) > 0;
            } catch (Exception $e) {
                return false;
            }
        }
        return true;
    }

    public function flush() {
        $this->cache = [];
        if ($this->redis) {
            try {
                return $this->redis->flushDB();
            } catch (Exception $e) {
                return false;
            }
        }
        return true;
    }

    private function key($key, $group) {
        return WP_REDIS_PREFIX . $group . ":" . $key;
    }

    public function stats() {
        return [
            "hits" => $this->cache_hits,
            "misses" => $this->cache_misses,
            "ratio" => $this->cache_hits / max($this->cache_hits + $this->cache_misses, 1)
        ];
    }
}

$wp_object_cache = new WP_Object_Cache();

function wp_cache_add($key, $data, $group = "", $expire = 0) {
    global $wp_object_cache;
    return $wp_object_cache->add($key, $data, $group, $expire);
}

function wp_cache_set($key, $data, $group = "", $expire = 0) {
    global $wp_object_cache;
    return $wp_object_cache->set($key, $data, $group, $expire);
}

function wp_cache_get($key, $group = "", $force = false, &$found = null) {
    global $wp_object_cache;
    return $wp_object_cache->get($key, $group, $force, $found);
}

function wp_cache_delete($key, $group = "") {
    global $wp_object_cache;
    return $wp_object_cache->delete($key, $group);
}

function wp_cache_flush() {
    global $wp_object_cache;
    return $wp_object_cache->flush();
}
';
    }

    /**
     * Get Memcached object-cache.php content
     *
     * @return string
     */
    private function getMemcachedObjectCacheContent(): string
    {
        return '<?php
/**
 * Memcached Object Cache Drop-in
 * Generated by RankingCoach Plugin
 */

class WP_Object_Cache {
    private $memcached;
    private $cache = [];
    private $cache_hits = 0;
    private $cache_misses = 0;

    public function __construct() {
        $this->memcached = new Memcached();
        $this->memcached->addServer("127.0.0.1", 11211);
    }

    public function add($key, $data, $group = "default", $expire = 0) {
        if ($this->get($key, $group) !== false) {
            return false;
        }
        return $this->set($key, $data, $group, $expire);
    }

    public function set($key, $data, $group = "default", $expire = 0) {
        $cache_key = $this->key($key, $group);
        $this->cache[$cache_key] = $data;
        
        $expiration = $expire > 0 ? time() + $expire : 0;
        return $this->memcached->set($cache_key, $data, $expiration);
    }

    public function get($key, $group = "default", $force = false, &$found = null) {
        $cache_key = $this->key($key, $group);

        if (!$force && isset($this->cache[$cache_key])) {
            $found = true;
            $this->cache_hits++;
            return $this->cache[$cache_key];
        }

        $value = $this->memcached->get($cache_key);
        if ($this->memcached->getResultCode() === Memcached::RES_SUCCESS) {
            $found = true;
            $this->cache_hits++;
            $this->cache[$cache_key] = $value;
            return $value;
        }

        $found = false;
        $this->cache_misses++;
        return false;
    }

    public function delete($key, $group = "default") {
        $cache_key = $this->key($key, $group);
        unset($this->cache[$cache_key]);
        return $this->memcached->delete($cache_key);
    }

    public function flush() {
        $this->cache = [];
        return $this->memcached->flush();
    }

    private function key($key, $group) {
        return "rankingcoach_" . $group . ":" . $key;
    }

    public function stats() {
        return [
            "hits" => $this->cache_hits,
            "misses" => $this->cache_misses,
            "ratio" => $this->cache_hits / max($this->cache_hits + $this->cache_misses, 1)
        ];
    }
}

$wp_object_cache = new WP_Object_Cache();

function wp_cache_add($key, $data, $group = "", $expire = 0) {
    global $wp_object_cache;
    return $wp_object_cache->add($key, $data, $group, $expire);
}

function wp_cache_set($key, $data, $group = "", $expire = 0) {
    global $wp_object_cache;
    return $wp_object_cache->set($key, $data, $group, $expire);
}

function wp_cache_get($key, $group = "", $force = false, &$found = null) {
    global $wp_object_cache;
    return $wp_object_cache->get($key, $group, $force, $found);
}

function wp_cache_delete($key, $group = "") {
    global $wp_object_cache;
    return $wp_object_cache->delete($key, $group);
}

function wp_cache_flush() {
    global $wp_object_cache;
    return $wp_object_cache->flush();
}
';
    }

    /**
     * Get APCu object-cache.php content
     *
     * @return string
     */
    private function getApcuObjectCacheContent(): string
    {
        return '<?php
/**
 * APCu Object Cache Drop-in
 * Generated by RankingCoach Plugin
 */

class WP_Object_Cache {
    private $cache = [];
    private $cache_hits = 0;
    private $cache_misses = 0;

    public function add($key, $data, $group = "default", $expire = 0) {
        if ($this->get($key, $group) !== false) {
            return false;
        }
        return $this->set($key, $data, $group, $expire);
    }

    public function set($key, $data, $group = "default", $expire = 0) {
        $cache_key = $this->key($key, $group);
        $this->cache[$cache_key] = $data;
        
        $ttl = $expire > 0 ? $expire : 0;
        return apcu_store($cache_key, $data, $ttl);
    }

    public function get($key, $group = "default", $force = false, &$found = null) {
        $cache_key = $this->key($key, $group);

        if (!$force && isset($this->cache[$cache_key])) {
            $found = true;
            $this->cache_hits++;
            return $this->cache[$cache_key];
        }

        $success = false;
        $value = apcu_fetch($cache_key, $success);
        
        if ($success) {
            $found = true;
            $this->cache_hits++;
            $this->cache[$cache_key] = $value;
            return $value;
        }

        $found = false;
        $this->cache_misses++;
        return false;
    }

    public function delete($key, $group = "default") {
        $cache_key = $this->key($key, $group);
        unset($this->cache[$cache_key]);
        return apcu_delete($cache_key);
    }

    public function flush() {
        $this->cache = [];
        return apcu_clear_cache();
    }

    private function key($key, $group) {
        return "rankingcoach_" . $group . ":" . $key;
    }

    public function stats() {
        return [
            "hits" => $this->cache_hits,
            "misses" => $this->cache_misses,
            "ratio" => $this->cache_hits / max($this->cache_hits + $this->cache_misses, 1)
        ];
    }
}

$wp_object_cache = new WP_Object_Cache();

function wp_cache_add($key, $data, $group = "", $expire = 0) {
    global $wp_object_cache;
    return $wp_object_cache->add($key, $data, $group, $expire);
}

function wp_cache_set($key, $data, $group = "", $expire = 0) {
    global $wp_object_cache;
    return $wp_object_cache->set($key, $data, $group, $expire);
}

function wp_cache_get($key, $group = "", $force = false, &$found = null) {
    global $wp_object_cache;
    return $wp_object_cache->get($key, $group, $force, $found);
}

function wp_cache_delete($key, $group = "") {
    global $wp_object_cache;
    return $wp_object_cache->delete($key, $group);
}

function wp_cache_flush() {
    global $wp_object_cache;
    return $wp_object_cache->flush();
}
';
    }
}
