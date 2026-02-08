<?php /** @noinspection PhpRedundantOptionalArgumentInspection */
/** @noinspection PhpUnusedParameterInspection */
declare( strict_types=1 );

namespace RankingCoach\Inc\Core\Initializers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use ActionScheduler;
use ActionScheduler_Store;
use Exception;
use RankingCoach\Inc\Core\Admin\AdminManager;
use RankingCoach\Inc\Core\CustomVersionLoader;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Base\Traits\RcInstanceCreatorTrait;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\CapabilityManager;
use RankingCoach\Inc\Core\ConflictManager;
use RankingCoach\Inc\Core\CronJobManager;
use RankingCoach\Inc\Core\DB\DatabaseManager;
use RankingCoach\Inc\Core\DB\DatabaseTablesManager;
use RankingCoach\Inc\Core\FeatureFlagManager;
use RankingCoach\Inc\Core\Helpers\CoreHelper;
use RankingCoach\Inc\Core\HooksManager;
use RankingCoach\Inc\Core\NotificationManager;
use RankingCoach\Inc\Core\PluginConfiguration;
use RankingCoach\Inc\Core\RobotsTxt\RobotsTxtManager;
use RankingCoach\Inc\Core\Rss\FeedManager;
use RankingCoach\Inc\Core\Settings\SettingsManager;
use RankingCoach\Inc\Core\Plugin\MuPluginManager;
use RankingCoach\Inc\Core\TokensManager;
use RankingCoach\Inc\Core\UrlChangeDetector;
use RankingCoach\Inc\Interfaces\InitializerInterface;
use RankingCoach\Inc\Modules\ModuleManager;
use RankingCoach\Inc\Traits\ConfigManager;
use RankingCoach\Inc\Traits\HookManager;
use ReflectionException;
use Throwable;
use WP_Application_Passwords;
use WP_User;

/**
 * Class Installer
 *
 * Handles plugin installation, activation, and deactivation processes.
 * This class is responsible for setting up database tables, registering hooks,
 * managing application passwords, and handling multisite installations.
 *
 * @since 1.0.0
 */
class Installer implements InitializerInterface
{
    use ConfigManager;
    use HookManager;
    use RcInstanceCreatorTrait;
    use RcLoggerTrait;
    
    /**
     * Plugin activation hook name
     */
    private const ACTION_PLUGIN_ACTIVATED = 'activated_plugin';

    /**
     * Plugin deactivation hook name
     */
    private const ACTION_PLUGIN_DEACTIVATED = 'deactivated_plugin';


    /**
     * Initializes the plugin hooks and registers activation/deactivation handlers.
     *
     * This method sets up all necessary hooks for the plugin to function properly.
     * It registers activation and deactivation hooks, as well as hooks for reacting
     * to other plugins' activation and deactivation events.
     *
     * @return void
     * @throws ReflectionException If there's an issue with reflection during initialization
     * @throws Throwable
     */
    public function initialize(): void {

        // Initialize internal hooks and filters
        $this->initializeHooks();

        add_action('plugins_loaded',  [$this, 'maybeActivateOnce'], 10);

        // Register activation/deactivation hooks
        // These run in an isolated environment with limited WordPress access
        // Exceptions will prevent plugin activation/deactivation
        register_activation_hook(RANKINGCOACH_FILE, [$this, 'maybeActivateOnce']);
        register_deactivation_hook(RANKINGCOACH_FILE, [$this, 'deactivationOnce']);

        // Register plugin lifecycle event listeners
        // These run in the normal WordPress flow with full environment access
        // Exceptions won't prevent plugin activation/deactivation
        add_action(self::ACTION_PLUGIN_ACTIVATED, [$this, 'handlePluginActivation']);
        add_action(self::ACTION_PLUGIN_DEACTIVATED, [$this, 'handlePluginDeactivation']);

        // Register uninstall hook
        // This runs when the plugin is uninstalled, not just deactivated
        register_uninstall_hook(RANKINGCOACH_FILE, [Installer::class, 'handleUninstall']);
    }

