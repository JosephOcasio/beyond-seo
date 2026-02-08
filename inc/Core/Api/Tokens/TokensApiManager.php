<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Api\Tokens;

use Exception;
use RankingCoach\Inc\Core\Plugin\RankingCoachPlugin;
use RankingCoach\Inc\Exceptions\HttpApiException;
use RankingCoach\Inc\Core\Api\HttpApiClient;
use RankingCoach\Inc\Core\TokensManager;
use RankingCoach\Inc\Exceptions\InvalidResponseException;
use RankingCoach\Inc\Core\Helpers\CoreHelper;
use ReflectionException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use function rceh;

/**
 * Class TokensApiManager
 */
class TokensApiManager extends HttpApiClient
{

	/** @var TokensApiManager|null $instance Singleton instance */
	protected static ?TokensApiManager $instance = null;

    /** @var TokensApiManager[] $instances Array of instances keyed by bearerToken */
    protected static array $instances = [];

	private bool $initialized = false;
	public static bool $useCache = false;
	public static bool $devMode = true;

	/** @var array $configuration The configuration for the API. */
	protected array $configuration;

	/**
	 * Get the singleton instance.
	 *
	 * @param array $defaultHeaders
	 * @param HttpClientInterface|null $client
	 * @param bool $bearerToken
	 *
	 * @return TokensApiManager
	 * @throws HttpApiException
	 * @throws ReflectionException
	 * @throws Exception
	 */
	public static function getInstance( array $defaultHeaders = [], ?HttpClientInterface $client = null, bool $bearerToken = false ): TokensApiManager {
        $key = $bearerToken ? 'with_token' : 'without_token';
        if ( ! isset(self::$instances[$key]) ) {
			$accessToken = null;
			if($bearerToken) {
				/** @var TokensManager $tokensManager */
                $tokensManager = TokensManager::instance();
				$accessToken = $tokensManager->getStoredAccessToken();
				if (TokensManager::validateToken($accessToken, false) === false) {
					$refreshToken = $tokensManager->getStoredRefreshToken();
					$tokensManager->generateAndSaveAccessToken($refreshToken);
					$accessToken = $tokensManager->getStoredAccessToken();
				}
			}
			self::$instances[$key] = new self($client, $defaultHeaders, $accessToken);
		}

		return self::$instances[$key];
	}

	/**
	 * TokensApiManager constructor.
	 *
	 * @param HttpClientInterface|null $client
	 * @param array $defaultHeaders
	 * @param string|null $accessToken
	 */
	public function __construct(
		?HttpClientInterface $client,
		array $defaultHeaders = [],
		?string $accessToken = null
	) {

		parent::__construct($client, $defaultHeaders, $accessToken);
	}

	/**
	 * Initialize API configuration.
	 */
	public function initialize(): void
	{
		if ($this->initialized) {
			return;
		}
		$currentConfig = require RANKINGCOACH_PLUGIN_APP_DIR . 'config/app/externalIntegrations.php';
		$this->configuration = $currentConfig;

		// Set prefix based on production mode
		if (RankingCoachPlugin::isProductionMode()) {
			$this->configuration['prefix'] = $this->configuration['liveEnv'];
		} else {
			// In dev environment, check for testing_environment option
			$this->configuration['prefix'] = get_option('testing_environment', $this->configuration['devEnv']);
		}

		$this->initialized = true;
	}

	/**
	 * Set the URL for the API with automatic configuration handling.
	 * 
	 * @param string $url The API endpoint (e.g., 'refresh')
	 * @param bool $addDebugParams Whether to add debug and noCache parameters
	 * 
	 * @throws Exception
	 */
	public function setUrl(string $url, bool $addDebugParams = true): void
	{
		if (!$this->initialized) {
			$this->initialize();
		}

		$baseUrl = sprintf($this->configuration['refreshUrl'], $this->configuration['prefix']);
		
		$finalUrl = $baseUrl . $url;
		
		if ($addDebugParams && !self::$useCache) {
			$finalUrl .= '?noCache=1&debug=1';
		}
		
		parent::setUrl($finalUrl);
	}

	/**
	 * Handle the response for token-related operations.
	 *
	 * @param array $response
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function handleResponse(array $response): bool
	{
		if(!empty($response->error)) {
			rceh()->error( new InvalidResponseException( 'Invalid API response during generate the refresh token. Error:' . $response->error ), json_encode($response) );
		}
		if (empty($response['content']) ?? false) {
			rceh()->error( new InvalidResponseException( 'Invalid API response during generate the refresh token.' ), json_encode($response) );
		}
		$response = $response['content'];

		if (empty($response->refreshToken) ?? false) {
			rceh()->error( new InvalidResponseException( 'Invalid API response during generate the refresh token.' ), json_encode($response) );
		}

		$refreshToken = $response->refreshToken;
		$accessToken = $response->accessToken;

		return TokensManager::updateTokens($accessToken, $refreshToken);
	}

    /**
     * Refresh the token by making an API call.
     *
     * @param string $refreshToken
     * @return string
     * @throws HttpApiException
     * @throws Exception
     */
	public function generateToken(string $refreshToken): string
	{
		// Set the URL for the refresh endpoint
		$this->setUrl('refresh');

		// Generate payload using the modern approach with security metadata
		$payload = CoreHelper::generateCommonSecurityPayload([
			'refreshToken' => $refreshToken,
		]);

		// Prepare security headers using the refresh token for HMAC signature
		$this->prepareSecurityHeaders($refreshToken, $payload);

		// Make the POST request
		$response = $this->post($payload);
		$this->handleResponse($response);

		/** @var TokensManager $tokenManager */
		$tokenManager = TokensManager::instance();
		return $tokenManager->getStoredAccessToken();
	}
}