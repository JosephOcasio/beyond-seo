<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core\Api\Feedback;

use Exception;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\Helpers\CoreHelper;
use RankingCoach\Inc\Core\Plugin\RankingCoachPlugin;
use RankingCoach\Inc\Core\TokensManager;
use RankingCoach\Inc\Exceptions\HttpApiException;
use RankingCoach\Inc\Core\Api\HttpApiClient;
use ReflectionException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Class FeedbackApiManager
 * Handles feedback submission to RankingCoach API
 */
class FeedbackApiManager extends HttpApiClient
{
    use RcLoggerTrait;

	/**
	 * Singleton instance
	 */
	protected static ?FeedbackApiManager $instance = null;

    /** @var array|null $configuration The external integrations configuration */
    protected ?array $configuration = null;

    /**
	 * Get the singleton instance.
	 *
	 * @param array $defaultHeaders
	 * @param HttpClientInterface|null $client
	 * @param bool|null $bearerToken
	 *
	 * @return FeedbackApiManager
	 * @throws HttpApiException
	 * @throws ReflectionException
	 * @throws Exception
	 */
	public static function getInstance( array $defaultHeaders = [], ?HttpClientInterface $client = null, ?bool $bearerToken = false ): FeedbackApiManager {
		if ( ! self::$instance ) {
			if($bearerToken) {
				$accessToken = self::handleTokenValidation();
			}
			self::$instance = new self($client, $defaultHeaders, $accessToken ?? null);
		}

		return self::$instance;
	}

	/**
	 * Handle token validation
	 *
	 * @return string|null
	 * @throws Exception
	 */
	public static function handleTokenValidation(): ?string {
		/** @var TokensManager $tokensManager */
        $tokensManager = TokensManager::instance();
		$accessToken = $tokensManager->getStoredAccessToken();
		if (TokensManager::validateToken($accessToken) === false) {
			$refreshToken = $tokensManager->getStoredRefreshToken();
			$tokensManager->generateAndSaveAccessToken($refreshToken);
			$accessToken = $tokensManager->getStoredAccessToken();
		}
		return $accessToken;
	}

	/**
	 * FeedbackApiManager constructor.
	 *
	 * @param HttpClientInterface|null $client
	 * @param array $defaultHeaders
	 * @param string|null $accessToken
	 *
	 * @throws Exception
	 */
	public function __construct(
		?HttpClientInterface $client = null,
		array $defaultHeaders = [],
		?string $accessToken = null
	) {
		parent::__construct($client, $defaultHeaders, $accessToken);
	}

	/**
	 * Submit deactivation feedback to the API.
	 *
	 * @param string $reasonCode The reason code for deactivation
	 * @param string $feedbackText The feedback text from the user
	 * @param bool $deleteProject Whether to delete project data on deactivation
	 * @param bool $cancelAccount Whether to cancel the account
	 *
	 * @return bool True if feedback was submitted successfully, false otherwise
     * @throws Exception
	 */
	public function submitFeedback(
		string $reasonCode,
		string $feedbackText = '',
		bool $deleteProject = false,
		bool $cancelAccount = false
	): bool {
		try {
			// Set the API endpoint URL to publicApi
			$this->setUrl('feedback', 'publicApi', false);

			// Prepare the security payload with feedback data
			$payload = CoreHelper::generateCommonSecurityPayload([
				'reasonCode' => $reasonCode,
				'feedbackText' => $feedbackText,
				'deleteProject' => $deleteProject,
				'cancelAccount' => $cancelAccount,
			]);

			$this->prepareSecurityHeaders($this->getBearerToken(), $payload);

			// Send the POST request to the API
			$response = $this->post($payload);

			// Log the request
			$this->log_json([
				'operation_type' => 'feedback_submission',
				'operation_status' => 'success',
				'context_entity' => 'feedback',
				'context_type' => 'deactivation',
				'metadata' => [
					'reason_code' => $reasonCode,
					'delete_project' => $deleteProject,
					'cancel_account' => $cancelAccount,
					'response' => $response,
				],
			], 'feedback');

			return true;
		} catch ( Exception $e ) {
			$this->log_json([
				'operation_type' => 'feedback_submission',
				'operation_status' => 'error',
				'context_entity' => 'feedback',
				'context_type' => 'deactivation',
				'error_details' => [
					'exception_message' => $e->getMessage(),
					'exception_code' => $e->getCode(),
					'exception_file' => $e->getFile(),
					'exception_line' => $e->getLine(),
				],
			], 'feedback');

			return false;
		}
	}

    /**
     * Set the URL for the API with automatic configuration handling.
     *
     * @param string $url The API endpoint (e.g., 'categories', 'checkAccount', etc.)
     * @param string $urlType The type of URL to use ('baseUrl', 'publicApi', 'collectorsApi')
     * @param bool $addDebugParams Whether to add debug and noCache parameters
     *
     * @throws Exception
     */
    public function setUrl(string $url, string $urlType = 'baseUrl', bool $addDebugParams = true): void
    {
        $this->loadConfiguration();

        $baseUrl = match ($urlType) {
            'publicApi' => sprintf($this->configuration['publicApi'], $this->configuration['prefix']),
            'collectorsApi' => sprintf($this->configuration['collectorsApi'], rtrim(home_url(), '/')),
            default => sprintf($this->configuration['baseUrl'], $this->configuration['prefix']),
        };

        $finalUrl = $baseUrl . $url;

        if ($addDebugParams) {
            $finalUrl .= '?debug=1&noCache=1';
        }

        parent::setUrl($finalUrl);
    }

    /**
     * Load configuration for API URLs based on environment.
     * @return void
     */
    protected function loadConfiguration(): void
    {
        if ($this->configuration === null) {
            $this->configuration = require RANKINGCOACH_PLUGIN_APP_DIR . 'config/app/externalIntegrations.php';

            // Set prefix based on production mode
            if (RankingCoachPlugin::isProductionMode()) {
                $this->configuration['prefix'] = $this->configuration['liveEnv'];
            } else {
                // In dev environment, check for testing_environment option
                $this->configuration['prefix'] = get_option('testing_environment', $this->configuration['devEnv']);
            }
        }
    }

}
