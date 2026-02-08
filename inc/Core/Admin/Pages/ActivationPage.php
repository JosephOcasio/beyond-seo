<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Admin\Pages;

if (!defined('ABSPATH')) {
    exit;
}

use Exception;
use JetBrains\PhpStorm\NoReturn;
use RankingCoach\Inc\Core\Admin\AdminManager;
use RankingCoach\Inc\Core\Admin\AdminPage;
use RankingCoach\Inc\Core\Api\User\UserApiManager;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\ChannelFlow\ChannelEnforcer;
use RankingCoach\Inc\Core\ChannelFlow\ChannelResolver;
use RankingCoach\Inc\Core\ChannelFlow\OptionStore;
use RankingCoach\Inc\Core\ChannelFlow\Traits\FlowGuardTrait;
use RankingCoach\Inc\Core\Helpers\CoreHelper;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Core\Initializers\Hooks;
use RankingCoach\Inc\Core\TokensManager;
use RankingCoach\Inc\Exceptions\HttpApiException;
use RankingCoach\Inc\Traits\SingletonManager;
use ReflectionException;
use Throwable;

/**
 * Class ActivationPage
 *
 * Handles plugin activation flow, validation, and user redirection.
 * Responsible for rendering the activation form and processing submitted codes.
 *
 * @method static ActivationPage getInstance()
 */
class ActivationPage extends AdminPage
{
    use FlowGuardTrait;
    use SingletonManager;

    /** @var string Action name used in form submission */
    protected const SAVE_ACTIVATION_ACTION = 'save_rankingcoach_activation';

    /** @var string Page slug name */
    public string $name = 'activation';

    /** @var AdminManager|null Reference to the main admin manager */
    public static ?AdminManager $managerInstance = null;

    /**
     * Flag to track if access control was already handled by the load-{$page_hook} hook.
     * When true, page_content() will skip the FlowGuard logic since it was already processed.
     *
     * @var bool
     */
    private bool $accessControlHandled = false;

    /** @var bool Indicates whether API error filter is active */
    private bool $apiErrorFilterEnabled = false;

    /** Feature flag: when true, ActivationPage will use FlowManager to guard access (redirect if next_step !== 'activate'). */
    // We keep it disabled by default to avoid automatic redirects that could be confuse
    // The case when rc_activation_saved=1 and the refresh is made automatically.
    private bool $flowGuardEnabled = false;

    public function __construct() {
        parent::__construct();
        if (isset($_GET['bypass_flow'])) {
            $this->flowGuardEnabled = false;
        } else {
            $this->flowGuardEnabled = OptionStore::isFlowGuardActive();
        }
    }

    /**
     * Returns the page name slug.
     */
    public function page_name(): string
    {
        return $this->name;
    }

    /**
     * Displays the activation page content.
     * Evaluates flow, handles redirects, and loads the activation view.
     *
     * @param callable|null $failCallback Optional callback executed on failure.
     */
    public function page_content(?callable $failCallback = null): void
    {
        $store = new OptionStore();
        $resolver = new ChannelResolver($store);
        $channel = $resolver->resolve();
        if (!isset($_GET['bypass_flow'])) {
            ChannelEnforcer::enforcePageAccess('activation', $channel);
        }

        // Optional flow guard (disabled by default; see $this->flowGuardEnabled)
        $this->applyFlowGuard($failCallback);

        wp_enqueue_style(
            'rankingcoach-activation',
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/activation.css',
            [],
            RANKINGCOACH_VERSION
        );
        wp_enqueue_script(
            'rankingcoach-activation',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/activation.js',
            [],
            RANKINGCOACH_VERSION,
            true
        );
        wp_localize_script('rankingcoach-activation', 'rcActivation', [
            'errorEmptyCode' => __('Activation code is required.', 'beyond-seo'),
        ]);

        // REF: get message if exists
        $errorMessage = $this->getQueryParamString('message');

        // Retrieve activation code from database
        $activationCode = get_option(BaseConstants::OPTION_ACTIVATION_CODE);

        // REF: load isolated view
        include __DIR__ . '/views/activation-page.php';
    }

