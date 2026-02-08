<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Admin\Pages;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Exception;
use RankingCoach\Inc\Core\Admin\AdminManager;
use RankingCoach\Inc\Core\Admin\AdminPage;
use RankingCoach\Inc\Core\Api\User\UserApiManager;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Frontend\ViteApp\ReactApp;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\ChannelFlow\ChannelResolver;
use RankingCoach\Inc\Core\ChannelFlow\OptionStore;
use RankingCoach\Inc\Core\ChannelFlow\Traits\FlowGuardTrait;
use RankingCoach\Inc\Core\Helpers\Traits\RcApiTrait;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Core\Jobs\AccountSyncJob;
use RankingCoach\Inc\Core\TokensManager;
use RankingCoach\Inc\Exceptions\HttpApiException;
use RankingCoach\Inc\Exceptions\InvalidTokenException;
use RankingCoach\Inc\Traits\SingletonManager;
use ReflectionException;
use WP_REST_Request;
use WP_Error;

/**
 * Class DashboardPage
 * @method UpsellPage getInstance(): static
 */
class UpsellPage extends AdminPage
{

    use RcApiTrait;
    use SingletonManager;
    use RcLoggerTrait;
    use FlowGuardTrait;

    /** @var string $name The name of the upsell page */
    public string $name = 'upsell';

    /** @var AdminManager|null $managerInstance */
    public static AdminManager|null $managerInstance = null;

    /** Feature flag: when true, UpsellPage will use FlowManager to guard access. */
    private bool $flowGuardEnabled = false;

    /**
     * UpsellPage constructor.
     * Initializes the UpsellPage instance and registers necessary scripts.
     */
    public function __construct() {
        // Register scripts loading
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('rest_api_init', [$this, 'registerUpsellRoutes']);
        $this->flowGuardEnabled = OptionStore::isFlowGuardActive();

        // Load React app for upsell page
        add_action('current_screen', function($screen) {
            // Ensure the screen object is available
            if (!is_object($screen) || !isset($screen->id)) {
                return;
            }

            if (
                $screen->id !== RANKINGCOACH_BRAND_SLUG . '_page_rankingcoach-' . $this->name &&
                $screen->id !== 'admin_page_rankingcoach-' . $this->name
            ) {
                return;
            }

            ReactApp::get([
                'upsell'
            ]);
        });

        parent::__construct();
    }

