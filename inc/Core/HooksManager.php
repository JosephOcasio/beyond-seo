<?php /** @noinspection PhpRedundantOptionalArgumentInspection */
/** @noinspection PhpUnusedParameterInspection */
declare( strict_types=1 );

namespace RankingCoach\Inc\Core;

if ( !defined('ABSPATH') ) {
    exit;
}

use ActionScheduler;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Adapters\WordPressProvider;
use Doctrine\Persistence\Mapping\MappingException;
use Exception;
use RankingCoach\Inc\Core\Admin\AdminManager;
use RankingCoach\Inc\Core\Api\User\UserApiManager;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Base\Traits\RcInstanceCreatorTrait;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\DB\DatabaseManager;
use RankingCoach\Inc\Core\Frontend\FrontendManager;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Core\Initializers\Hooks;
use RankingCoach\Inc\Core\Jobs\AccountSyncJob;
use RankingCoach\Inc\Core\Jobs\BrokenLinkCheckerJob;
use RankingCoach\Inc\Core\Jobs\LogCleanupJob;
use RankingCoach\Inc\Core\Jobs\SyncKeywordsJob;
use RankingCoach\Inc\Core\Jobs\WpConfigCronEnablerJob;
use RankingCoach\Inc\Core\Rest\RestManager;
use RankingCoach\Inc\Core\Security\SecurityManager;
use RankingCoach\Inc\Core\Settings\SettingsManager;
use RankingCoach\Inc\Core\Sitemap\Sitemap;
use RankingCoach\Inc\Exceptions\ExceptionHandler;
use RankingCoach\Inc\Integrations\Elementor\ElementorIntegration;
use RankingCoach\Inc\Integrations\Gutenberg\GutenbergIntegration;
use RankingCoach\Inc\Interfaces\MetaHeadBuilderInterface;
use RankingCoach\Inc\Modules\ModuleManager;
use RankingCoach\Inc\Traits\ConfigManager;
use RankingCoach\Inc\Traits\HookManager;
use ReflectionException;
use Throwable;
use WP_Upgrader;

/**
 * Class HooksActionsManager
 *
 * This class is responsible for managing hook actions.
 */
class HooksManager
{

    use RcLoggerTrait;
    use HookManager;
    use ConfigManager;
    use RcInstanceCreatorTrait;

    /**
     * After the plugin is loaded
     * @return void
     * @throws Exception
     */
    public function pluginLoaded(): void
    {
        if (!defined('RANKINGCOACH_VERSION')) {
            return;
        }

        $this->loadTextDomain();

        // Set the plugin security headers
        new SecurityManager();

        // Load early custom integrations
        $this->loadCustomIntegrations();

        add_action('plugins_loaded', [$this, 'runInstalledModules'], 1);
        add_action('plugins_loaded', [$this, 'loadNotifications'], 1);
        add_action('plugins_loaded', [$this, 'addRankingCoachColumnOnTables'], 1);
        add_action('plugins_loaded', [$this, 'runDbMigration'], 1);

        // disable render meta viewport
        add_filter('rankingcoach_viewport_enabled', '__return_false');

        // this happens only if the onboarding is completed
        if (WordpressHelpers::isOnboardingCompleted()) {
            add_action('plugins_loaded', [$this, 'initializeSitemap'], 1);
            add_action('plugins_loaded', [$this, 'pageAndPostSessionHandlers'], 1);
            add_action('plugins_loaded', [$this, 'initializeUrlChangeDetector'], 1);
            // Initialize jobs after ActionScheduler is ready (init hook with priority 15)
            if(class_exists('ActionScheduler')) {
                add_action('init', [$this, 'initializeKeywordSynchronization'], 15);
                add_action('init', [$this, 'initializeWpCronEnabler'], 15);
                add_action('init', [$this, 'initializeBrokenLinkChecker'], 15);
                add_action('init', [$this, 'initializeLogCleanupJob'], 15);
                add_action('init', [$this, 'initializeAccountSyncJob'], 15);
            }

            // This hook is based on "handleUpselling", which provides the upsell URL to the client,
            // and redirects them to the upsell page in the partner account, when the force check option is set.
            // If the force check option is enabled, 5 minutes after "handleUpselling", the "upsellCheckAccount" method is called to fetch account details.
            // If the account details have changed, it means the subscription has also changed in rC side.
            if (get_option(BaseConstants::OPTION_UPSELL_FORCE_CHECK)) {
                add_action('admin_init', [$this, 'upsellCheckAccount'], 15);
            }
        }
    }