    /**
     * Initializes all the plugin's internal hooks and filters.
     *
     * This method sets up various hooks and filters for the plugin's functionality:
     * - Initializes the HooksManager
     * - Registers default settings
     * - Sets up filters for account details and keyword management
     * - Registers actions for database operations and data collection
     * - Initializes the admin manager for form submissions
     * - Sets up cron jobs for regular tasks
     *
     * @return void
     * @throws ReflectionException If there's an issue with reflection during initialization
     * @throws Throwable
     */
    public function initializeHooks(): void {
        // Register default settings
        $this->registerDefaultSettings();

        // Initialize AutoUpdater early to ensure version tracking works in all contexts
        $this->initializeAutoUpdater();

        // Initialize the hook actions manager
        (new Hooks(new HooksManager()))->initialize();

        // Register core action handlers
        $this->registerCoreActionHandlers();
        
        // Initialize admin components
        $this->initializeAdminComponents();
        
        // Configure and initialize cron jobs
        $this->initializeCronJobs();

        // Initialize the RSS feed manager
        $this->initializeRssFeedManager();

        // Initialize the RobotsTxt manager
        $this->initializeRobotsTxtManager();

        // Initialize heartbeat service management
        $this->initializeHeartbeatService();
    }
    
    /**
     * Registers the default plugin settings.
     * 
     * @return void
     */
    private function registerDefaultSettings(): void {
        // Register the default settings saved in the database
        // If that setting is not in the database, it will be added
        // If it is in the database, it will not be overwritten
        SettingsManager::instance()->registerDefaultSettings();
    }

    /**
     * Initializes the AutoUpdater to ensure version tracking works in all contexts.
     * 
     * @return void
     */
    private function initializeAutoUpdater(): void {
        try {
            // Initialize AutoUpdater early to ensure it's available in all update contexts
            CustomVersionLoader::getInstance(RANKINGCOACH_PLUGIN_BASENAME);
        } catch (Exception $e) {
            $this->log('Failed to initialize AutoUpdater: ' . $e->getMessage(), 'ERROR');
        }
    }

    /**
     * Registers handlers for core plugin actions.
     *
     * @return void
     * @throws Throwable
     */
    private function registerCoreActionHandlers(): void {

        // Define action for creating tables and fetching account details
        add_action(Hooks::RANKINGCOACH_ACTION_COLLECT_DATA_FROM_ALL_AVAILABLE_COLLECTORS, function($context) {
            try {
                // $context means 'settings' or 'activation'
                // UserApiManager::collectDataFromCollectors();
            } catch (Exception $e) {
                $this->log('Error while collecting data from all available collectors. Exception: ' . $e->getMessage(), 'ERROR');
            }
        });
    }
    
    /**
     * Initializes admin-related components.
     * 
     * @return void
     * @throws ReflectionException
     */
    private function initializeAdminComponents(): void {
        // AdminManager class handles all possible form submissions
        /** @var AdminManager $adminManager */
        $adminManager = self::getInstance(AdminManager::class);
        $adminManager->processAllFormSubmissions();
    }
    
    /**
     * Configures and initializes cron jobs.
     * 
     * @return void
     * @throws Exception
     */
    private function initializeCronJobs(): void {
        // Initialize Cron jobs manager
        CronJobManager::instance()->initialize();
    }

    /**
     * Activates the plugin.
     *
     * This method is called directly by WordPress when the plugin is activated.
     * It runs in an isolated environment with limited WordPress access.
     * Any exceptions will prevent plugin activation and be displayed to the user.
     *
     * @param bool $entireNetwork Whether to activate for the entire network in multisite
     * @return void
     * @throws Exception If activation fails
     */
    public function activationOnce(bool $entireNetwork = false): void {
        if (!is_multisite() || !$entireNetwork) {
            $this->activateSingleSite();
            return;
        }
        $this->networkActivateDeactivate(true);
    }

