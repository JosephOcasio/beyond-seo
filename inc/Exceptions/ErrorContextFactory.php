<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Exception;

/**
 * Class ErrorContextFactory
 */
class ErrorContextFactory {

	/**
	 * Create an error context based on the exception type.
	 *
	 * @param Exception $exception The exception object.
	 * @return array The error context.
	 */
	public static function create(Exception $exception): array
	{
		$baseContext = [
			'debugMode' => defined('RANKINGCOACH_WP_DEBUG') && RANKINGCOACH_WP_DEBUG,
			'error' => $exception->getMessage(),
		];

		if ($exception instanceof BaseException) {
			$mapping =  [
				'title' => $exception->getTitle(),
				'description' => $exception->getDescription(),
				'reasons' => $exception->getReasons(),
				'styles' => $exception->getStyles(),
				'showFooter' => $exception->shouldShowFooter(),
				'footer' => $exception->getFooter(),
			];
			return array_merge($mapping, $baseContext);
		}

		return array_merge([
			'title' => __('Unexpected Error', 'beyond-seo'),
			'description' => !empty($baseContext['error']) ? $baseContext['error'] : __('An unexpected error occurred.', 'beyond-seo'),
			'reasons' => [
				__('Unhandled exception type.', 'beyond-seo'),
				__('Contact support if the issue persists.', 'beyond-seo')
			],
			'showFooter' => false,
		], $baseContext);
	}
}