    /**
     * Force account sync after upsell with retry logic.
     * Attempts sync twice with 5-minute intervals, then gives up and waits for scheduled sync.
     *
     * @return void
     */
    public function upsellCheckAccount(): void
    {
        $currentTime = time();
        $retryCount = (int) get_option(BaseConstants::OPTION_UPSELL_RETRY_COUNT, 0);
        $lastCheckTimestamp = (int) get_option(BaseConstants::OPTION_UPSELL_LAST_CHECK_TIMESTAMP, 0);

        // Wait 5 minutes between attempts
        if (($currentTime - $lastCheckTimestamp) < 300) { // 300 seconds = 5 minutes
            return;
        }

        // After 2 attempts, stop force sync and wait for scheduled sync
        if ($retryCount >= 2) {
            update_option(BaseConstants::OPTION_UPSELL_FORCE_CHECK, false, true);
            update_option(BaseConstants::OPTION_UPSELL_RETRY_COUNT, 0);
            update_option(BaseConstants::OPTION_UPSELL_LAST_CHECK_TIMESTAMP, $currentTime);

            $this->log_json([
                'operation_type' => 'upsell_force_sync',
                'operation_status' => 'max_retries_reached',
                'context_entity' => 'hooks_manager',
                'context_type' => 'upsell_check',
                'retry_count' => $retryCount,
                'message' => 'Max retries reached, disabling force sync',
                'timestamp' => current_time('mysql')
            ], 'upsell_check');
            return;
        }

        // Attempt force sync using AccountSyncJob
        $accountSyncJob = AccountSyncJob::instance();
        $syncSuccess = $accountSyncJob->forceSync();

        // Update timestamps and retry count
        update_option(BaseConstants::OPTION_UPSELL_LAST_CHECK_TIMESTAMP, $currentTime);

        if ($syncSuccess) {
            // Sync successful - AccountSyncJob will handle resetting upsell flags
            $this->log_json([
                'operation_type' => 'upsell_force_sync',
                'operation_status' => 'success',
                'context_entity' => 'hooks_manager',
                'context_type' => 'upsell_check',
                'retry_count' => $retryCount,
                'message' => 'Force sync successful',
                'timestamp' => current_time('mysql')
            ], 'upsell_check');
        } else {
            // Sync failed - increment retry count
            $retryCount++;
            update_option(BaseConstants::OPTION_UPSELL_RETRY_COUNT, $retryCount);

            $this->log_json([
                'operation_type' => 'upsell_force_sync',
                'operation_status' => 'failed',
                'context_entity' => 'hooks_manager',
                'context_type' => 'upsell_check',
                'retry_count' => $retryCount,
                'message' => 'Force sync failed, will retry',
                'timestamp' => current_time('mysql')
            ], 'upsell_check');
        }
    }

    /**
     * Initialize URL change detection system
     * @return void
     */
    public function initializeUrlChangeDetector(): void
    {
        UrlChangeDetector::instance()->boot();
    }

    /**
     * Initialize the Sitemap functionality.
     *
     * @return void
     */
    public function initializeSitemap(): void
    {
        // Initialize sitemap functionality
        $sitemap = new Sitemap();
        $sitemap->init();
    }

    /**
     * Initialize the PostEventsManager to handle post save events.
     *
     * @return void
     */
    public function pageAndPostSessionHandlers(): void
    {
        // Initialize handlers for page/post save events
        PostEventsManager::instance()->initialize();

        // Register SEO analysis hooks
        $this->registerSeoAnalysisHooks();
    }