    /**
     * Handles plugin activation events.
     *
     * This method is called whenever any plugin is activated in WordPress.
     * It runs in the normal WordPress flow with full environment access.
     * Exceptions won't prevent plugin activation since this runs after activation is complete.
     *
     * @param string $plugin The path to the plugin file relative to the plugins directory
     * @return void
     * @throws Exception If an error occurs during post-activation processing
     */
    public function handlePluginActivation(string $plugin): void {
        // Check for conflicts and add notifications when any plugin is activated
        ConflictManager::getInstance()->addConflictNotification($plugin);

        // If it's not our plugin being activated, we're done
        if ($plugin !== RANKINGCOACH_PLUGIN_BASENAME) {
            return;
        }
    }

    /**
     * Activates the plugin for a single site.
     *
     * This method handles the core activation process for a single WordPress site.
     * It sets up capabilities, initializes modules, and configures plugin options.
     *
     * @return void
     * @throws Exception If activation fails
     */
    private function activateSingleSite(): void {

        // Create database tables and fetch account details
        try {
            $dbm = DatabaseManager::getInstance();
            $dbm->createAllTables();
        } catch (Exception $e) {
            // Log the error and prevent activation
            $this->log('Error during plugin activation: ' . $e->getMessage(), 'ERROR');
            /* translators: %s: error message */
            throw new Exception(sprintf(('Plugin activation failed: %s'), esc_html($e->getMessage())));
        }

        // Check and update default settings with any new defaults
        SettingsManager::instance()->syncNewSettingsFromDefaults();

        // Check for plugin conflicts
        $this->checkForPluginConflicts();
        
        // Set up user capabilities
        $this->setupUserCapabilities();

        // Rebuild the operation-factor mapping cache
        $this->rebuildOperationFactorCache();

        // Initialize plugin modules
        $this->initializePluginModules();

        // Set up plugin options
        $this->setupPluginOptions();

        // Configure application password
        $this->setupApplicationPassword();

        // Set up must-use plugin
        // MuPluginManager::setupDebugAccessPlugin();

        // Initialize URLChangeDetector baseline
        UrlChangeDetector::instance()->initializeOriginBaseline();

        // TODO: ====================================================================
        // TODO: IONOS environment setup as default until plugin becomes public
        // TODO: Remove this block before public release
        // TODO: ====================================================================
        // update_option('ionos_group_brand', 'ionos', false);
        // update_option('ionos_market', 'de', false);
    }

    /**
     * Rebuild the operation-factor mapping cache
     */
    private function rebuildOperationFactorCache(): void
    {
        FeatureFlagManager::getInstance()->rebuildOperationFactorMapCache();
    }
    
    /**
     * Checks for conflicts with other plugins.
     * 
     * @return void
     */
    private function checkForPluginConflicts(): void {
        ConflictManager::getInstance()->checkPluginConflicts();
    }
    
    /**
     * Sets up user capabilities for the plugin.
     * 
     * @return void
     */
    private function setupUserCapabilities(): void {
        // Add capabilities for three user roles (admin, editor, author)
        CapabilityManager::instance()->assignCapabilities();
    }
    
    /**
     * Initializes all plugin modules.
     * 
     * @return void
     * @throws Exception If module initialization fails
     */
    private function initializePluginModules(): void {
        ModuleManager::instance()->initialize();
    }
    
