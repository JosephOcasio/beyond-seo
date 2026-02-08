<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Exception;
use RankingCoach\Inc\Core\Admin\Pages\ActivationPage;
use RankingCoach\Inc\Core\Admin\Pages\CachePage;
use RankingCoach\Inc\Core\Admin\Pages\GeneralSettingsPage;
use RankingCoach\Inc\Core\Admin\Pages\IframePage;
use RankingCoach\Inc\Core\Admin\Pages\OnboardingPage;
use RankingCoach\Inc\Core\Admin\Pages\RegistrationPage;
use RankingCoach\Inc\Core\Admin\Pages\FeedbackPage;
use RankingCoach\Inc\Core\Admin\Pages\UpsellPage;
use RankingCoach\Inc\Core\CacheManager;
use RankingCoach\Inc\Core\ConflictManager;
use RankingCoach\Inc\Core\DashboardWidgetManager;
use RankingCoach\Inc\Core\Frontend\ViteApp\ReactApp;
use RankingCoach\Inc\Core\Helpers\CoreHelper;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Core\Settings\SettingsManager;
use RankingCoach\Inc\Core\ToolbarManager;
use RankingCoach\Inc\Exceptions\HttpApiException;
use RankingCoach\Inc\Traits\SingletonManager;
use ReflectionException;

// Define the base URL for the admin management pages
define('RANKINGCOACH_ADMIN_MANAGER_URL', plugin_dir_url(__FILE__));


/**
 * Class AdminManager
 * @method AdminManager getInstance(): static
 */
class AdminManager
{

    use SingletonManager;

    /**
     * The main page of the plugin.
     *
     * @var IframePage|null
     */
    private ?IframePage $main_page;

    /**
     * The onboarding page of the plugin.
     *
     * @var ActivationPage|null
     */
    private ?ActivationPage $activation_page;

    /**
     * The registration page of the plugin.
     *
     * @var RegistrationPage|null
     */
    private ?RegistrationPage $registration_page;

    /**
     * The onboarding page of the plugin.
     *
     * @var OnboardingPage|null
     */
    private ?OnboardingPage $onboarding_page;

    /**
     * The general settings page of the plugin.
     *
     * @var GeneralSettingsPage|null
     */
    private ?GeneralSettingsPage $general_settings_page;

    /**
     * The dashboard page of the plugin.
     *
     * @var UpsellPage|null
     */
    private ?UpsellPage $upsell_page;

    /**
     * The feedback page handler of the plugin.
     *
     * @var FeedbackPage|null
     */
    private ?FeedbackPage $feedback_page;

    /**
     * The cache management page of the plugin.
     *
     * @var CachePage|null
     */
    private ?CachePage $cache_page;

    /**
     * The name of the main admin page.
     *
     * @var string
     */
    public const PAGE_MAIN              = 'rankingcoach-main';
    public const PAGE_SETTINGS          = 'rankingcoach-settings';
    public const PAGE_ACTIVATION        = 'rankingcoach-activation';
    public const PAGE_REGISTRATION      = 'rankingcoach-registration';
    public const PAGE_ONBOARDING        = 'rankingcoach-onboarding';
    public const PAGE_GENERAL_SETTINGS  = 'rankingcoach-generalSettings';
    public const PAGE_UPSELL            = 'rankingcoach-upsell';
    public const PAGE_CACHE             = 'rankingcoach-cache';

    /**
     * AdminManager constructor.
     */
    public function __construct()
    {

        add_action('current_screen', static function ($screen) {
            // Ensure the screen object is available and valid
            if (!is_object($screen) || !isset($screen->id)) {
                return;
            }

            // Only apply to our supported post types
            if (
                $screen->base === 'post' &&
                in_array($screen->post_type, ALLOWED_RANKINGCOACH_CUSTOM_TYPES, true)
            ) {
                // Avoid Elementor or other non-standard editors if needed
                $action = WordpressHelpers::sanitize_input('GET', 'action');

                if ($action === 'elementor') {
                    return;
                }

                $post_id = WordpressHelpers::sanitize_input(
                    'GET',
                    'post',
                    filters: [FILTER_SANITIZE_NUMBER_INT],
                    validate: FILTER_VALIDATE_INT,
                    return: 'int'
                );

                // Determine if it's Add New or Edit
                if ($screen->action === 'add') {
                    // It's the Add New screen for post/page
                    ReactApp::get([
                        'edit', 'float', 'add_new'
                    ], $post_id);
                } elseif($post_id > 0) {
                    // It's the Edit screen
                    ReactApp::get([
                        'edit', 'float'
                    ], $post_id);
                }
            }
        });

        // Initialize toolbar and dashboard widget managers
        ToolbarManager::getInstance()->init();
        DashboardWidgetManager::getInstance()->init();

        // Initialize cache manager
        CacheManager::getInstance()->init();

        $this->main_page = IframePage::getInstance()->setManager($this);
        $this->activation_page = ActivationPage::getInstance()->setManager($this);
        $this->registration_page = RegistrationPage::getInstance()->setManager($this);
        $this->onboarding_page = OnboardingPage::getInstance()->setManager($this);
        $this->general_settings_page = GeneralSettingsPage::getInstance()->setManager($this);
        $this->upsell_page = UpsellPage::getInstance()->setManager($this);
        $this->cache_page = CachePage::getInstance()->setManager($this);
        $this->feedback_page = FeedbackPage::getInstance()->setManager($this);
    }