    /**
     * Register hooks for SEO analysis.
     *
     * @return void
     */
    public function registerSeoAnalysisHooks(): void
    {
        // Register the filter for retrieving SEO analysis
        add_filter(
            Hooks::RANKINGCOACH_ACTION_RETRIEVE_SEO_ANALYSIS,
            [$this, 'retrieveSeoAnalysis'],
            10,
            4
        );

        // Register the filter for analyzing SEO
        add_filter(
            Hooks::RANKINGCOACH_ACTION_ANALYZE_SEO,
            [$this, 'analyzeSeo'],
            10,
            5
        );

        // Register the filter for throttling SEO analysis
        add_filter(
            Hooks::RANKINGCOACH_FILTER_SHOULD_THROTTLE_SEO_ANALYSIS,
            [$this, 'shouldThrottleSeoAnalysis'],
            10,
            2
        );

        // Register the filter for global SEO metrics
        add_filter(
            Hooks::RANKINGCOACH_FILTER_GLOBAL_SEO_METRICS,
            [$this, 'getGlobalSeoMetrics'],
            10,
            1
        );
    }

    /**
     * Register components that need to be available early in the WordPress lifecycle.
     * @return void
     * @throws Exception
     */
    public function registerEarlyComponents(): void
    {
        $this->registerAutoUpdater();
    }

    /**
     * Initialize the plugin.
     * @return void
     * @throws Exception
     */
    public function pluginInit(): void
    {
        $this->registerExceptionHandlers();

        // === Onboarding Questions ===
        // Just for translation purposes
        $default_questions = [
            __("Let's get started.", 'beyond-seo'),
            __('First, could you tell me what your website or project is about?', 'beyond-seo'),
            __('Awesome!', 'beyond-seo'),
            __('Do you already have a name for your website, project, or business?', 'beyond-seo'),
            __('Wonderful!', 'beyond-seo'),
            __('Could you describe in more detail what you plan to do with your website? For example, will you offer products or services, share blog articles, or something else?', 'beyond-seo'),
            __('Just tasty! Thanks for sharing!', 'beyond-seo'),
            __('Is your project or business tied to a specific location? Do you serve customers locally, or operate in multiple areas?', 'beyond-seo'),
            __('I see.', 'beyond-seo'),
            __("Where do you primarily want to focus your reach? Is there a particular city or region you'd like to target, or do you want to go nationwide?", 'beyond-seo'),
            __('Thanks for providing that!', 'beyond-seo'),
            __("Lastly, is there anything else you'd like to highlight about your project or business, something that makes it unique or special?", 'beyond-seo')
        ];
    }

    /**
     * After the plugin is initialized, priority 1 is load_head_metatags
     * @return void
     * @throws Exception
     */
    public function loadHeadMetatags(): void
    {
        // Early return if onboarding not completed
        if (!WordpressHelpers::isOnboardingCompleted(true, true)) {
            return;
        }

        if (WordpressHelpers::is_admin_request()) {
            return;
        }

        $modules = [];

        /** @var HeadManager $head */
        $head = HeadManager::getInstance();
        // This includes all the meta-modules that can add meta-tags to the head section.
        /** @var MetaModulesManager $metaModulesManager */
        $metaModulesManager = MetaModulesManager::getInstance();
        // Use just to retrieve the instance of any module want
        /** @var ModuleManager $moduleManager */
        $moduleManager = ModuleManager::instance();

        foreach ($metaModulesManager->getModules() as $module) {
            // retrieve the instance of a module and add its meta-tags to the head section.

            // This just returns the module instance based on his name
            /** @var MetaHeadBuilderInterface $moduleInstance */
            $moduleInstance = $moduleManager->get_module($module);

            if (!$moduleInstance instanceof MetaHeadBuilderInterface) {
                continue;
            }

            $metaTags = method_exists($moduleInstance, 'generateMetaTags') ? $moduleInstance->generateMetaTags() : null;
            if (empty($metaTags)) {
                continue;
            }

            $priority = method_exists($moduleInstance, 'getMetaTagsPriority') ? $moduleInstance->getMetaTagsPriority() : 10;
            $modules[] = [
                'metaTags' => $metaTags,
                'priority' => $priority
            ];
        }

        // Sort by priority (lowest first)
        usort($modules, fn($a, $b) => $a['priority'] <=> $b['priority']);

        foreach ($modules as $moduleData) {
            try {
                if ($moduleData['metaTags']) {
                    // Add the meta-tags to the head section
                    $head->addHeaderElement($moduleData['metaTags']);
                }
            } catch (Throwable) {
                // Log error or ignore silently
            }
        }
    }

