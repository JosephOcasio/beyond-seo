<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class InvalidResponseException
 */
class InvalidResponseException extends BaseException
{
	/**
	 * InvalidResponseException constructor.
	 *
	 * @return string
	 */
	public function getTitle(): string
	{
		return $this->getMessage() ?? __('Invalid JSON Response', 'beyond-seo');
	}

	/**
	 * @return string
	 */
	public function getDescription(): string
	{
		return __(
			'The response received from the external API is invalid or malformed. This may prevent the plugin from functioning correctly.',
			'beyond-seo'
		);
	}

	/**
	 * @return array
	 */
	public function getReasons(): array
	{
		return [
			__('The response is not in the expected format.', 'beyond-seo'),
			__('The response is empty or missing.', 'beyond-seo'),
			__('The response is not properly set.', 'beyond-seo'),
		];
	}

	/**
	 * @return bool
	 * @noinspection PhpMissingParentCallCommonInspection
	 */
	public function shouldShowFooter(): bool
	{
		return false;
	}

	/**
	 * @return string
	 * @noinspection PhpMissingParentCallCommonInspection
	 */
	public function getFooter(): string
	{
		return '';
	}
}
