<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core\Api\User;

use Exception;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\DB\DatabaseManager;
use RankingCoach\Inc\Core\DB\DatabaseTablesManager;
use RankingCoach\Inc\Core\Helpers\CoreHelper;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Core\Plugin\RankingCoachPlugin;
use RankingCoach\Inc\Core\UrlChangeDetector;
use RankingCoach\Inc\Exceptions\HttpApiException;
use RankingCoach\Inc\Core\Api\HttpApiClient;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\TokensManager;
use ReflectionException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;
use WP_Error;

/**
 * Class UserApiManager
 */
class UserApiManager extends HttpApiClient
{

    use RcLoggerTrait;

	/** @var UserApiManager[] $instances Array of instances keyed by bearerToken */
	protected static array $instances = [];

	/** @var int $userId The current user ID */
	protected int $userId;

	/** @var array|null $configuration The external integrations configuration */
	protected ?array $configuration = null;

	/**
	 * Get the singleton instance.
	 *
	 * @param array $defaultHeaders
	 * @param HttpClientInterface|null $client
	 * @param bool $bearerToken
	 *
	 * @return UserApiManager
	 * @throws HttpApiException
	 * @throws ReflectionException
	 * @throws Exception
	 */
	public static function getInstance( array $defaultHeaders = [], ?HttpClientInterface $client = null, bool $bearerToken = false ): UserApiManager {
		$key = $bearerToken ? 'with_token' : 'without_token';
		if ( ! isset(self::$instances[$key]) ) {
			$accessToken = null;
			if($bearerToken) {
                /** @var TokensManager $tokensManager */
                $tokensManager = TokensManager::instance();
                $accessToken = $tokensManager->getStoredAccessToken();
                if (TokensManager::validateToken($accessToken) === false) {
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
	 * UserApiManager constructor.
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
		$this->loadConfiguration();
	}

	/**
	 * Load the external integrations configuration.
	 *
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
     * Prepare security headers for the API request.
     *
     * @param string $activationCode
     * @return mixed
     * @throws HttpApiException
     * @throws ReflectionException
     * @throws Throwable
     */
    public function checkActivationCode(string $activationCode): mixed {
        $this->setUrl('activation/check', 'publicApi');

        // Set security headers
        $payload = CoreHelper::generateCommonSecurityPayload([
            'activationCode' => $activationCode,
        ]);
        $this->prepareSecurityHeaders($activationCode, $payload);

        $response = $this->post($payload);

        if (empty($response['content'])) {
            return false;
        }

        return $response['content'];
    }

    /**
     * Get the user account details from RankingCoach API.
     *
     * @param string|null $locale
     * @return mixed
     * @throws HttpApiException
     * @throws ReflectionException
     * @throws Throwable
     * @throws Exception
     */
    public function getCategoriesTranslated(string $locale = null): mixed
    {
        // Always fetch the latest data from the API without using cache
        $this->setUrl('categories');

        $payload = CoreHelper::generateCommonSecurityPayload([
            'locale' => WordpressHelpers::get_wp_locale($locale),
            'language' => WordpressHelpers::current_language_code_helper($locale),
        ]);
        $this->prepareSecurityHeaders($this->getBearerToken(), $payload);

        $response = $this->post($payload);

        $this->log_json([
            'operation_type' => 'api_config',
            'operation_status' => 'prepared',
            'context_entity' => 'categories',
            'context_type' => 'config_resolution',
            'metadata' => [
                'endpoint' => 'categories',
                'url_type' => 'baseUrl',
                'environment' => (RankingCoachPlugin::isProductionMode() ? 'liveEnv' : 'devEnv'),
                'api_base_domain' => $this->configuration['prefix'] ?? null,
                'base_url_template' => $this->configuration['baseUrl'] ?? null,
                'base_url_resolved' => (isset($this->configuration['baseUrl']) && isset($this->configuration['prefix'])) ? sprintf($this->configuration['baseUrl'], $this->configuration['prefix']) : null,
                'final_url' => $this->url ?? null,
                'debug_params' => (is_string($this->url) ? (str_contains($this->url, 'debug=1') && str_contains($this->url, 'noCache=1')) : null),
                'config_source' => RANKINGCOACH_PLUGIN_APP_DIR . 'config/app/externalIntegrations.php',
                'request_headers_keys' => array_keys($this->defaultHeaders ?? []),
                'payload' => $payload,
                'response_content' => $response['content']
            ],
        ], 'categories_translated', !RankingCoachPlugin::isProductionMode());

        return $response['content']?->translatedCategories ?? [];
    }

    /**
     * @return mixed
     * @throws HttpApiException
     */
    public function updateWebsiteUrl(): bool
    {
        $this->setUrl('updateWebsiteUrl');
        
        // Sanitize and validate the URL before sending
        $sanitizedUrl = $this->sanitizeWebsiteUrl(home_url());
        if (!$sanitizedUrl) {
            throw new Exception('Invalid website URL detected, cannot update API');
        }
        
        // Update the website URL in the database
        $payload = CoreHelper::generateCommonSecurityPayload([
            'siteDomain' => $sanitizedUrl,
        ]);
        $this->prepareSecurityHeaders($this->getBearerToken(), $payload);
        $response = $this->put($payload);
        if (empty($response['content'])) {
            $this->log('Failed to update website URL in the API', 'ERROR');
            return false;
        }
        return true;
    }

    /**
     * Sanitize and validate website URL before API transmission
     */
    private function sanitizeWebsiteUrl(string $url): ?string
    {
        // Parse URL components
        $parsed = wp_parse_url($url);
        if (!$parsed || empty($parsed['host'])) {
            return null;
        }

        // Validate scheme
        $scheme = $parsed['scheme'] ?? 'https';
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        // Sanitize host
        $host = strtolower($parsed['host']);

        // Sanitize port
        $port = '';
        if (!empty($parsed['port'])) {
            $portNum = (int)$parsed['port'];
            if ($portNum > 0 && $portNum <= 65535) {
                // Only include non-standard ports
                if (($scheme === 'http' && $portNum !== 80) || ($scheme === 'https' && $portNum !== 443)) {
                    $port = ':' . $portNum;
                }
            }
        }

        // Sanitize path
        $path = !empty($parsed['path']) ? rtrim($parsed['path'], '/') : '';

        return $scheme . '://' . $host . $port . $path;
    }

    /**
     * Fetches account details from the external API and updates only subscription-related data.
     * 
     * @throws Throwable
     * @throws HttpApiException
     */
    public function fetchAndUpdateAccountDetails(): mixed
    {
        $this->setUrl('checkAccount');

        // Set security headers
        $payload = CoreHelper::generateCommonSecurityPayload();
        $this->prepareSecurityHeaders($this->getBearerToken(), $payload);

        // If the account details were fetched successfully, update the data
        $response = $this->get([], $payload);

        // Return false if response is empty
        if (empty($response['content'])) {
            return false;
        }

        // Update only subscription-related data
        $result = $this->updateSubscriptionData($response['content']);

        if (is_wp_error($result)) {
            $this->log('Error while updating subscription data: ' . $result->get_error_message(), 'ERROR');
            return false;
        }

        return $response['content'];
    }

    /**
     * Updates only subscription-related data from the API response.
     *
     * @param mixed $jsonData
     * @return bool|WP_Error
     * @throws Exception
     */
    public function updateSubscriptionData(mixed $jsonData): bool|WP_Error
    {
        // Decode JSON data
        $data = json_decode(json_encode($jsonData), true);
        if (!$data) {
            return false;
        }

        try {
            $subscriptionExternal = $data['subscription'] ?? null;
            $subscriptionHistory = $data['subscriptionHistory'] ?? null;
            $maxKeywords = $data['maxAllowedKeywords'] ?? null;

//            if (!$subscriptionExternal) {
//                throw new Exception('Update subscription data failed. Missing required fields: subscription, subscriptionHistory or maxAllowedKeywords.');
//            }

            // Update only subscription-related options
            update_option(BaseConstants::OPTION_RANKINGCOACH_SUBSCRIPTION, $subscriptionExternal, true);
            update_option(BaseConstants::OPTION_RANKINGCOACH_SUBSCRIPTION_HISTORY, $subscriptionHistory, true);
            update_option(BaseConstants::OPTION_RANKINGCOACH_MAX_ALLOWED_KEYWORDS, $maxKeywords, true);
            if(!empty($data['couponCode'] ?? null)) {
                update_option(BaseConstants::OPTION_RANKINGCOACH_COUPON_CODE, $data['couponCode'], null);
            }

            return true;

        } catch (Exception $e) {
            $this->log('Error while updating subscription data: ' . $e->getMessage(), 'ERROR');
            return new WP_Error('subscription_update_failed', $e->getMessage());
        }
    }

    /**
     * Fetches account data from the external API and inserts it directly into the database.
     *
     * Configures the API request using the external integration configuration, sets security headers,
     * sends a POST request with necessary data, and inserts the returned account details into the database.
     * If the API response is empty, the method returns false.
     *
     * @return mixed The account data if fetched successfully, or false if the response is empty.
     * @throws Exception
     * @throws Throwable
     */
    public function fetchAndInsertAccountData(bool $onlyVerification = false): mixed
    {
        $accountType = get_option( BaseConstants::OPTION_IS_RESELLER_ACCOUNT );
        // Configure API request
        $this->setUrl('checkAccount');

        $params = [
            'activationCode' => get_option( BaseConstants::OPTION_ACTIVATION_CODE, null ),
            'verificationOnly' => $onlyVerification === true
        ];
        // Set security headers
        $payload = CoreHelper::generateCommonSecurityPayload($params);
        $this->prepareSecurityHeaders($this->getBearerToken(), $payload);

        // Make API request
        $response = $this->post($payload);


        //$this->log(json_encode($response), 'DEBUG_RESPONSE');

        // Return false if response is empty
        if (empty($response['content'])) {
            return false;
        }
        
        // Insert data directly into the database
        $content = $response['content'];
        $result = $this->insertRCAccountData($content);

        if (is_wp_error($result)) {
            $this->log('Error while inserting Rankingcoach data: ' . $result->get_error_message(), 'ERROR');
            return false;
        }

        return $response['content'];
    }

    /**
     * Get the plugin information.
     *
     * @return mixed
     * @throws HttpApiException
     * @throws ReflectionException
     * @throws Exception
     */
    public static function handleGmbCategories(): mixed
    {
        /** @var UserApiManager $instance This handles the API requests */
        $instance = UserApiManager::getInstance(bearerToken: true);
        $categories = $instance->getCategoriesTranslated();
        if($categories) {
            // Save the categories to the database

            // Check if the DatabaseSetupCategories table exists
            $tableExists = DatabaseManager::getInstance()->tableExists(DatabaseTablesManager::DATABASE_SETUP_CATEGORIES);
            if($tableExists) {
                DatabaseManager::getInstance()->tables()->saveCategoriesToDatabase($categories);
                // Update the last update timestamp and language
                update_option(BaseConstants::OPTION_CATEGORIES_LAST_UPDATE, time());
                update_option(BaseConstants::OPTION_CATEGORIES_LAST_LANGUAGE, WordpressHelpers::current_language_code_helper());
            }
        }
        return $categories;
    }


    /**
     * Insert the account details.
     *
     * @param mixed $jsonData
     * @return bool|WP_Error
     * @throws Throwable
     */
    public function insertRCAccountData(mixed $jsonData): bool|WP_Error
    {
        $dbManager = DatabaseManager::getInstance();

        // Decode JSON data
        $data = json_decode(json_encode($jsonData), true);
        if (!$data) {
            return false;
        }

        $dbManager->db()->beginTransaction();

        try {
            $accountId = $data['accountId'] ?? null;
            $projectId = $data['projectId'] ?? null;
            $locationId = $data['locationId'] ?? null;

            if(!$accountId || !$projectId || !$locationId) {
                throw new Exception('Save account data failed. Missing required fields: accountId, projectId, or locationId.');
            }

            // Update only account-related options
            update_option(BaseConstants::OPTION_RANKINGCOACH_ACCOUNT_ID, $accountId, true);
            update_option(BaseConstants::OPTION_RANKINGCOACH_PROJECT_ID, $projectId, true);
            update_option(BaseConstants::OPTION_RANKINGCOACH_LOCATION_ID, $locationId, true);
            if(!empty($data['couponCode'] ?? null)) {
                update_option(BaseConstants::OPTION_RANKINGCOACH_COUPON_CODE, $data['couponCode'], null);
            }

            $subscriptionExternal = $data['subscription'] ?? '';
            if (empty($subscriptionExternal)) {
                $subscriptionExternal = 'seo_wp_free';
            }
            $subscriptionHistory = $data['subscriptionHistory'] ?? null;
            $maxKeywords = $data['maxAllowedKeywords'] ?? null;

            if (!$subscriptionExternal || !$subscriptionHistory || $maxKeywords === null) {
                throw new Exception('Update subscription data failed. Missing required fields: subscription, subscriptionHistory or maxAllowedKeywords.');
            }

            // Update only subscription-related options
            update_option(BaseConstants::OPTION_RANKINGCOACH_SUBSCRIPTION, $subscriptionExternal, true);
            update_option(BaseConstants::OPTION_RANKINGCOACH_SUBSCRIPTION_HISTORY, $subscriptionHistory, true);
            update_option(BaseConstants::OPTION_RANKINGCOACH_MAX_ALLOWED_KEYWORDS, $maxKeywords, true);

            // Categories
            $categories = $data['categories']['elements'] ?? [];

            // Keywords
            $keywords = $data['keywords']['elements'] ?? [];
            $this->saveKeywords($keywords);
            
            // Everything is saved on the setup requirements table ( categories, keywords, location data )
            $this->updateSetupRequirements($data, $categories, $keywords);
            $dbManager->db()->commit();

            return true;

        } catch (Exception $e) {
            $dbManager->db()->rollback();
            $this->log('Error while inserting Rankingcoach data: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }


    /**
     * Insert the account details.
     *
     * @param array $categories
     * @param bool $fileStorage
     * @return bool|WP_Error
     */
    public function saveRCTranslatedCategories(array $categories = [], bool $fileStorage = false): bool|WP_Error
    {
        $dbManager = DatabaseManager::getInstance();

        if($fileStorage) {

            try {

                // Update the last update timestamp and language
                update_option(BaseConstants::OPTION_CATEGORIES_LAST_UPDATE, time());
                update_option(BaseConstants::OPTION_CATEGORIES_LAST_LANGUAGE, WordpressHelpers::current_language_code_helper());

            } catch (Exception $e) {
                // do nothing
            }
        }

        $dbManager->db()->beginTransaction();
        try {
            $dbManager->tables()->saveCategoriesToDatabase($categories);

            $dbManager->db()->commit();
            // Update the last update timestamp and language
            update_option(BaseConstants::OPTION_CATEGORIES_LAST_UPDATE, time());
            update_option(BaseConstants::OPTION_CATEGORIES_LAST_LANGUAGE, WordpressHelpers::current_language_code_helper());

            return true;
        } catch (Exception $e) {
            $dbManager->db()->rollback();
            $this->log('Error while saving in database the translated categories: ' . $e->getMessage(), 'ERROR');
        }

        return false;
    }

    /**
     * @param array $data
     * @param array|null $categories
     * @param array|null $keywords
     * @return void
     * @throws Throwable
     */
    public function updateSetupRequirements(array $data, array $categories = null, array $keywords = null ): void
    {
        if(!empty($data['descriptionLong'])) {
            DatabaseManager::getInstance()->tables()->updateSetupRequirements('businessDescription', $data['descriptionLong']);
        }
        if(!empty($data['companyName'])) {
            DatabaseManager::getInstance()->tables()->updateSetupRequirements('businessName', $data['companyName']);
        }
        DatabaseManager::getInstance()->tables()->updateSetupRequirements('businessServiceArea', 'y');
        // If the formattedAddress include just the country and that is Germany, we don't need to update the business address
        if(!empty($data['formattedAddress']) &&
            ($data['formattedAddress'] !== 'Germany' && $data['formattedAddress'] !== 'Deutschland')
        ) {
            DatabaseManager::getInstance()->tables()->updateSetupRequirements('businessAddress', $data['formattedAddress']);
        }
        // If the geoaddress is not empty, we need to update the business geolocation
        if(!empty($data['geoaddress'])) {
            DatabaseManager::getInstance()->tables()->updateSetupRequirements('businessGeoAddress', json_encode($data['geoaddress']));
        }
        // Categories (if any
        if(!empty($categories)) {
            DatabaseManager::getInstance()->tables()->updateSetupRequirements('businessCategories', json_encode(array_map(static fn($cat) => (int) $cat['categoryId'], $categories), JSON_THROW_ON_ERROR));
        }

        if(!empty($keywords)) {
            DatabaseManager::getInstance()->tables()->updateSetupRequirements('businessKeywords', json_encode(array_map(static fn($keyword) => (string)$keyword['keyword']['name'], $keywords), JSON_THROW_ON_ERROR));
        }

    }

    /**
     * @param array $keywords
     * @return void
     * @throws Exception
     */
    public function saveKeywords(array $keywords): void
    {
        $dbManager = DatabaseManager::getInstance();

        if(empty($keywords)) {
            return;
        }

        foreach ($keywords as $keyword) {

            $keywordId = (int)$keyword['id'];
            $keywordData = [
                'externalId' => $keyword['keyword']['id'] ?? null,
                'name' => $keyword['keyword']['name'] ?? '',
                'alias' => $keyword['keyword']['alias'] ?? '',
                'hash' => $keyword['keyword']['hash'] ?? ''
            ];

            $exists = $dbManager->db()->table(DatabaseTablesManager::DATABASE_APP_KEYWORDS)->select('id')->where('externalId', $keywordId)->value('id');

            if($exists) {
                $dbManager->db()->table(DatabaseTablesManager::DATABASE_APP_KEYWORDS)->delete()->where('externalId', $exists)->get();
            }

            try {
                $dbManager->db()->table(DatabaseTablesManager::DATABASE_APP_KEYWORDS)->insert()->set($keywordData)->get();
            } catch (Exception $e) {
                throw new Exception(sprintf(
                    /* translators: %1$s is the error message */
                    esc_html__('Keyword insert failed. Error: %1$s', 'beyond-seo'),
                    esc_html($e->getMessage())
                ));
            }
        }
    }

    /**
     * Fetch upselling URL from the external API for the selected plan.
     *
     * @param string $planSelected The selected plan for upselling
     * @return array|false Returns array with upsellUrl and status code, or false on failure
     * @throws Exception
     */
    public function fetchUpsellingUrl(string $planSelected): array|false
    {
        // Configure API request
        $this->setUrl('intention/upselling/');

        // Set security headers
        $payload = CoreHelper::generateCommonSecurityPayload([
            'planSelected' => $planSelected
        ]);


        //wp_die(json_encode(['token' => $this->getBearerToken(), 'payload' => $payload]));
        $this->prepareSecurityHeaders($this->getBearerToken(), $payload);

        // Make API request
        $response = $this->post($payload);

        // Return false if response is empty
        if (empty($response['content'])) {
            return false;
        }

        // Check if we have a successful response (200) and upsellUrl
        if (!empty($response['content']->upsellUrl)) {
            // Get existing upsell URLs object or create new one
            $existingUpsellUrls = get_option(BaseConstants::OPTION_UPSELL_URLS, []);
            if (!is_array($existingUpsellUrls)) {
                $existingUpsellUrls = [];
            }
            
            // Save the upsell URL for the specific plan
            $existingUpsellUrls[$planSelected] = $response['content'];
            update_option(BaseConstants::OPTION_UPSELL_URLS, $existingUpsellUrls);
            
            return [
                'upsellUrl' => $response['content']->upsellUrl
            ];
        }

        return false;
    }

    /**
     * Handle upselling process for the selected plan.
     *
     * @param string $planSelected The selected plan for upselling
     * @return array|false Returns upselling data or false on failure
     * @throws HttpApiException
     * @throws ReflectionException
     * @throws Exception
     */
    public static function handleUpselling(string $planSelected): array|false
    {
        /** @var UserApiManager $instance This handles the API requests */
        $instance = UserApiManager::getInstance(bearerToken: true);
        $upsellingUrl = $instance->fetchUpsellingUrl($planSelected);
        /**
         * We should check next time customer if he upgraded or not
         */
        if ($upsellingUrl) {
            update_option(BaseConstants::OPTION_UPSELL_FORCE_CHECK, true, true);
        }
        return $upsellingUrl;
    }

    /**
     * Get stored upsell URL for a specific plan.
     *
     * @param string $planSelected The plan to get the upsell URL for
     * @return string|null Returns the upsell URL or null if not found
     */
    public static function getStoredUpsellUrl(string $planSelected): ?string
    {
        $upsellUrls = get_option(BaseConstants::OPTION_UPSELL_URLS, []);
        
        if (is_array($upsellUrls) && isset($upsellUrls[$planSelected])) {
            return $upsellUrls[$planSelected];
        }
        
        return null;
    }

    /**
     * Get all stored upsell URLs.
     *
     * @return array Returns array of all stored upsell URLs indexed by plan
     */
    public static function getAllStoredUpsellUrls(): array
    {
        $upsellUrls = get_option(BaseConstants::OPTION_UPSELL_URLS, []);
        
        return is_array($upsellUrls) ? $upsellUrls : [];
    }

    /**
     * Clear stored upsell URL for a specific plan.
     *
     * @param string $planSelected The plan to clear the upsell URL for
     * @return bool Returns true if cleared successfully
     */
    public static function clearStoredUpsellUrl(string $planSelected): bool
    {
        $upsellUrls = get_option(BaseConstants::OPTION_UPSELL_URLS, []);
        
        if (is_array($upsellUrls) && isset($upsellUrls[$planSelected])) {
            unset($upsellUrls[$planSelected]);
            return update_option(BaseConstants::OPTION_UPSELL_URLS, $upsellUrls);
        }
        
        return false;
    }

    /**
     * Fetch upsell magic link from the external API.
     *
     * @param string $paymentPeriod
     * @param string $countryCode
     * @param string $sessionId
     * @return array|false
     * @throws HttpApiException
     */
    public function fetchUpsellMagicLink(string $paymentPeriod, string $countryCode): array|false
    {
        $this->setUrl('intention/upselling-dc');

        $payload = CoreHelper::generateCommonSecurityPayload([
            'paymentPeriod' => $paymentPeriod,
            'countryShortCode'   => strtolower($countryCode),
        ]);

        $this->prepareSecurityHeaders($this->getBearerToken(), $payload);
        $response = $this->post($payload);

        if (empty($response['content'])) {
            return false;
        }

        $content = $response['content'];

        // Recursively decode HTML entities for URLs to handle double encoding
        $voucherURL = $content->voucherURL ?? '';
        $voucherURL = html_entity_decode($voucherURL, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $registrationURL = $content->registrationURL ?? '';
        $registrationURL = html_entity_decode($registrationURL, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return [
            'voucherURL'      => $voucherURL,
            'registrationURL' => $registrationURL,
            's'               => $content->s ?? '',
            'meta'            => $content->meta ?? null,
        ];
    }

    /**
     * Get the client dashboard URL based on the current environment.
     *
     * @return string
     */
    public function getClientDashboardUrl(): string
    {
        $this->loadConfiguration();
        return sprintf('https://%s.rankingcoach.com', $this->configuration['prefix']);
    }
}