    /**
     * Init the REST API.
     * @return void
     * @throws Exception
     */
    public function initRestApi(): void
    {
        add_filter('rest_pre_serve_request', function ($value) {
            $allowFullCors = defined('RANKINGCOACH_JWT_AUTH_CORS_ENABLE') && RANKINGCOACH_JWT_AUTH_CORS_ENABLE;
            // Force CORS headers for REST API requests
            RestManager::wpFullCorsHeaders($allowFullCors);
            return $value;
        });
        $this->adminRestApiInit();
    }

    /**
     * Admin init.
     * @return void
     * @throws Exception
     */
    public function adminInit(): void
    {
        // Initialize the Admin Manager
        AdminManager::getInstance()->init();

        // Load the Dev tools only in local or staging environments
        if (
            wp_get_environment_type() !== 'production' &&
            file_exists(RANKINGCOACH_PLUGIN_DIR . 'tools/Dev/bootstrap.php')
        ) {
            require_once RANKINGCOACH_PLUGIN_DIR . 'tools/Dev/bootstrap.php';
        }
    }

    /**
     * Frontend init.
     * @return void
     * @throws Exception
     */
    public function frontendInit(): void
    {
        /** @var FrontendManager $frontendManager */
        $frontendManager = self::getInstance(FrontendManager::class);
        $frontendManager->init();
    }

    /**
     * Runs all installed modules.
     *
     * This method retrieves the instance of ModuleManager and
     * calls the runModules method to execute all installed modules.
     *
     * @return void
     * @throws Exception
     */
    public function runInstalledModules(): void
    {
        // run modules
        /** @var ModuleManager $moduleManager */
        $moduleManager = ModuleManager::instance();
        $moduleManager->run_modules();
    }

    /**
     * Loads all stored notifications from the storage mechanism.
     */
    public function loadNotifications(): void
    {
        // load all stored notifications
        NotificationManager::instance()->get_from_storage();
    }

    /**
     * Manages the keyword limit notification based on remaining keywords count.
     * Shows notification when remaining keywords count becomes negative.
     * Removes notification when count is >= 0.
     */
    private function manageKeywordLimitNotification(): void
    {
        $remainingKeywords = (int) get_option(BaseConstants::OPTION_SYNC_KEYWORDS_REMAINS_KEYWORDS, 99999);
        $notificationId = 'keyword_limit_exceeded';

        /** @var NotificationManager $notificationManager */
        $notificationManager = NotificationManager::instance();

        if ($remainingKeywords < 0) {
            // Show notification if remaining keywords is negative and notification doesn't exist
            if (!$notificationManager->has_notification($notificationId)) {
                $upgradeUrl = esc_url( AdminManager::getPageUrl(AdminManager::PAGE_UPSELL) );

                $message = sprintf(
                /* translators: %1$s: opening link tag, %2$s: closing link tag */
                    __('You have reached your keyword tracking limit. %1$sUpgrade your plan%2$s to continue tracking more keywords and improve your SEO performance.', 'beyond-seo'),
                    '<a href="' . esc_url($upgradeUrl) . '" class="button button-primary" style="margin-left: 10px;">',
                    '</a>'
                );

                $notificationManager->add($message, [
                    'id' => $notificationId,
                    'type' => Notification::WARNING,
                    'screen' => Notification::SCREEN_ANY,
                    'capability' => 'manage_options',
                    'classes' => 'rankingcoach-keyword-limit-notification'
                ]);
            }
        }
        elseif ($notificationManager->has_notification($notificationId)) {
            $notificationManager->remove_by_id($notificationId);
        }
    }

    /**
     * Initializes the plugin.
     * @return void
     * @throws Exception
     */
    public function addRankingCoachColumnOnTables(): void
    {
        /** @var ContentsManager $contentsManager */
        $contentsManager = ContentsManager::getInstance();
        $contentsManager->addRankingCoachColumnOnTables();
    }