    /**
     * Initialize the admin manager.
     */
    public function init(): void
    {
        // Add a div mounted on DOM, support for edit page/post
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        // Add a div mounted in DOM, support for a floating component. In footer admin
        add_action('admin_footer', [$this, 'footer_block']);
        // Create admin pages
        add_action('admin_menu', [$this, 'create_admin_pages']);
        // Add admin-specific hooks here
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts'], 0);
        // Add plugin action links
        add_filter('plugin_action_links_' . plugin_basename(RANKINGCOACH_FILE), [$this, 'plugin_action_links']);
    }

    /**
     * Returns the URL of the admin page.
     *
     * @param $page
     * @param string $additional
     * @return string
     */
    public static function getPageUrl($page, string $additional = ''): string
    {
        return admin_url( 'admin.php?page=' . $page . $additional );
    }

    /**
     * Enqueue admin scripts.
     * @throws Exception
     */
    public function enqueue_admin_scripts(string $hook_suffix): void
    {

        wp_enqueue_style('rankingcoach-admin-style', plugin_dir_url(__FILE__) . 'assets/css/admin-style.css', [], RANKINGCOACH_VERSION);
        wp_enqueue_style('rankingcoach-common-style', plugin_dir_url(__FILE__) . 'assets/css/common-style.css', [], RANKINGCOACH_VERSION);
        wp_enqueue_script('rankingcoach-admin-script', plugin_dir_url(__FILE__) . 'assets/js/common-script.js', ['jquery'], RANKINGCOACH_VERSION, true);

        wp_enqueue_style('admin-styles', 'https://www.rankingcoach.com/theming/definitions/direct-channel/direct-channel.min.css?version=1733928348532', [], RANKINGCOACH_VERSION);

        // Register the admin script for REST data
        wp_register_script(
            'rankingcoach-admin-rest-data',
            '',
            [],
            RANKINGCOACH_VERSION,
            true
        );
        wp_enqueue_script('rankingcoach-admin-rest-data');
        wp_add_inline_script(
            'rankingcoach-admin-rest-data',
            'window.rankingCoachRestData = ' . wp_json_encode([
                'nonce'             => wp_create_nonce('wp_rest'),
                'restUrl'           => esc_url_raw(rest_url(RANKINGCOACH_REST_API_BASE . '/')),
                'ajaxUrl'           => admin_url('admin-ajax.php'),
                'pluginUrl'         => RANKINGCOACH_PLUGIN_URL,
                'pluginVersion'     => RANKINGCOACH_VERSION,
                'wordpressVersion'  => get_bloginfo('version'),
                'phpVersion'        => phpversion(),
            ]) . ';',
            'before'
        );

        // Enqueue only on some plugin pages, not all
        if (in_array($hook_suffix, ALLOWED_RANKINGCOACH_PAGES, true)) {
            // scripts
            $time = time();
            $nonce = CoreHelper::rc_custom_nonce(CoreHelper::RC_NONCE_ACTION_NAME, $time);
            wp_enqueue_script('rankingcoach-general-admin-script', plugin_dir_url(__FILE__) . 'assets/js/admin-script.js', ['jquery'], RANKINGCOACH_VERSION, true);
            wp_localize_script('rankingcoach-general-admin-script', 'RankingCoachGeneralData', [
                'site_url'          => get_site_url(),
                'api_base_url'      => get_rest_url(null, '/' . RANKINGCOACH_REST_API_BASE . '/'),
                'ajax_url'          => admin_url('admin-ajax.php'),
                'nonce_ts'          => $time,
                'nonce'             => $nonce,
                'i18n'              => [
                    'showMore'      => __('Show more', 'beyond-seo'),
                    'showLess'      => __('Show less', 'beyond-seo'),
                ]
            ]);
        }

        // Enqueue the main React bundle using in page/post lists, inline score widget
        $this->enqueue_vite_asset(
            $hook_suffix,
            'src/main.tsx', // entryKey in manifest.json
            'rc-main-react'
        );
    }

