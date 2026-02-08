<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class IncompleteOnboardingException
 */
class IncompleteOnboardingException extends BaseException
{
	/**
	 * IncompleteOnboardingException constructor.
	 *
	 * @return string
	 */
	public function getTitle(): string
	{
		return $this->getMessage() ?? __('Onboarding Incomplete Error', 'beyond-seo');
	}

	/**
	 * @return string
	 */
	public function getDescription(): string
	{
		return __(
			'This is a dedicated rankingCoach account that requires completed onboarding before the plugin can be activated. The onboarding process must be finalized in the rankingCoach platform.',
			'beyond-seo'
		);
	}

	/**
	 * @return array
	 */
	public function getReasons(): array
	{
		return [
			__('The onboarding process has not been completed in your rankingCoach account.', 'beyond-seo'),
			__('Plugin activation is blocked until all required setup steps are finished.', 'beyond-seo'),
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
		return __('Please complete the onboarding process in your rankingCoach platform account, then try activating the plugin again.', 'beyond-seo');
	}
}