    /**
     * Loads the text domain for the plugin.
     * @throws Exception
     */
    public function loadTextDomain(): void
    {
        // Only load text domain during or after the init hook
        if (!did_action('plugins_loaded')) {
            // Schedule text domain loading for the init hook with priority 10 (default)
            add_action('plugins_loaded', [$this, 'loadTextDomainOnInit'], 10);
            return;
        }

        $this->loadTextDomainOnInit();
    }

    /**
     * Loads the text domain during the init hook.
     * This ensures text domain loading happens at the appropriate time.
     */
    public function loadTextDomainOnInit(): void
    {
        $domain = 'rankingcoach';
        $locale = determine_locale();
        $locale = apply_filters('plugin_locale', $locale, $domain);

        $customMo = WP_LANG_DIR . '/plugins/' . $domain . '-' . $locale . '.mo';

        if (file_exists($customMo)) {
            unload_textdomain($domain);
            load_textdomain($domain, $customMo);
        } else {
            load_plugin_textdomain($domain, false, dirname(plugin_basename(RANKINGCOACH_FILE)) . '/languages');
        }
    }

    /**
     * Initializes the Admin REST API.
     * @return void
     */
    public function adminRestApiInit(): void
    {
        // register rest api routes
        /** @var RestManager $restApiManager */
        $restApiManager = new RestManager();
        $restApiManager->registerLegacyRoutes();
        $restApiManager->registerMetaToRestResponse();
    }

    /**
     * Register the exception handlers hooks.
     * @return void
     */
    public function registerExceptionHandlers(): void
    {
        ExceptionHandler::registerErrorHooks(plugin_basename(RANKINGCOACH_FILE));
    }

    /**
     * Registers the auto-updater functionality.
     * @return void
     * @throws MappingException
     */
    public function registerAutoUpdater(): void
    {
        /** @var CustomVersionLoader $autoUpdater */
        CustomVersionLoader::getInstance(RANKINGCOACH_PLUGIN_BASENAME);
    }

    /**
     * Loads custom integrations.
     * @return void
     */
    public function loadCustomIntegrations(): void
    {
        if (did_action('elementor/loaded')) {
            new ElementorIntegration();
        }

        if ( function_exists( 'register_block_type' ) && version_compare( get_bloginfo( 'version' ), '5.0', '>=' ) ) {
            new GutenbergIntegration();
        }
    }

    /**
     * Run database migrations if needed.
     *
     * @return void
     */

    public function runDbMigration(): void
    {
        $currentDbVersion = get_option(BaseConstants::OPTION_DB_VERSION, '1.0.0');
        if (version_compare(DatabaseManager::getInstance()->getDbVersion(), $currentDbVersion, '>')) {
            try {
                DatabaseManager::getInstance()->runMigrations();
                update_option(BaseConstants::OPTION_DB_VERSION, DatabaseManager::getInstance()->getDbVersion() );
            } catch (Throwable $e) {
                $this->log('Error running database migrations: ' . $e->getMessage(), 'ERROR');
            }
        }
    }

    /**
     * Handle plugin update
     *
     * @param WP_Upgrader $upgrader The WP_Upgrader instance
     * @param array $hook_extra The extra arguments passed to the update method
     */
    public function pluginUpgraderProcessComplete(WP_Upgrader $upgrader, array $hook_extra = []): void
    {
        // Check if our plugin was updated
        if ($hook_extra['action'] === 'update' && $hook_extra['type'] === 'plugin') {
            // Get the plugin basename
            $plugin_basename = plugin_basename(RANKINGCOACH_FILE);

            // Check if our plugin is in the list of updated plugins
            if (isset($hook_extra['plugins']) && in_array($plugin_basename, $hook_extra['plugins'])) {

                // Rebuild the operation-factor-context mapping cache
                FeatureFlagManager::getInstance()->rebuildOperationFactorMapCache();

                // Sync new settings from WPSettings without altering existing values
                SettingsManager::instance()->syncNewSettingsFromDefaults();

                // Clear all notifications
                NotificationManager::instance()?->removeAllNotifications();

                // Check for database migrations
                try {
                    DatabaseManager::getInstance()->runMigrations();
                } catch (Throwable $e) {
                    $this->log('Error running database migrations: ' . $e->getMessage(), 'ERROR');
                }
            }
        }
    }