    /**
     * Registers hooks for form processing and notice handling.
     */
    public function handleActivationFormSaves(): void
    {
        static $registered = false;
        if ($registered) {
            return;
        }

        add_action('admin_post_' . self::SAVE_ACTIVATION_ACTION, [$this, 'handleActivationCodeSubmission']);
        add_action('admin_notices', [$this, 'handleActivationNotices']);
        $registered = true;
    }

    /** Enables API error propagation through filters. */
    private function enableApiErrorPropagation(): void
    {
        if ($this->apiErrorFilterEnabled) {
            return;
        }
        add_filter('rankingcoach_http_api_response_throw_exception', [$this, 'forceHttpApiExceptions'], 10, 2);
        $this->apiErrorFilterEnabled = true;
    }

    /** Disables API error propagation. */
    private function disableApiErrorPropagation(): void
    {
        if (!$this->apiErrorFilterEnabled) {
            return;
        }
        remove_filter('rankingcoach_http_api_response_throw_exception', [$this, 'forceHttpApiExceptions'], 10);
        $this->apiErrorFilterEnabled = false;
    }

    /** Forces HTTP API responses to throw exceptions for uniform handling. */
    public function forceHttpApiExceptions(bool $shouldThrow, array $errorDetails = []): bool
    {
        return true;
    }

    /** Redirects back to the activation page with an error message. */
    #[NoReturn]
    private function redirectToActivationWithMessage(string $message): void
    {
        $this->disableApiErrorPropagation();
        AdminManager::getInstance()->redirectPage('activation', '&message=' . urlencode($message));
        exit;
    }

    /** Redirects to activation success state. */
    #[NoReturn]
    private function redirectToActivationSuccess(): void
    {
        $this->disableApiErrorPropagation();
        AdminManager::getInstance()->redirectPage('activation', '&rc_activation_saved=1');
        exit;
    }

    /**
     * Handles form submission for the activation code.
     *
     */
    #[NoReturn]
    public function handleActivationCodeSubmission(): void
    {
        $this->checkUserPermissions();
        $this->enableApiErrorPropagation();

        $activationCode = WordpressHelpers::sanitize_input('POST', 'activation_code');
        if (empty($activationCode)) {
            error_log('DEBUG: Empty activation code submitted.');
            $this->redirectToActivationWithMessage(__('Activation code is required.', 'beyond-seo'));
        }

        try {
            [$success, $message, $result] = $this->processActivationCode($activationCode);
        } catch (Throwable $e) {
            $message = $e->getMessage() ?: __('Invalid response during account activation.', 'beyond-seo');
            $this->redirectToActivationWithMessage($message);
        }

        if (!$success || !$result) {
            $this->redirectToActivationWithMessage($message ?: __('Invalid activation code.', 'beyond-seo'));
        }

        // REF: token handling
        $refreshToken = $result->refreshToken ?? '';
        $accountId = $result->accountId ?? null;
        $setupSetting = $result->locationSetupSetting ?? null;
        $resellerAccount = $result->resellerAccount ?? true;

        if ($refreshToken === '') {
            $this->redirectToActivationWithMessage(__('Missing refresh token in activation response.', 'beyond-seo'));
        }

        add_option(BaseConstants::OPTION_IS_RESELLER_ACCOUNT, $resellerAccount ? 1 : 0);
        update_option(BaseConstants::OPTION_ACTIVATION_CODE, $activationCode);
        update_option(BaseConstants::OPTION_RANKINGCOACH_ACCOUNT_ID, $accountId);
        update_option(BaseConstants::OPTION_LOCATION_SETUP_SETTINGS, $setupSetting);

        try {
            $isHandled = $this->saveRefreshTokenAndFetchData($refreshToken);
        } catch (Throwable $e) {
            $this->redirectToActivationWithMessage($e->getMessage() ?: __('Error fetching account data.', 'beyond-seo'));
        }

        if (!$isHandled) {
            $this->redirectToActivationWithMessage(__('Activation succeeded but data could not be saved.', 'beyond-seo'));
        }

        // Mark activation as complete in flow state
        $store = new OptionStore();
        $store->updateFlowState(function($flowState) {
            $flowState->registered = true;
            $flowState->emailVerified = true;
            $flowState->activated = true;
            return $flowState;
        });

        $this->redirectToActivationSuccess();
    }

