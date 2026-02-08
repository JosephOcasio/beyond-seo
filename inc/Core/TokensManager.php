<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core;

if ( !defined('ABSPATH') ) {
    exit;
}

use Exception;
use RankingCoach\Inc\Exceptions\HttpApiException;
use RankingCoach\Inc\Core\Api\Tokens\TokensApiManager;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Base\Traits\RcInstanceCreatorTrait;
use RankingCoach\Inc\Exceptions\InvalidTokenException;
use RankingCoach\Inc\Exceptions\MissingTokenException;
use ReflectionException;

/**
 * Class TokensManager
 */
class TokensManager
{
    use RcInstanceCreatorTrait;

    protected static ?self $instance = null;
	public const ACCESS_TOKEN = BaseConstants::OPTION_ACCESS_TOKEN;
	public const REFRESH_TOKEN = BaseConstants::OPTION_REFRESH_TOKEN;

    /**
     * TokensManager singleton
     * @return TokensManager
     */
    public static function instance(): TokensManager {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

	/**
	 * TokensManager constructor.
	 *
	 * @param TokensApiManager $tokensApiClient
	 */
	private TokensApiManager $tokensApiClient;

	/**
	 * Update the access and refresh tokens in the database.
	 * @param mixed $accessToken
	 * @param mixed $refreshToken
	 * @return bool
	 */
	public static function updateTokens( mixed $accessToken, mixed $refreshToken ): bool {

		return
			update_option( self::ACCESS_TOKEN, $accessToken ) &&
			update_option( self::REFRESH_TOKEN, $refreshToken );
	}

	/**
	 * Validate the accessToken hash.
	 * @param string|null $token
	 * @param bool $returnExpiration
	 * @return bool|int
	 */
	public static function validateToken( ?string $token = null,  bool $returnExpiration = false ): bool|int {
		list( , $expireAt ) = TokensManager::getJwtParts( $token );

		// Get the current time
		$currentTime = time();

		// Check if the token is expired
		if ( ! $expireAt || ( $expireAt > 0 && $expireAt < $currentTime ) ) {
			return false; // JWT has expired
		}

		if($returnExpiration) {
			return $expireAt;
		}

		return true;
	}

	/**
	 * Check JWT parts if the access token and account ID are set.
	 *
	 * @param string|null $jwt
	 *
	 * @return array
	 */
	public static function getJwtParts( ?string $jwt = null ): array {
		if ( ! $jwt ) {
			return [ null, null ];
		}

		// Split the token into parts
		$parts = explode( '.', $jwt );

		// Check if it has exactly three parts
		if ( count( $parts ) !== 3 ) {
			return [ null, null ];
		}

		// Decode each part
		list( $header, $payload, ) = $parts;

		// Validate Base64-URL encoding for header and payload
		if ( ! TokensManager::isBase64Url( $header ) || ! TokensManager::isBase64Url( $payload ) ) {
			return [ null, null ];
		}

		// Decode the header and payload to verify their JSON structure
		$decodedHeader = json_decode(TokensManager::base64UrlDecode($header), true);
		$decodedPayload = json_decode(TokensManager::base64UrlDecode($payload), true);

		if (!is_array($decodedHeader) || !is_array($decodedPayload)) {
			return [ null, null ];
		}

		// Optionally check required fields in the header or payload
		if (!isset($decodedHeader['alg']) || !isset($decodedHeader['typ'])) {
			return [ null, null ];
		}

		// Check if the 'exp' claim exists
		if ( ! isset( $decodedPayload['expiresAt'] ) || ! isset( $decodedPayload['accountId'] ) ) {
			return [ null, null ];
		}

		return [ $decodedPayload['accountId'], $decodedPayload['expiresAt'] ];
	}

	/**
	 * @param $string
	 *
	 * @return false|int
	 */
	public static function isBase64Url( $string ): bool|int {
		// Check if the string is valid Base64-URL (no padding '=' characters)
		return preg_match( '/^[A-Za-z0-9_\-]+$/', $string );
	}

	/**
	 * Decode a Base64-URL string.
	 *
	 * @param string $string The string to decode.
	 *
	 * @return string The decoded string.
	 */
	public static function base64UrlDecode( string $string): string {
		// Decode Base64-URL by replacing characters and padding if necessary
		$remainder = strlen($string) % 4;
		if ($remainder) {
			$string .= str_repeat('=', 4 - $remainder);
		}
		return base64_decode(strtr($string, '-_', '+/'));
	}

	/**
	 * Read the access token from the database and check if it is valid.
	 */
	private function isAccessTokenValid(): bool {

		$token = get_option(self::ACCESS_TOKEN);
		if (!$token) {
			return false;
		}

		return TokensManager::validateToken($token) === true;
	}

	/**
	 * Read the refresh token from the database and check if it is valid.
	 */
	private function isRefreshTokenValid(): bool {
		$token = get_option(self::REFRESH_TOKEN);
		if (!$token) {
			return false;
		}

		return TokensManager::validateToken($token) === true;
	}

	/**
	 * Get the access token from the database.
	 */
	public function getStoredAccessToken(): string {
		return get_option(self::ACCESS_TOKEN, '');
	}

	/**
	 * Get the refresh token from the database.
	 */
	public function getStoredRefreshToken(): string {
		return get_option(self::REFRESH_TOKEN, '');
	}

    /**
     * Check if the access token exists in the database and it is valid.
     * If not, generate a new access token.
     * @param string|null $context
     * @return string
     * @throws HttpApiException
     * @throws ReflectionException
     */
	public function getAccessToken(?string $context = null): string {
		if ($this->isAccessTokenValid()) {
			return $this->getStoredAccessToken();
		}
		return $this->generateAccessToken();
	}

	/**
	 * Generate a new access token using the refresh token.
	 * @return string
	 * @throws HttpApiException
	 * @throws ReflectionException
	 * @throws Exception
	 */
	private function generateAccessToken(): string {
		$refreshToken = $this->checkRefreshToken(static::class);
		$this->generateAndSaveAccessToken( $refreshToken );
		return $this->getStoredAccessToken();
	}

    /**
     * Check if the refresh token exists in the database and it is valid.
     * If not, generate a new refresh token.
     * @param string|null $context
     * @return string
     * @throws Exception
     */
	public function checkRefreshToken(?string $context = null): string {
		$refreshToken = $this->getStoredRefreshToken();
		if(empty($refreshToken)) {
			$message = 'The token is missing.';
			rceh()->error( (new MissingTokenException( $message ))->throwException(true) );
		}
		if (!$this->isRefreshTokenValid()) {
			$message = 'The refresh token is invalid or expired';
			rceh()->error( new InvalidTokenException( $message ) );
		}
		return $refreshToken;
	}

	/**
	 * @param string $refreshToken
	 *
	 * @return string
	 * @throws HttpApiException
	 * @throws ReflectionException
	 */
	public function generateAndSaveAccessToken( string $refreshToken ): string {
		return TokensApiManager::getInstance()->generateToken( $refreshToken );
	}

    /**
     * Check if the refresh token is expired and generate a new one.
     * @return float
     */
	public function calculateRefreshTokenRemainingDays(): float {

		$refreshToken = $this->getStoredRefreshToken();
		$expiration = self::validateToken($refreshToken, true) ?? 0;

		$currentTime = time();
		$nextCheckTime = $currentTime + 1800; // Add 1/2 hour to the current time

		if(!$expiration || $expiration < $currentTime) {
			return -1;
		}

		$cron_report = [
			'rc_refresh_token_validation_status' => [
				'days_validity' => round(( $expiration - $currentTime ) / (24 * 60 * 60)),
				'last_check_time' => gmdate('Y-m-d H:i:s', $currentTime),
				'next_check_time' => gmdate('Y-m-d H:i:s', $nextCheckTime)
			]
		];
		/** @var CronJobManager $cronManager */
		$cronManager = CronJobManager::instance();
		$cronManager->saveRefreshTokenRemainingDays($cron_report);

		return round(($currentTime - $expiration) / (24 * 60 * 60)) ?? 0;
	}
}