    /**
     * Retrieve SEO analysis for a post.
     *
     * @param int $postId The post ID to analyze
     * @param array $params Additional parameters for analysis
     * @return mixed The SEO analysis result or the default value
     */
    public function retrieveSeoAnalysis(int $postId, array $params = []): mixed
    {
        try {

            // Logic for retrieving SEO analysis

        } catch (Throwable $e) {
            // Log the error
            $this->log('Error retrieving SEO analysis: ' . $e->getMessage(), 'ERROR');
        }
        return null;
    }

    /**
     * Analyze SEO for a post.
     *
     * @param int $postId The post ID to analyze
     * @param array $params Additional parameters for analysis
     * @param bool $useCache Whether to use cached data
     * @return mixed The SEO analysis result or the default value
     */
    public function analyzeSeo(int $postId, array $params = [], bool $useCache = true): mixed
    {
        try {

            // Logic for analyzing SEO

        } catch (Throwable $e) {
            // Log the error
            $this->log('Error analyzing SEO: ' . $e->getMessage(), 'ERROR');
        }
        return null;
    }

    /**
     * Check if SEO analysis should be throttled.
     *
     * @param bool $default The default throttle value
     * @param int $postId The post ID to check
     * @return bool True if analysis should be throttled, false otherwise
     */
    public function shouldThrottleSeoAnalysis(bool $default, int $postId): bool
    {
        try {
            // Use the WordPressProvider to check if analysis should be throttled
            return WordPressProvider::shouldThrottleAnalysis($postId);
        } catch (Throwable $e) {
            // Log the error
            $this->log('Error checking if SEO analysis should be throttled: ' . $e->getMessage(), 'ERROR');
            return $default;
        }
    }

    /**
     * Get global SEO metrics from wp_options.
     *
     * @param array $default The default metrics array
     * @return array{
     *     average_score: float|null,
     *     pages_count: int|null,
     *     min_score: float|null,
     *     max_score: float|null
     * } Array containing the global SEO metrics
     */
    public function getGlobalSeoMetrics(array $default = []): array
    {
        try {
            return $this->retrieveGlobalSeoMetrics();
        } catch (Throwable $e) {
            // Log the error
            $this->log('Error retrieving global SEO metrics: ' . $e->getMessage(), 'ERROR');
            return $default;
        }
    }

    /**
     * Retrieve global SEO metrics from wp_options
     *
     * @return array{
     *     average_score: float|null,
     *     pages_count: int|null,
     *     min_score: float|null,
     *     max_score: float|null
     * } Array containing the global SEO metrics
     */
    public function retrieveGlobalSeoMetrics(): array
    {
        $getOptionAsFloat = function (string $optionName): ?float {
            $value = get_option($optionName, null);
            return $value !== null && $value !== false ? (float)$value : null;
        };

        $getOptionAsInt = function (string $optionName): ?int {
            $value = get_option($optionName, null);
            return $value !== null && $value !== false ? (int)$value : null;
        };
        return [
            'average_score' => $getOptionAsFloat(BaseConstants::OPTION_ANALYSIS_WEBSITE_SCORE_AVERAGE),
            'pages_count' => $getOptionAsInt(BaseConstants::OPTION_ANALYSIS_WEBSITE_PAGES_COUNT),
            'min_score' => $getOptionAsFloat(BaseConstants::OPTION_ANALYSIS_SCORE_MIN),
            'max_score' => $getOptionAsFloat(BaseConstants::OPTION_ANALYSIS_SCORE_MAX),
        ];
    }