    /**
     * Displays only plugin-specific notices on the activation page.
     */
    public function handleActivationNotices(): void
    {
        $current = get_current_screen();
        if (!$current || !str_contains($current->id, 'rankingcoach-activation')) {
            return;
        }

        remove_all_actions('admin_notices');
        remove_all_actions('all_admin_notices');
        $this->displayRankingCoachNotices();
    }

    /** Displays custom messages for activation form result. */
    public function displayRankingCoachNotices(): void
    {
        $formFieldSaved = WordpressHelpers::sanitize_input('GET', 'rc_form_field_saved');
        if (!empty($formFieldSaved)) {
            $status = (int)$formFieldSaved;
            $msg = $status > 0
                    ? __('Refresh token saved successfully.', 'beyond-seo')
                    : __('Failed to save refresh token.', 'beyond-seo');

            printf('<p style="text-align:center;color:#555;margin-bottom:30px;">%s</p>', esc_html($msg));
        }
    }

    /**
     * Validates and processes the activation code through the API.
     *
     * @param string $activationCode
     * @return array{0:bool,1:?string,2:?object}
     */
    private function processActivationCode(string $activationCode): array
    {
        try {
            $uam = new UserApiManager();
            $result = $uam->checkActivationCode($activationCode);
            if (!$result || !$result->success) {
                return [false, $result->message ?? __('Invalid activation code.', 'beyond-seo'), null];
            }
            return [true, null, $result];
        } catch (Exception $e) {
            return [false, $e->getMessage(), null];
        }
    }

    /**
     * Saves the refresh token and fetches account details.
     *
     * @param string $refreshToken
     * @return bool
     * @throws ReflectionException
     * @throws Throwable
     * @throws HttpApiException
     */
    private function saveRefreshTokenAndFetchData(string $refreshToken): bool
    {
        $currentToken = get_option(TokensManager::REFRESH_TOKEN);
        $tokens = TokensManager::instance();

        if ($currentToken !== $refreshToken && $tokens->generateAndSaveAccessToken($refreshToken)) {
            $account = UserApiManager::getInstance(bearerToken: true)->fetchAndInsertAccountData(true);
            do_action(Hooks::RANKINGCOACH_ACTION_COLLECT_DATA_FROM_ALL_AVAILABLE_COLLECTORS, 'activation');
            return $account !== false;
        }
        return false;
    }

    /**
     * Validates user permissions and nonce integrity.
     */
    public function checkUserPermissions(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions.', 'beyond-seo'));
        }

        $nonce = WordpressHelpers::sanitize_input('POST', '_wpnonce');
        if (empty($nonce) || !wp_verify_nonce($nonce, self::SAVE_ACTIVATION_ACTION)) {
            wp_die(esc_html__('Nonce verification failed.', 'beyond-seo'));
        }
    }


    /**
     * If enabled via $flowGuardEnabled, redirect according to flow decision when activation isn't the next step.
     * Kept separate to make it easy to toggle/remove without touching page_content.
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
            if ($step !== 'activate') {
                $this->redirectByFlowStep($step, $failCallback);
            }
        } catch (\Throwable $e) {
            // Fail closed: if flow evaluation fails, we do not block the activation page.
        }
    }

    /* ------------------------------------------------------------------------
     * Internal Helpers
     * --------------------------------------------------------------------- */

    /** Retrieves sanitized string parameter from query. */
    private function getQueryParamString(string $key): string
    {
        $value = WordpressHelpers::sanitize_input('GET', $key);
        return !empty($value) ? urldecode($value) : '';
    }

    /** Retrieves integer parameter safely from query. */
    private function getQueryParamInt(string $key): int
    {
        $val = WordpressHelpers::sanitize_input('GET', $key);
        return ($val !== false && $val !== null) ? (int)$val : 0;
    }

    /**
     * Handles redirection based on current flow step.
     *
     * @param string $step
     * @param callable|null $failCallback
     */
    private function redirectByFlowStep(string $step, ?callable $failCallback = null): void
    {
        if (!self::$managerInstance instanceof AdminManager) {
            return;
        }

        $redirects = [
            'email_validation' => 'registration',
            'register' => 'registration',
            'finalizing' => 'registration',
            'onboarding' => 'onboarding',
            'done' => 'main',
            'main' => 'main',
        ];

        $target = $redirects[$step] ?? 'main';
        self::$managerInstance->redirectPage($target);

        if (is_callable($failCallback)) {
            $failCallback();
        }

        exit;
    }
}