    /**
     * This function is used to mount react components to mounting points.
     *
     * @param string $hookSuffix The hook name where the script should be enqueued.
     * @param string $entryKey The key corresponding to the entry point in the manifest file.
     * @param string $scriptHandle The handle for the script being registered.
     * @param string $manifestRelativePath Optional. Relative path to the manifest file. Default is 'react/dist/manifest.json'.
     */
    public function enqueue_vite_asset(string $hookSuffix, string $entryKey, string $scriptHandle, string $manifestRelativePath = 'react/dist/manifest.json'): void
    {
        if ( $hookSuffix !== 'edit.php') {
            return;
        }

        $manifest_path = plugin_dir_path(RANKINGCOACH_FILE) . $manifestRelativePath;
        if (!file_exists($manifest_path)) {
            return;
        }

        $manifest = json_decode(file_get_contents($manifest_path), true);
        if (!isset($manifest[$entryKey])) {
            return;
        }

        $bundle = $manifest[$entryKey];
        $jsFile = $bundle['file'] ?? null;
        $cssFiles = $bundle['css'] ?? [];

        if ($jsFile) {
            wp_enqueue_script(
                $scriptHandle,
                plugin_dir_url(RANKINGCOACH_FILE) . dirname($manifestRelativePath) . '/' . $jsFile,
                [],
                RANKINGCOACH_VERSION,
                true
            );

            add_filter('script_loader_tag', function ($tag, $handle) use ($scriptHandle) {
                if ($handle === $scriptHandle) {
                    return str_replace(' src', ' type="module" src', $tag);
                }
                return $tag;
            }, 10, 2);
        }

        foreach ($cssFiles as $cssFile) {
            wp_enqueue_style(
                $scriptHandle . '-style-' . md5($cssFile),
                plugin_dir_url(RANKINGCOACH_FILE) . dirname($manifestRelativePath) . '/' . $cssFile,
                [],
                RANKINGCOACH_VERSION
            );
        }
    }

    /**
     * Create InPost Block
     * registers the meta-box within the WordPress infrastructure
     *
     * @return void
     */
    public function add_meta_boxes(): void
    {
        // Get the current screen object.
        $screen = get_current_screen();

        // Check if we're in the admin area, editing a post or page, and not creating a new one.
        if ($screen && $screen->base === 'post') {
            add_meta_box(
                'rankingcoach-seo-analysis',
                RANKINGCOACH_BRAND_NAME,
                [$this, 'edit_block_callback'],
                ALLOWED_RANKINGCOACH_CUSTOM_TYPES,
                'normal',
                'high'
            );
        }
    }

    /**
     * InPost Block Callback
     * The HTML echoed by the InPost Block
     *
     * @return void
     */
    public function edit_block_callback(): void
    {
        echo '<div id="edit-rankingcoach-react"></div>';
    }

    /**
     * Outputs a custom footer block container for React.
     *
     * @return void
     */
    public function footer_block(): void
    {
        echo '
			<div id="seo-optimiser-rankingcoach-react"></div>
		    <script>
		        document.addEventListener("DOMContentLoaded", function() {
		            let target = document.getElementById("wpcontent");
		            let element = document.getElementById("seo-optimiser-rankingcoach-react");

		            if (target && element) {
		                target.appendChild(element);
		            }
		        });
		    </script>
		';
    }

    /**
     * Outputs a custom page block container for React.
     *
     * @return void
     */
    public function page_block(): void
    {
        echo '<div id="page-rankingcoach-react"></div>';
    }

