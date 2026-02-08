<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core\Admin\Pages;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Exception;
use RankingCoach\Inc\Core\Admin\AdminManager;
use RankingCoach\Inc\Core\Admin\AdminPage;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\ChannelFlow\OptionStore;
use RankingCoach\Inc\Core\ChannelFlow\Traits\FlowGuardTrait;
use RankingCoach\Inc\Core\Helpers\CoreHelper;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Core\Jobs\AccountSyncJob;
use RankingCoach\Inc\Core\Plugin\RankingCoachPlugin;
use RankingCoach\Inc\Core\TokensManager;
use RankingCoach\Inc\Exceptions\HttpApiException;
use RankingCoach\Inc\Exceptions\InvalidTokenException;
use RankingCoach\Inc\Traits\SingletonManager;
use ReflectionException;
use RankingCoach\Inc\Core\Settings\SettingsManager;

use Throwable;
use function rceh;

/**
 * Class IframePage
 *
 * Singleton AdminIframePage Class
 * @method IframePage getInstance(): static
 */
class IframePage extends AdminPage
{
    use SingletonManager;
    use RcLoggerTrait;
    use FlowGuardTrait;

    public string $name = 'main';

    public static ?AdminManager $managerInstance = null;

    /** Feature flag: when true, IframePage will use FlowManager to guard access (step must be 'main' or 'done'). */
    private bool $flowGuardEnabled = false;

    /**
     * IframePage constructor.
     * Initializes the IframePage instance.
     */
    public function __construct() {
        parent::__construct();
        $this->flowGuardEnabled = OptionStore::isFlowGuardActive();
    }

    /**
     * @return string
     */
    public function page_name(): string
    {
        return $this->name;
    }

    /**
     * Main page content renderer (dashboard iframe).
     * - Decoupled cookie detection UI into template: views/cookie/third-party-cookie-warning.php
     * - Decoupled iframe UI into template: views/iframe-page.php
     * - Optional Flow guard (feature-flagged)
     *
     * @param callable|null $failCallback
     * @return void
     * @throws HttpApiException
     * @throws ReflectionException
     * @throws Throwable
     */
    public function page_content(?callable $failCallback = null): void
    {
        // Optional flow guard (disabled by default; enable by flipping $this->>flowGuardEnabled)
        $this->applyFlowGuard($failCallback);

        /** @var TokensManager $tokensManager */
        $tokensManager = TokensManager::instance();
        $refreshToken = $tokensManager->getStoredRefreshToken();
        $accessToken  = $tokensManager->getStoredAccessToken();
        $locationId   = (int) get_option(BaseConstants::OPTION_RANKINGCOACH_LOCATION_ID, 0);

        // Ensure we have a valid access token (refresh if needed)
        if (!$tokensManager::validateToken($accessToken)) {
            $tokensManager->generateAndSaveAccessToken($refreshToken);
            $accessToken = $tokensManager->getStoredAccessToken();
        }
        if (!$accessToken || !$tokensManager::validateToken($accessToken)) {
            rceh()->error(new InvalidTokenException('The access token is invalid or expired'));
        }

        // Handle 'ref' query parameter for account sync
        // ===============================================
        // If 'ref=account_sync' means that a successful upsell occurred and we need to trigger an account sync
        // ===============================================
        $ref = WordpressHelpers::sanitize_input('GET', 'ref');
        if ($ref === 'account_sync') {
            try {
                $accountSyncJob = AccountSyncJob::instance();
                $syncSuccess = $accountSyncJob->forceSync();
                if ($syncSuccess) {
                    $this->log('Account sync triggered by ref parameter', 'INFO');
                } else {
                    $this->log('Account sync failed', 'ERROR');
                }
            } catch (Exception $e) {
                $this->log('Error during account sync: ' . $e->getMessage(), 'ERROR');
            }
        }

        // Build iframe URL from config
        $config    = require RANKINGCOACH_PLUGIN_APP_DIR . 'config/app/externalIntegrations.php';
        $language  = WordpressHelpers::current_language_code_helper(WordpressHelpers::get_wp_locale()) ?? 'en';
        $locale    = WordpressHelpers::get_wp_locale();
        $baseEnv   = RankingCoachPlugin::isProductionMode() ? 'liveEnv' : 'devEnv';
        $installationId = (string)get_option(BaseConstants::OPTION_INSTALLATION_ID, '');
        $parentOrigin = urlencode(site_url());
        $iframeUrl = sprintf($config['iframeUrl'], $config[$baseEnv], $language, $locationId, $installationId, $parentOrigin, $accessToken);
        if(get_option(BaseConstants::OPTION_RANKINGCOACH_COUPON_CODE)) {
            $couponCode = (string)get_option(BaseConstants::OPTION_RANKINGCOACH_COUPON_CODE);
            $iframeUrl = sprintf($config['codeUrl'], $config[$baseEnv], $locale, $couponCode, urlencode($iframeUrl));
        }

        $settingsManager = SettingsManager::instance();
        $openInNewTab = (bool)$settingsManager->get_option('open_rc_dashboard_in_new_tab', false);
        $highestPlan = CoreHelper::isHighestPaid();

        // 1) Cookie detection UI (JS + warning overlay)
        include __DIR__ . '/views/cookie/third-party-cookie-warning.php';
        // 2) Iframe UI (skeleton + main iframe)
        include __DIR__ . '/views/iframe-page.php';

        // Include FlowGuard components if enabled
        if (defined('BSEO_FLOW_GUARD_ENABLED') && BSEO_FLOW_GUARD_ENABLED) {
            include __DIR__ . '/views/flowguard-button.php';
            include __DIR__ . '/views/flowguard-panel.php';
        }
    }

    /**
     * Flow guard for IframePage (dashboard). Allowed when flow step is "main" or "done".
     * Otherwise redirect to the corresponding step page.
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
            $channel = $result['channel'] ?? '';

            // Allowed steps for the dashboard
            if ($step === 'done') {
                return; // proceed rendering
            }

            // Redirect mapping
            $destination = match ($step) {
                'activate', 'email_validation', 'register', 'finalizing' => 'activation',
                'onboarding'                                  => 'onboarding',
                default                                       => 'main',
            };

            if (self::$managerInstance instanceof AdminManager) {
                self::$managerInstance->redirectPage($destination);
            }
            if (is_callable($failCallback)) {
                $failCallback();
            }
            exit;
        } catch (Throwable $e) {
            // Fail-open: if evaluation fails, let the page render
            return;
        }
    }
}