    /**
     * Enqueue necessary scripts and styles for the upsell page
     *
     * @param string $hook The current admin page hook
     * @throws HttpApiException
     * @throws ReflectionException
     */
    public function enqueue_scripts(string $hook): void {

        if (
            $hook === RANKINGCOACH_BRAND_SLUG . '_page_rankingcoach-' . $this->name ||
            $hook === 'admin_page_rankingcoach-' . $this->name
        ) {
            $upsell_script_url = RANKINGCOACH_PLUGIN_ADMIN_URL . 'assets/js/upsell-page.js';
            $upsell_window_script_url = RANKINGCOACH_PLUGIN_ADMIN_URL . 'assets/js/upsell-window.js';
            $css_url = RANKINGCOACH_PLUGIN_ADMIN_URL . 'assets/css/admin-style.css';
            $common_css_url = RANKINGCOACH_PLUGIN_ADMIN_URL . 'assets/css/common-style.css';
            $upsell_css_url = RANKINGCOACH_PLUGIN_ADMIN_URL . 'assets/css/upsell-page.css';
            $version = defined('RANKINGCOACH_VERSION') ? RANKINGCOACH_VERSION : '1.0.0';
            
            // Enqueue Admin CSS
            wp_enqueue_style(
                'rankingcoach-admin-style',
                $css_url,
                [],
                $version
            );

            // Enqueue Common CSS
            wp_enqueue_style(
                'rankingcoach-common-style',
                $common_css_url,
                [],
                $version
            );

            // Enqueue Upsell Page CSS
            wp_enqueue_style(
                'rankingcoach-upsell-page-style',
                $upsell_css_url,
                [],
                $version
            );

            // Enqueue JavaScript
            wp_enqueue_script(
                'rankingcoach-upsell-page-js',
                $upsell_script_url,
                ['jquery'],
                $version,
                true // Load in footer
            );

            wp_enqueue_script(
                'rankingcoach-upsell-window-js',
                $upsell_window_script_url,
                ['jquery'],
                $version,
                true // Load in footer
            );

            wp_localize_script('rankingcoach-upsell-window-js', 'rcWindowConfig', [
                'loadingTitle' => __('Loading...', 'beyond-seo'),
                'connectingMessage' => __('Connecting to server...', 'beyond-seo'),
            ]);

            // Initialize the registration handler with custom callbacks
            $main_page_url = AdminManager::getPageUrl(AdminManager::PAGE_MAIN, '?ref=account_sync');
            wp_add_inline_script(
                'rankingcoach-upsell-window-js',
                "if (typeof BSEORegistration !== 'undefined') {
                    BSEORegistration.init({
                        onSuccess: function(payload) {
                            console.log('[RankingCoach] Registration successful, redirecting to main page');
                            window.location.href = '" . esc_js($main_page_url) . "';
                        },
                        onError: function(errorMessage, payload) {
                            console.error('[RankingCoach] Registration error:', errorMessage);
                            alert('Registration Error: ' + errorMessage);
                        },
                        onCancel: function(payload) {
                            console.log('[RankingCoach] Registration cancelled by user');
                        }
                    });
                }",
                'after'
            );

            // Localize script for API calls
            wp_localize_script('rankingcoach-upsell-page-js', 'rcUpsell', [
                'apiUrl' => esc_url_raw(rest_url(RANKINGCOACH_REST_API_BASE . '/upsell/url')),
                'baseUrl' => UserApiManager::getInstance()->getClientDashboardUrl(),
                'locale' => WordpressHelpers::get_wp_locale() ?? 'en_US',
                'nonce'  => wp_create_nonce('wp_rest')
            ]);
        }
    }

    /**
     * Register REST endpoints for upsell flow.
     */
    public function registerUpsellRoutes(): void
    {
        register_rest_route(RANKINGCOACH_REST_API_BASE, '/upsell/url', [
            'methods' => 'POST',
            'callback' => [$this, 'handleGetUpsellUrl'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
            'args' => [
                'paymentType' => [
                    'required'          => true,
                    'type'              => 'string',
                    'description'       => 'The payment type (monthly or annual)',
                    'validate_callback' => function ($param) {
                        return in_array($param, ['monthly', 'annual'], true);
                    },
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }

    /**
     * Handle request to get upsell magic link.
     *
     * @param WP_REST_Request $request
     * @return array|WP_Error
     */
    public function handleGetUpsellUrl(WP_REST_Request $request): array|WP_Error
    {
        // paymentType is already validated and sanitized by the REST API
        $paymentType = $request->get_param('paymentType');
        $country = get_option(BaseConstants::OPTION_RANKINGCOACH_COUNTRY_CODE, 'US');

        try {
            $result = UserApiManager::getInstance(bearerToken: true)->fetchUpsellMagicLink($paymentType, $country);

            if (!$result) {
                return new WP_Error('upsell_error', 'Could not fetch upsell URL', ['status' => 500]);
            }

            /**
             * We should check next time customer if he upgraded or not
             */
            // Attempt force sync using AccountSyncJob
            $accountSyncJob = AccountSyncJob::instance();
            $accountSyncJob->forceSync();
            update_option(BaseConstants::OPTION_UPSELL_FORCE_CHECK, true, true);

            return $result;
        } catch (Exception $e) {
            return new WP_Error('upsell_exception', $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * @return string
     */
    public function page_name(): string
    {
        return $this->name;
    }

    /**
     * Admin UpsellPage constructor.
     * @param callable|null $failCallback
     * @throws HttpApiException
     * @throws ReflectionException
     */
    public function page_content(?callable $failCallback = null): void
    {
        // Use ChannelResolver for consistent channel detection
        $store = new OptionStore();
        $resolver = new ChannelResolver($store);
        $channel = $resolver->resolve();

        // Guarded by feature flag; performs render/redirect and exits when enabled
        $this->applyFlowGuard($failCallback);

        $step = WordpressHelpers::sanitize_input('GET', 'step') ?: false;
        $planSelected = WordpressHelpers::sanitize_input('GET', 'planSelected') ?: false;
        if( $step && $planSelected ) {
            $planSelectedStep = [
                'step' => $step,
                'planSelected' => $planSelected
            ];
        }

        /** @var TokensManager $tokensManager */
        $tokensManager = TokensManager::instance();
        $refreshToken = $tokensManager->getStoredRefreshToken();
        $accessToken = $tokensManager->getStoredAccessToken();

        /** @var TokensManager $tokensManager */
        if(!$tokensManager::validateToken($accessToken)) {
            $tokensManager->generateAndSaveAccessToken($refreshToken);
            $accessToken = $tokensManager->getStoredAccessToken();
        }
        if(!$accessToken || !$tokensManager::validateToken($accessToken)) {
            rceh()->error( (new InvalidTokenException('The access token is invalid or expired'))->throwException(true) );
        }

        // Handle upselling if planSelectedStep exists
        if (isset($planSelectedStep) && !empty($planSelectedStep['planSelected'])) {
            try {
                $upsellingResult = UserApiManager::handleUpselling($planSelectedStep['planSelected']);
                if ($upsellingResult && !empty($upsellingResult['upsellUrl'])) {
                    // Redirect to the upselling URL
                    wp_redirect($upsellingResult['upsellUrl']);
                    exit;
                }
            } catch (Exception $e) {
                $this->log('Upselling API error: ' . $e->getMessage(), 'ERROR');
            }
        }

        // Load the appropriate view template based on channel
        if ($channel === 'direct') {
            include __DIR__ . '/views/upsell-dc-page.php';
        } else {
            include __DIR__ . '/views/upsell-ionos-page.php';
        }

        // Include FlowGuard components if enabled
        if (defined('BSEO_FLOW_GUARD_ENABLED') && BSEO_FLOW_GUARD_ENABLED) {
            include __DIR__ . '/views/flowguard-button.php';
            include __DIR__ . '/views/flowguard-panel.php';
        }
    }

    /**
     * Get the URL for the new plans upgrade.
     *
     * @return string
     */
    public function getPlansToBuyUrl(): string
    {
        return esc_url(AdminManager::getPageUrl(AdminManager::PAGE_UPSELL));
    }

    /**
     * Guard Upsell page using FlowManager mapping. Exits after render/redirect when enabled.
     *
     * @param callable|null $failCallback
     * @return void
     */
    private function applyFlowGuard(?callable $failCallback = null): void
    {
        if (!$this->flowGuardEnabled) {
            return;
        }

        try {
            $result = $this->evaluateFlow();
            $step   = $result['next_step'] ?? '';

            // Allowed on UpsellPage: done (which maps to main/upsell)
            if ($step === 'done') {
                return;
            }

            // Otherwise redirect by flow mapping
            $destination = match ($step) {
                'activate', 'register', 'email_validation', 'finalizing' => 'activation',
                'onboarding'       => 'onboarding',
                default            => 'main',
            };

            if (self::$managerInstance instanceof AdminManager) {
                self::$managerInstance->redirectPage($destination);
            }
            if (is_callable($failCallback)) {
                $failCallback();
            }
            exit;
        } catch (\Throwable $e) {
            // Fail-open
            return;
        }
    }
}