    /**
     * Ensure Action Scheduler is loaded for async tasks. Safe if missing.
     */
    public function ensureActionSchedulerLoaded(): void
    {
        if (function_exists('as_enqueue_async_action') || function_exists('as_schedule_single_action')) {
            return;
        }

        // Try to load bundled Action Scheduler
        $as_path = RANKINGCOACH_PLUGIN_DIR . 'inc/Core/Plugin/action-scheduler/action-scheduler.php';
        if (file_exists($as_path)) {
            require_once $as_path;
            // In most cases Action Scheduler bootstraps itself on include.
            if (class_exists('ActionScheduler') && method_exists('ActionScheduler', 'init')) {
                try {
                    ActionScheduler::init(
                        defined('RANKINGCOACH_PLUGIN_BASENAME') ? RANKINGCOACH_PLUGIN_BASENAME : plugin_basename(RANKINGCOACH_FILE)
                    );
                } catch (Throwable $e) {
                    // Silently ignore; we provide WP-Cron fallback elsewhere
                    rclh('Action Scheduler init failed: ' . $e->getMessage(), 'WARNING');
                }
            }
        }
    }

    public function initializeLogCleanupJob(): void {
        try {
            // Initialize the log cleanup job
            LogCleanupJob::instance()->initialize();
        } catch (Throwable $e) {
            // Log the error if the job instance cannot be created
            $this->log('Failed to initialize log cleanup job: ' . $e->getMessage(), 'ERROR');
        }
    }

    /**
     * Initialize keyword synchronization using the dedicated job class.
     * This method is only called when onboarding is completed and runs during plugins_loaded.
     *
     * @return void
     */
    public function initializeKeywordSynchronization(): void
    {
        try {
            SyncKeywordsJob::instance()->initialize();
            // Check and manage keyword limit notification
            $this->manageKeywordLimitNotification();
        } catch (Throwable $e) {
            $this->log('Failed to initialize keyword synchronization job: ' . $e->getMessage(), 'ERROR');
        }
    }

    /**
     * Initialize the WP-Cron Enabler job.
     * This method sets up the job to ensure WP-Cron is enabled and scheduled correctly.
     *
     * @return void
     */
    public function initializeWpCronEnabler(): void
    {
        try {
            // Initialize the job (sets up hooks and scheduling)
            WpConfigCronEnablerJob::instance()->initialize();
        } catch (Throwable $e) {
            // Log the error if the job instance cannot be created
            $this->log('Failed to initialize WP-Cron-Enabler job: ' . $e->getMessage(), 'ERROR');
        }
    }

    /**
     * Initialize the Broken Link Checker job.
     * This method sets up the job to perform scheduled link status verification across all posts and pages.
     *
     * @return void
     */
    public function initializeBrokenLinkChecker(): void
    {
        try {
            // Initialize the job (sets up hooks and scheduling)
            BrokenLinkCheckerJob::instance()->initialize();
        } catch (Throwable $e) {
            // Log the error if the job instance cannot be created
            $this->log('Failed to initialize Broken Link Checker job: ' . $e->getMessage(), 'ERROR');
        }
    }

    /**
     * Initialize the Account Sync job.
     * This method sets up the job to perform scheduled synchronization of customer account data.
     *
     * @return void
     */
    public function initializeAccountSyncJob(): void
    {
        try {
            // Initialize the job (sets up hooks and scheduling)
            AccountSyncJob::instance()->initialize();
        } catch (Throwable $e) {
            // Log the error if the job instance cannot be created
            $this->log('Failed to initialize Account Sync job: ' . $e->getMessage(), 'ERROR');
        }
    }

    /**
     * Initialize the asynchronous retrieval of translated categories.
     * This method sets up an async job to fetch and store translated categories without blocking the main thread.
     *
     * @return void
     */
    public function initializeRetrieveTranslatedCategories(): void
    {
        $hook = 'rankingcoach/async_insert_translated_categories';
        $this->ensureActionSchedulerLoaded();
        // --------------- ASYNC JOB ----------------
        if (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action($hook, [], RANKINGCOACH_BRAND_NAME);
        } elseif (function_exists('as_schedule_single_action')) {
            as_schedule_single_action(time(), $hook, [], RANKINGCOACH_BRAND_NAME);
        } else {
            if (!wp_next_scheduled($hook)) {
                wp_schedule_single_event(time(), $hook, []);
            } else {
                do_action($hook);
            }
        }
        // --------------- ASYNC JOB ----------------
    }
}
