<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\Admin\AdminManager;

/**
 * PluginActivationException
 */
class PluginActivationException extends BaseException {

	/**
	 * Returns the title.
	 *
	 * @return string
	 */
	public function getTitle(): string {
		return __('Plugin Activation Failed', 'beyond-seo');
	}

	/**
	 * Returns the description.
	 *
	 * @return string
	 */
	public function getDescription(): string {
		return __(
			'An issue occurred during the plugin activation process, preventing successful initialization.',
			'beyond-seo'
		);
	}

	/**
	 * Returns the reasons.
	 *
	 * @return array
	 */
	public function getReasons(): array {
		return [
			__('Possible connection issues with the external API.', 'beyond-seo'),
			__('Server configuration conflicts or incompatible environment settings.', 'beyond-seo'),
		];
	}

	/**
	 * Get the footer content.
	 *
	 * @return string
	 * @noinspection PhpMissingParentCallCommonInspection
	 */
	public function getFooter(): string {
		return sprintf(
            // translators: %s is a link to configure a new refresh token manually
			__('Click here to <a href="%s" class="invalid-token-button">configure a new refresh token manually</a>.', 'beyond-seo'),
			esc_url(AdminManager::getPageUrl(AdminManager::PAGE_ACTIVATION))
		);
	}

	/**
	 * Determine if the footer should be shown.
	 *
	 * @return bool
	 * @noinspection PhpMissingParentCallCommonInspection
	 */
	public function shouldShowFooter(): bool
	{
		return true;
	}

	/**
	 * Get additional styles for the error page.
	 *
	 * @return string
	 * @noinspection PhpMissingParentCallCommonInspection
	 */
	public function getStyles(): string
	{
		return '
            .rc-error-header {
                font-size: 1.5em;
                text-align: center;
                color: #d9534f;
                margin-bottom: 15px;
            }
            .rc-error-body ul {
                margin-left: 20px;
                color: #333;
            }
        ';
	}
}