    /**
     * Sets up core plugin options.
     * 
     * @return void
     */
    public function setupPluginOptions(): void {
        // Set installation date if not already set
        if (false === boolval(get_option(BaseConstants::OPTION_INSTALLATION_DATE))) {
            update_option(BaseConstants::OPTION_INSTALLATION_DATE, current_time('timestamp'));
        }

        $pluginVersion = get_plugin_data( WP_PLUGIN_DIR . '/' . plugin_basename(RANKINGCOACH_PLUGIN_BASENAME), false, false )['Version'];

        // Set version information
        update_option(BaseConstants::OPTION_PLUGIN_VERSION, $pluginVersion);
        update_option(BaseConstants::OPTION_DB_VERSION, '1.0.0');
        update_option(BaseConstants::OPTION_API_VERSION, '1.0.0');
        update_option(BaseConstants::OPTION_RANKINGCOACH_ONBOARDING_URL, home_url(), true);
        
        // Generate and store unique installation ID
        update_option(BaseConstants::OPTION_INSTALLATION_ID, CoreHelper::generate_installation_id());
    }
    
    /**
     * Sets up application password for API authentication.
     * 
     * @return void
     */
    private function setupApplicationPassword(): void {
        $this->manageApplicationPassword();
    }

    /**
     * Deactivates the plugin.
     *
     * This method is called directly by WordPress when the plugin is deactivated.
     * It runs in an isolated environment with limited WordPress access.
     * Any exceptions will prevent plugin deactivation and be displayed to the user.
     *
     * @param bool $entireNetwork Whether to deactivate for the entire network in multisite
     * @return void
     * @throws Throwable
     */
    public function deactivationOnce(bool $entireNetwork = false): void {
        if (!is_multisite() || !$entireNetwork) {
            $this->deactivateSingleSite();
            return;
        }
        $this->networkActivateDeactivate(false);
    }

    /**
     * Handles plugin deactivation events.
     *
     * This method is called whenever any plugin is deactivated in WordPress.
     * It runs in the normal WordPress flow with full environment access.
     * Exceptions won't prevent plugin deactivation since this runs after deactivation is complete.
     *
     * @param string $plugin The path to the plugin file relative to the plugins directory
     * @return void
     */
    public function handlePluginDeactivation(string $plugin): void {
        // Handle conflict notifications for any deactivated plugin
        ConflictManager::getInstance()->removeConflictNotification($plugin);

        // If it's not our plugin being deactivated, we're done
        if ($plugin !== RANKINGCOACH_PLUGIN_BASENAME) {
            return;
        }

        // Log deactivation for debugging purposes
        $this->logDeactivationEvent($plugin);
    }
    
    /**
     * Logs plugin deactivation event with debug information.
     * 
     * @param string $plugin The plugin being deactivated
     * @return void
     */
    private function logDeactivationEvent(string $plugin): void {
        $backtrace = debug_backtrace();
        $this->log('Deactivated RC plugin: ' . $plugin . ' - Backtrace: ' . json_encode($backtrace), 'DEBUG');
    }

    /**
     * Deactivates the plugin for a single site.
     *
     * This method handles the core deactivation process for a single WordPress site.
     * It cleans up cron jobs, application passwords, module settings, and database tables.
     *
     * @return void
     * @throws Throwable
     */
    private function deactivateSingleSite(): void {

        // Check if the plugin allows settings cleanup on deactivation
        if (!SettingsManager::instance()->get_option('remove_settings_on_deactivation', false)) {
            $this->log('Remove settings on deactivation is disabled.', 'DEBUG');
            return;
        }

        // Trigger a hook before deactivation cleanup, and everybody can know that we are deactivating
        do_action(Hooks::RANKINGCOACH_ACTION_PLUGIN_DEACTIVATING);
        // How can check if the hook was triggered with php command?
        // $wasTriggered = did_action(Hooks::RANKINGCOACH_ACTION_PLUGIN_DEACTIVATING);

        try {
            $this->cleanUserData();
        } catch (Throwable $th) {
            // Log the error but allow deactivation to continue
            $this->log('Error during plugin deactivation: ' . $th->getMessage() . ' in ' . $th->getFile() . ' on line ' . $th->getLine(), 'ERROR');
        }
    }
    
