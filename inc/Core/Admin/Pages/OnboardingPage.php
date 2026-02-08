<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core\Admin\Pages;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\Admin\AdminManager;
use RankingCoach\Inc\Core\Admin\AdminPage;
use RankingCoach\Inc\Core\ChannelFlow\ChannelResolver;
use RankingCoach\Inc\Core\ChannelFlow\OptionStore;
use RankingCoach\Inc\Core\ChannelFlow\Traits\FlowGuardTrait;
use RankingCoach\Inc\Core\Frontend\ViteApp\ReactApp;
use RankingCoach\Inc\Core\Helpers\JavaScriptHelper;
use RankingCoach\Inc\Core\TokensManager;
use RankingCoach\Inc\Exceptions\HttpApiException;
use RankingCoach\Inc\Traits\SingletonManager;
use ReflectionException;
use Throwable;

/**
 * Represents a page in the onboarding process.
 * @method OnboardingPage getInstance(): static
 */
class OnboardingPage extends AdminPage {

    use FlowGuardTrait;
    use SingletonManager;

    public string $name = 'onboarding';

	public static AdminManager|null $managerInstance = null;

	/** Feature flag: when true, OnboardingPage will use FlowManager to guard access (next_step must be 'onboarding'). */
    private bool $flowGuardEnabled = false;

    /**
     * OnboardingPage constructor.
     * Initializes the OnboardingPage instance and sets up the necessary hooks.
     */
	public function __construct() {
		add_action('current_screen', function($screen) {
			// Ensure the screen object is available
			if (!is_object($screen) || !isset($screen->id)) {
				return;
			}

			if ($screen->base === 'admin_page_rankingcoach-onboarding') {
				ReactApp::get([
					'onboarding'
				]);
			}
		});
         $this->flowGuardEnabled = OptionStore::isFlowGuardActive();
         parent::__construct();
	}

	/**
	 * @return string
	 */
	public function page_name(): string
	{
		return $this->name;
	}

    /**
     * Handles the generation or processing of page content within the application.
     *
     * @param callable|null $failCallback
     * @return void
     * @throws HttpApiException
     * @throws ReflectionException
     */
    public function page_content(?callable $failCallback = null): void
    {
        // Retrieve channel metadata and flow state for FlowGuard components
        $channelMeta = OptionStore::retrieveChannel();
        $flowState = OptionStore::retrieveFlowState();

        // Optional flow guard (disabled by default; see $this->flowGuardEnabled)
        $this->applyFlowGuard($failCallback);

        try {
            /** @var TokensManager $tokensManager */
            $tokensManager = TokensManager::instance();
            $accessToken = $tokensManager->getAccessToken(static::class);
        } catch (Throwable $e) {
            // Use ChannelResolver for consistent channel detection
            $store = new OptionStore();
            $resolver = new ChannelResolver($store);
            $channel = $resolver->resolve();
            
            // Reset flow state since token is invalid
            $store->updateFlowState(function($flowState) {
                $flowState->registered = false;
                $flowState->emailVerified = false;
                $flowState->activated = false;
                return $flowState;
            });
            
            // Redirect based on channel
            $nextPage = ($channel === 'ionos' || $channel === 'extendify')
                ? AdminManager::PAGE_ACTIVATION
                : AdminManager::PAGE_REGISTRATION;
            
            $nextStepUrl = AdminManager::getPageUrl($nextPage);
            wp_safe_redirect($nextStepUrl);
            exit; // IMPORTANT: Add exit after redirect
        }

        include __DIR__ . '/views/onboarding-page.php';

        // Add login session expiration handler script
        // This script will handle the login modal state and refresh the page when the modal is closed.
        // Is not a mistake to add this script here.
        // The behaviour is happening on the login modal, which is opened when the user session expires.
        JavaScriptHelper::renderLoginSessionExpirationScript();
    }

    /**
     * Evaluate the current flow using FlowManager (read-only).
     * @return array{channel?:string,next_step?:string,description?:string,meta?:mixed}
     */

    /**
     * If enabled via $flowGuardEnabled, redirect according to flow decision when onboarding isn't the next step.
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

            if ($step === 'onboarding') {
                return; // allowed, continue rendering page
            }

            // Redirect mapping consistent with existing behavior
            if ($step === 'activate' || $step === 'register' || $step === 'email_validation' || $step === 'finalizing') {
                if (self::$managerInstance instanceof AdminManager) {
                    self::$managerInstance->redirectPage('activation');
                }
            } elseif ($step === 'done') {
                if (self::$managerInstance instanceof AdminManager) {
                    self::$managerInstance->redirectPage('main');
                }
            } else {
                // Unknown step fallback
                if (self::$managerInstance instanceof AdminManager) {
                    self::$managerInstance->redirectPage('main');
                }
            }

            if (is_callable($failCallback)) {
                $failCallback();
            }
            exit;
        } catch (\Throwable $e) {
            // Fail-open: if evaluation fails, let the page render
            return;
        }
    }
 }