    /**
     * Creates admin pages for the plugin.
     * @return void
     * @throws HttpApiException
     * @throws ReflectionException
     */
    public function create_admin_pages(): void
    {
        // Used for a later redirect to bypass the check for a scent header
        ob_start();
        $failCallback = function () {
            if (ob_get_level() > 0) {
                ob_end_flush();
            }
        };

        add_menu_page(RANKINGCOACH_BRAND_NAME, RANKINGCOACH_BRAND_NAME, 'manage_options', 'rankingcoach-main', function () use ($failCallback) {
            $this->main_page->page_content($failCallback);
        }, plugin_dir_url(__FILE__) . 'assets/icons/rC-logo-wp.svg', 20);

        add_submenu_page('rankingcoach-main', __('Settings', 'beyond-seo'), __('Settings', 'beyond-seo'), 'manage_options', 'rankingcoach-generalSettings', function () use ($failCallback) {
            $this->general_settings_page->page_content($failCallback);
        });

        add_submenu_page('-', __('Activation', 'beyond-seo'), __('Activation', 'beyond-seo'), 'manage_options', 'rankingcoach-activation', function () use ($failCallback) {
            $this->activation_page->page_content($failCallback);
        });

        add_submenu_page('-', __('Registration', 'beyond-seo'), __('Registration', 'beyond-seo'), 'manage_options', 'rankingcoach-registration', function () use ($failCallback) {
            $this->registration_page->page_content($failCallback);
        });

        add_submenu_page('-', __('Onboarding', 'beyond-seo'), __('Onboarding', 'beyond-seo'), 'manage_options', 'rankingcoach-onboarding', function () use ($failCallback) {
            $this->onboarding_page->page_content($failCallback);
        });

        if(!CoreHelper::isHighestPaid()){
            add_submenu_page('rankingcoach-main', __('Upgrade Plan', 'beyond-seo'), __('Upgrade Plan', 'beyond-seo'), 'manage_options', 'rankingcoach-upsell', function () use ($failCallback) {
                $this->upsell_page->page_content($failCallback);
            });
        }
    }

    /**
     * Handles the submission of the settings form.
     *
     * @return void
     */
    public function processAllFormSubmissions(): void
    {
        // Handle the activation form submission
        ActivationPage::getInstance()->handleActivationFormSaves();

        // Handle the deactivate plugins submission
        ConflictManager::getInstance()->registerAjaxHandlers();
    }

    /**
     * Redirects to the specified admin page.
     *
     * @param string $pageName - The name of the page to redirect to.
     * @param string|null $queries - Optional queries to append to the URL.
     * @return void
     */
    public function redirectPage(string $pageName, string $queries = null): void
    {
        if (isset($this->main_page) && property_exists($this->main_page, 'name') && $this->main_page->page_name() === $pageName) {
            $this->main_page->redirect($queries);
        }
        if (isset($this->onboarding_page) && property_exists($this->onboarding_page, 'name') && $this->onboarding_page->page_name() === $pageName) {
            $this->onboarding_page->redirect($queries);
        }
        if (isset($this->general_settings_page) && property_exists($this->general_settings_page, 'name') && $this->general_settings_page->page_name() === $pageName) {
            $this->general_settings_page->redirect($queries);
        }
        if (isset($this->activation_page) && property_exists($this->activation_page, 'name') && $this->activation_page->page_name() === $pageName) {
            $this->activation_page->redirect($queries);
        }
        if (isset($this->registration_page) && property_exists($this->registration_page, 'name') && $this->registration_page->page_name() === $pageName) {
            $this->registration_page->redirect($queries);
        }
        if (isset($this->upsell_page) && property_exists($this->upsell_page, 'name') && $this->upsell_page->page_name() === $pageName) {
            $this->upsell_page->redirect($queries);
        }
        if (isset($this->cache_page) && property_exists($this->cache_page, 'name') && $this->cache_page->page_name() === $pageName) {
            $this->cache_page->redirect($queries);
        }
        if (isset($this->feedback_page) && property_exists($this->feedback_page, 'name') && $this->feedback_page->page_name() === $pageName) {
            $this->feedback_page->redirect($queries);
        }
    }

    /**
     * Add action links to the plugin page.
     *
     * @param array $links
     * @return array
     */
    public function plugin_action_links(array $links): array
    {
        if (!CoreHelper::isHighestPaid()) {
            $upsell_text = esc_html__('Upgrade Plan', 'beyond-seo');
            $links['upgrade_link'] = sprintf(
                '<a style="font-weight: 900;" href="%1$s" class="rankingcoach-upgrade-link">%2$s</a>',
                esc_url(AdminManager::getPageUrl(AdminManager::PAGE_UPSELL)),
                $upsell_text
            );
        }

        return $links;
    }
}