    /**
     * Cleans up plugin resources during deactivation.
     * 
     * @return void
     * @throws Exception If cleanup fails
     */
    public function cleanActionSchedulerJobs(): void {

        delete_option('schema-ActionScheduler_LoggerSchema');
        delete_option('schema-ActionScheduler_StoreSchema');

        if (class_exists('ActionScheduler') && method_exists('ActionScheduler', 'is_initialized')) {
            $initialized = ActionScheduler::is_initialized(true);
            if ($initialized) {
                // Cancel all jobs in the RANKINGCOACH_BRAND_NAME group
                if (function_exists('as_unschedule_all_actions')) {
                    as_unschedule_all_actions('', [], RANKINGCOACH_BRAND_NAME);
                }

                // Cancel jobs by hook patterns for fallback
                $hookPatterns = [
                    'rankingcoach_',
                    'beyondseo_',
                    'rc_'
                ];

                foreach ($hookPatterns as $pattern) {
                    if (function_exists('as_get_scheduled_actions')) {
                        $actions = as_get_scheduled_actions([
                            'hook' => $pattern,
                            'status' => ActionScheduler_Store::STATUS_PENDING,
                            'per_page' => -1
                        ]);

                        foreach ($actions as $action) {
                            if (function_exists('as_unschedule_action')) {
                                as_unschedule_action($action->get_hook(), $action->get_args(), $action->get_group());
                            }
                        }
                    }
                }

                // Direct database cleanup for any remaining jobs
                $dbManager = DatabaseManager::getInstance();
                $dbManager->db()->queryRaw(
                    /* @lang=MySQL */"DELETE FROM " . $dbManager->db()->prefixTable('actionscheduler_actions') . " WHERE hook LIKE 'rankingcoach_%' OR hook LIKE 'rc_%' OR group_id IN (
                        SELECT group_id FROM " . $dbManager->db()->prefixTable('actionscheduler_groups') . " WHERE slug = " . $dbManager->db()->escapeValue(RANKINGCOACH_BRAND_NAME) . "
                    )"
                );
            }
        }
    }

    /**
     * Removes all RankingCoach API application passwords during plugin deactivation.
     * 
     * This method removes:
     * 1. The stored application password for the current user
     * 2. All application passwords with the name 'RankingCoach API' for all users
     * 3. The stored application password option from the database
     * 
     * @return void
     */
    private function removeApplicationPassword(): void {
        
        // Now remove all RankingCoach API application passwords for all users
        $this->removeAllRankingCoachAppPasswords();
    }
    
    /**
     * Removes all RankingCoach API application passwords for all users.
     * 
     * @return void
     */
    private function removeAllRankingCoachAppPasswords(): void {
        // Get all users who might have application passwords
        $users = get_users([
            'fields' => ['ID'],
            'meta_key' => BaseConstants::OPTION_APPLICATION_PASSWORD,
        ]);
        
        foreach ($users as $user) {
            $appPasswords = WP_Application_Passwords::get_user_application_passwords($user->ID);
            
            if (is_array($appPasswords)) {
                foreach ($appPasswords as $appPassword) {
                    if (isset($appPassword['name']) && $appPassword['name'] === 'RankingCoach API') {
                        WP_Application_Passwords::delete_application_password($user->ID, $appPassword['uuid']);
                        $this->log('Removed Application Password for user ID: ' . $user->ID);
                    }
                }
            }
        }
    }

    /**
     * Manages the application password for the current user.
     * 
     * This method is responsible for creating, verifying, and updating
     * the application password used for API authentication. It is called
     * during plugin activation.
     *
     * @return void
     */
    private function manageApplicationPassword(): void {
        $user = wp_get_current_user();
        
        if (!$user instanceof WP_User) {
            return;
        }

        $this->createNewAppPassword($user);
    }
    
    /**
     * Verifies if an existing application password is still valid.
     * 
     * @param WP_User $user The current user
     * @param array $storedAppPassword The stored application password data
     * @return bool True if the password exists and is valid, false otherwise
     */
    private function verifyExistingAppPassword(WP_User $user, array $storedAppPassword): bool {
        $appExists = false;
        
        // Get all application passwords for this user
        $appPasswords = WP_Application_Passwords::get_user_application_passwords($user->ID);
        
        // Check if our application password still exists
        if (is_array($appPasswords) && isset($storedAppPassword[0])) {
            foreach ($appPasswords as $appPassword) {
                if (isset($appPassword['uuid']) && $appPassword['uuid'] === $storedAppPassword[0]) {
                    $appExists = true;
                    break;
                }
            }
        }
        
        return $appExists;
    }
    
    /**
     * Creates a new application password for the user.
     * 
     * @param WP_User $user The current user
     * @return void
     */
    private function createNewAppPassword(WP_User $user): void {
        // First, delete any existing RankingCoach API application passwords
        $this->cleanupExistingAppPasswords($user);
        
        // Create a new application password
        $password = wp_generate_password(24, true);
        $args = [
            'name' => 'RankingCoach API',
            'app_id' => md5('RankingCoach API' . $password),
            'expires' => 0,
        ];
        
        list($appPassword, $item) = WP_Application_Passwords::create_new_application_password($user->ID, $args);
        // update user meta with the new application password
        update_user_meta($user->ID, BaseConstants::OPTION_APPLICATION_PASSWORD, $appPassword);
    }
    
    /**
     * Removes any existing RankingCoach API application passwords.
     * 
     * @param WP_User $user The current user
     * @return void
     */
    private function cleanupExistingAppPasswords(WP_User $user): void {
        $appPasswords = WP_Application_Passwords::get_user_application_passwords($user->ID);
        
        if (is_array($appPasswords)) {
            foreach ($appPasswords as $appPassword) {
                if (isset($appPassword['name']) && $appPassword['name'] === 'RankingCoach API') {
                    WP_Application_Passwords::delete_application_password($user->ID, $appPassword['uuid']);
                }
            }
        }
    }

    /**
     * Manages the activation or deactivation of the plugin across a network.
     *
     * This method iterates through all active blogs in a multisite network
     * and activates or deactivates the plugin for each one.
     *
     * @param bool $activate Indicates whether to activate (true) or deactivate (false)
     * @return void
     */
    private function networkActivateDeactivate(bool $activate): void {
        // Get all active sites in the network
        $sites = $this->getActiveSites();
        
        // Process each site
        foreach ($sites as $blogId) {
            $this->processSite($blogId, $activate);
        }
    }
    
    /**
     * Retrieves all active sites in the multisite network.
     * 
     * @return array Array of blog IDs
     */
    private function getActiveSites(): array {
        // Use WordPress's built-in get_sites function if available (WP 4.6+)
        if (function_exists('get_sites') && class_exists('WP_Site_Query')) {
            return $this->getSitesModernWP();
        }
        
        // Fallback for older WordPress versions
        return $this->getSitesLegacyWP();
    }
    
    /**
     * Gets sites using modern WordPress functions (4.6+).
     * 
     * @return array Array of blog IDs
     */
    private function getSitesModernWP(): array {
        $blogIds = [];
        $sites = get_sites(['archived' => 0, 'spam' => 0, 'deleted' => 0]);
        
        foreach ($sites as $site) {
            $blogIds[] = is_object($site) ? $site->blog_id : $site['blog_id'];
        }
        
        return $blogIds;
    }
    
    /**
     * Gets sites using legacy WordPress database queries.
     * 
     * @return array Array of blog IDs
     */
    private function getSitesLegacyWP(): array {
        $dbManager = DatabaseManager::getInstance();
        $blogsTable = $dbManager->db()->prefixTable('blogs');
        
        $results = $dbManager->db()->queryRaw(
            /** @lang=MySQL */"SELECT blog_id FROM {$blogsTable} WHERE archived = '0' AND spam = '0' AND deleted = '0'",
            'ARRAY_N'
        );
        
        $blogIds = [];
        if (is_array($results)) {
            foreach ($results as $row) {
                if (isset($row[0])) {
                    $blogIds[] = (int)$row[0];
                }
            }
        }
        
        return $blogIds ?: [];
    }
    
    /**
     * Processes a single site for activation or deactivation.
     * 
     * @param int $blogId The blog ID to process
     * @param bool $activate Whether to activate or deactivate
     * @return void
     */
    private function processSite(int $blogId, bool $activate): void {
        $callback = $activate ? 'activateSingleSite' : 'deactivateSingleSite';
        
        switch_to_blog($blogId);
        $this->$callback();
        restore_current_blog();
    }

    /**
     * Initializes the RSS feed manager.
     *
     * This method is a placeholder for initializing the RSS feed manager.
     * It can be extended in the future to set up RSS feed generation and management.
     *
     * @return void
     */
    private function initializeRssFeedManager()
    {
        FeedManager::getInstance()->init();
    }

    /**
     * Initializes the RobotsTxt manager.
     *
     * This method is a placeholder for initializing the RobotsTxt manager.
     *
     * @return void
     */
    private function initializeRobotsTxtManager()
    {
        RobotsTxtManager::getInstance()->init();
    }


    /**
     * Initializes the WordPress heartbeat service management.
     *
     * This method sets up the heartbeat service control based on plugin settings.
     * It manages the WordPress admin-ajax heartbeat service to improve performance.
     *
     * @return void
     */
    private function initializeHeartbeatService(): void
    {
        CoreHelper::manageHeartbeatService();
    }

    /**
     * Run activationOnce() exactly once even for MU-plugins/pre-activated installs.
     * Idempotent + race-safe via an option lock.
     */
    public function maybeActivateOnce(): void
    {
        // Fast path: already done
        if (get_option(BaseConstants::OPTION_PLUGIN_ACTIVATION_DONE) || !is_admin()) {
            return;
        }

        // Atomic lock: add_option returns false if it already exists
        if (! add_option(BaseConstants::OPTION_PLUGIN_ACTIVATION_LOCK, time(), '', 'no')) {
            return; // another request is running it
        }

        try {
            if (! get_option(BaseConstants::OPTION_PLUGIN_ACTIVATION_DONE)) {
                // Call our existing activation flow
                $this->activationOnce();
                update_option(BaseConstants::OPTION_PLUGIN_ACTIVATION_DONE, 1, true);
            }
        } catch (\Throwable $e) {
            $this->log('activationOnce failed: ' . $e->getMessage(), 'ERROR');
            // do NOT set OPT_ACTIVATION_DONE; we want a retry next load
        } finally {
            delete_option(BaseConstants::OPTION_PLUGIN_ACTIVATION_LOCK);
        }
    }

    /**
     * Handles plugin uninstallation.
     *
     * This method is called when the plugin is uninstalled, not just deactivated.
     * It can be used to clean up any resources or settings that should be removed
     * when the plugin is completely removed from the site.
     *
     * @return void
     */
    public static function handleUninstall(): void
    {
       // This method is intentionally left empty for now.
    }

    /**
     * @throws Exception
     */
    public function cleanUserData(): void {
        CronJobManager::instance()?->clearAllCronJobs();

        // Remove all module settings and tables
        ModuleManager::instance()->uninstall();

        // Remove all options with the 'rankingcoach_', 'rc_', 'bseo_' prefix
        PluginConfiguration::getInstance()->removeOptions();

        // Reset the notifications option to an empty array
        NotificationManager::instance()->removeAllNotifications();

        // Remove all tables
        DatabaseManager::getInstance()->removeAllTables();

        // Remove all users & posts metadata
        DatabaseTablesManager::getInstance()->removeUsersAndPostsMetadata();

        // Perform Action-Scheduler cleanup operations
        $this->cleanActionSchedulerJobs();

        // Delete the Application Password
        $this->removeApplicationPassword();
    }
}
