<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class DomainMismatchException
 */
class DomainMismatchException extends BaseException
{
	/**
	 * DomainMismatchException constructor.
	 *
	 * @return string
	 */
	public function getTitle(): string
	{
		return $this->getMessage() ?? __('Domain Mismatch Error', 'beyond-seo');
	}

	/**
	 * @return string
	 */
	public function getDescription(): string
	{
		return __(
			'The domain associated with your activation code does not match your WordPress site URL. This prevents the plugin from being activated correctly.',
			'beyond-seo'
		);
	}

	/**
	 * @return array
	 */
	public function getReasons(): array
	{
		return [
			__('The activation code is associated with a different domain.', 'beyond-seo'),
			__('Your WordPress site URL has changed since the activation code was generated.', 'beyond-seo'),
			__('The activation code was generated for a different website.', 'beyond-seo'),
		];
	}

	/**
	 * @return bool
	 * @noinspection PhpMissingParentCallCommonInspection
	 */
	public function shouldShowFooter(): bool
	{
		return true;
	}

	/**
	 * @return string
	 * @noinspection PhpMissingParentCallCommonInspection
	 */
	public function getFooter(): string
	{
		return __('Please contact support or request a new activation code for your current domain.', 'beyond-seo');
	}
}
