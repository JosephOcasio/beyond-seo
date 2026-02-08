<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core\Admin\Pages;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\Admin\AdminManager;
use RankingCoach\Inc\Core\Admin\AdminPage;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\ChannelFlow\Traits\FlowGuardTrait;
use RankingCoach\Inc\Core\Frontend\ViteApp\ReactApp;
use RankingCoach\Inc\Core\Helpers\JavaScriptHelper;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Core\TokensManager;
use RankingCoach\Inc\Traits\SingletonManager;

/**
 * Represents a page for general settings.
 * @method GeneralSettingsPage getInstance(): static
 */
class GeneralSettingsPage extends AdminPage {

	use SingletonManager;
    use RcLoggerTrait;
    use FlowGuardTrait;

	public string $name = 'generalSettings';

	public static AdminManager|null $managerInstance = null;

    /**
     * GeneralSettingsPage constructor.
     * Initializes the GeneralSettingsPage instance and sets up the necessary hooks.
     */
	public function __construct() {
		add_action('current_screen', function($screen) {
			// Ensure the screen object is available
			if (!is_object($screen) || !isset($screen->id)) {
				return;
			}

			if (
                $screen->base === RANKINGCOACH_BRAND_SLUG . '_page_rankingcoach-' . $this->name ||
                $screen->base === 'admin_page_rankingcoach-' . $this->name
            ) {
				ReactApp::get([
					'generalSettings'
				]);
			}
		});
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
	 */
	public function page_content(?callable $failCallback = null): void {

		/** @var TokensManager $tokensManager */
		$tokensManager = TokensManager::instance();
		$refreshToken = $tokensManager->getStoredRefreshToken();

        if(!WordpressHelpers::isActivationCompleted()) {

            $result = $this->evaluateFlow();
            //$step   = $result['next_step'] ?? '';
            $channel = $result['channel'] ?? '';

            // Redirect mapping
            $destination = match ($channel) {
                'ionos', 'direct' => 'activation',
                default => 'activation',
            };

			if (self::$managerInstance instanceof AdminManager) {
				self::$managerInstance->redirectPage($destination);
			}
            if(is_callable($failCallback)) {
                $failCallback();
            }
            wp_die();
		}

        // always show general settings even if onboarding is not completed
		/*if(!WordpressHelpers::isOnboardingCompleted()) {
			if (self::$managerInstance instanceof AdminManager) {
				self::$managerInstance->redirectPage('onboarding');
			}
			if(is_callable($failCallback)) {
				$failCallback();
			}
			wp_die();
		}*/

		echo wp_kses('<div id="generalSettings-rankingcoach-page" style="position: fixed; top: 0; left: 0; width: 100vw; background: #fff; height: 100vh; justify-items: center; align-content: center; overflow-y: scroll;"></div>', [
			'div' => [
				'id' => [],
				'style' => []
			]
		]); //z-index: 1000 for full screen

		// Add login session expiration handler script
        // This script will handle the login modal state and refresh the page when the modal is closed.
        // Is not a mistake to add this script here.
        // The behaviour is happening on the login modal, which is opened when the user session expires.
		JavaScriptHelper::renderLoginSessionExpirationScript();
	}
}
