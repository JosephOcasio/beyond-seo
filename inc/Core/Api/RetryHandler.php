<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core\Api;

use RankingCoach\Inc\Exceptions\HttpApiException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * Class RetryHandler
 */
class RetryHandler {
	private int $maxRetries;
	private int $backoffFactor;

	/**
	 * RetryHandler constructor.
	 *
	 * @param int $maxRetries
	 * @param int $backoffFactor
	 */
	public function __construct( int $maxRetries = 3, int $backoffFactor = 2 ) {
		$this->maxRetries    = $maxRetries;
		$this->backoffFactor = $backoffFactor;
	}

	/**
	 * Execute a request function with retries.
	 *
	 * @param callable $requestFunction
	 *
	 * @return mixed
	 * @throws HttpApiException
	 */
	public function execute( callable $requestFunction ): mixed {
		$retries = $this->maxRetries;

		while ( $retries > 0 ) {
			try {
				return $requestFunction();
			} catch ( TransportExceptionInterface $e ) {
				$retries --;
				if ( $retries === 0 ) {
                    // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					throw new HttpApiException( esc_html__('Request failed after multiple retries: ', 'beyond-seo') . $e->getMessage() );
				}
				sleep( $this->backoffFactor ** ( $this->maxRetries - $retries ) );
			}
		}

		return [];
	}
}
