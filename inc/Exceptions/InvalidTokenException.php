<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\ChannelFlow\ChannelResolver;
use RankingCoach\Inc\Core\ChannelFlow\OptionStore;

/**
 * InvalidTokenException
 */
class InvalidTokenException extends BaseException
{
	/**
	 * Get the title for the error.
	 *
	 * @return string
	 */
	public function getTitle(): string
	{
		return __('Invalid Token', 'beyond-seo');
	}

	/**
	 * Get the description for the error.
	 *
	 * @return string
	 */
	public function getDescription(): string
	{
		return __(
			'The provided token is invalid. This may prevent secure communication with external APIs.',
			'beyond-seo'
		);
	}

	/**
	 * Get the reasons for the error.
	 *
	 * @return array
	 */
	public function getReasons(): array
	{
		return [
			__('The token is invalid or malformed.', 'beyond-seo'),
			__('The token is not properly set.', 'beyond-seo'),
			__('The token might be empty or missing.', 'beyond-seo'),
		];
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
	 * Get the footer content.
	 *
	 * @return string
	 * @noinspection PhpMissingParentCallCommonInspection
	 */
	public function getFooter(): string
	{
        $store = new OptionStore();
        $resolver = new ChannelResolver($store);
        $channel = $resolver->resolve();
        if($channel === 'ionos') {
            return sprintf(
                __('Your refresh token has expired. Please %s to regenerate it.', 'beyond-seo'),
                '<a href="' . admin_url('admin.php?page=rankingcoach-activation&bypass_flow=1') . '">' . __('re-activate the plugin', 'beyond-seo') . '</a>'
            );
        }
        elseif($channel === 'direct') {
            return sprintf(
                __('Your refresh token has expired. Please %s to regenerate it.', 'beyond-seo'),
                '<a href="' . admin_url('admin.php?page=rankingcoach-registration&bypass_flow=1') . '">' . __('re-register the plugin', 'beyond-seo') . '</a>'
            );
        }
        return '';
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
                color: #f39c12;
            }
            .rc-error-body ul {
                margin-left: 20px;
                color: chocolate;
            }
        ';
	}
}
