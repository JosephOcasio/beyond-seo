<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core\Rest;

if ( !defined('ABSPATH') ) {
    exit;
}

use App\Domain\Integrations\WordPress\Plugin\Entities\WPSettings;
use App\Presentation\Api\Client\Integrations\WordPress\Dtos\ModulesResponseDto;
use Exception;
use RankingCoach\Inc\Core\Api\User\UserApiManager;
use RankingCoach\Inc\Core\CustomVersionLoader;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\CapabilityManager;
use RankingCoach\Inc\Core\CurrentUserManager;
use RankingCoach\Inc\Core\FileManager;
use RankingCoach\Inc\Core\HealthManager;
use RankingCoach\Inc\Core\Helpers\Attributes\RcDocumentation;
use RankingCoach\Inc\Core\Helpers\CoreHelper;
use RankingCoach\Inc\Core\Helpers\RestHelpers;
use RankingCoach\Inc\Core\Helpers\Traits\RcApiTrait;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Core\OpenApiGenerator;
use RankingCoach\Inc\Core\Settings\Dtos\SettingsRequestDto;
use RankingCoach\Inc\Core\Settings\Dtos\SettingsResponseDto;
use RankingCoach\Inc\Core\Settings\Dtos\SingleSettingRequestDto;
use RankingCoach\Inc\Core\Settings\Dtos\SingleSettingResponseDto;
use RankingCoach\Inc\Core\Settings\SettingsManager;
use RankingCoach\Inc\Core\Breadcrumbs\BreadcrumbsMultipleResponseHandler;
use RankingCoach\Inc\Core\Breadcrumbs\Dtos\BreadcrumbsRequestDto;
use RankingCoach\Inc\Core\Breadcrumbs\Dtos\BreadcrumbsResponseDto;
use RankingCoach\Inc\Modules\ModuleManager;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use Throwable;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class RestManager
 */
class RestManager extends WP_REST_Controller {

	use RcApiTrait;
	use RcLoggerTrait;

	/**
	 * Registered data.
	 *
	 * @var array|false
	 */
	private array|false $registered;

	/**
	 * Endpoint authentication configuration.
	 * 
	 * @var array
	 */
	private static array $allowedEndpointApiConfig = [
		// Public endpoints - no auth required
        'public' => [
            'webhook',
            'documentation'
        ],
		// Service endpoints - require app password auth
		'service' => [
			'integration',
			'sync',
		],
		// Frontend endpoints - require nonce auth
		'frontend' => [
			'user-data',
			'settings',
		],
		// Admin endpoints - require admin capabilities
		'admin' => [
			'admin',
			'config',
			'metatags',
			'contentAnalysis',
			'onboarding',
			'pluginInformation',
			'optimiser',
			'social'
		]
	];

    /** @var string $tokenOptionPrefix
     * Prefix for download logs token options.
     * This is used to store and retrieve download tokens in the WordPress options table.
     */
    private string $tokenOptionPrefix = 'rankingcoach_download_logs_token_';

    /**
     * @var int $tokenTTL
     * Time-to-live for download tokens in seconds.
     * This defines how long a download token is valid before it expires.
     */
    private int $tokenTTL = 300;

    /**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = RANKINGCOACH_REST_API_BASE;
		$this->registered = false;

		// Register the API routes
		add_action('rest_api_init', [$this, 'registerApiRoutes'], 5); // Lower priority to run earlier
        add_filter('rest_url', function (string $url): string {
            if ((bool) get_transient(RestHelpers::TRANSIENT_KEY)) {
                // Replace ".../wp-json/" with "index.php?rest_route=/"
                return trailingslashit(home_url('/index.php')) . '?rest_route=/';
            }
            return $url;
        }, 10, 1);
//        add_filter('rest_index', function($response) {
//            if (!is_user_logged_in()) {
//                $data = $response->get_data();
//                unset($data['namespaces'], $data['routes']);
//                $response->set_data($data);
//            }
//            return $response;
//        });
	}

	/**
	 * Get the endpoint authentication configuration.
	 * 
	 * @return array The endpoint authentication configuration.
	 */
	public static function getAllowedEndpointApiConfig(): array {
		return apply_filters('rankingcoach_endpoint_auth_config', self::$allowedEndpointApiConfig);
	}

	/**
	 * Register the API routes for the external API integration.
	 * This runs early in the rest_api_init hook to ensure these routes are registered
	 * before WordPress loads its standard routes.
	 */
	public function registerApiRoutes(): void {
		register_rest_route(RANKINGCOACH_REST_APP_BASE, '/(?P<method>.+)', [
			'methods' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'],
			'permission_callback' => function(WP_REST_Request $request) {
				// Allow OPTIONS requests for CORS preflight
				if ($request->get_method() === 'OPTIONS') {
					return true;
				}

				$method = $request->get_param('method');

				// Get endpoint authentication configuration from RestManager
				$endpoint_auth_config = self::getAllowedEndpointApiConfig();

				// Check if this is a public endpoint
				foreach ($endpoint_auth_config['public'] as $endpoint) {
					if (str_starts_with($method, $endpoint)) {
						return true;
					}
				}

				// Check for Basic Authentication first (universal approach)
				// This allows any endpoint to be accessed with valid application password credentials
				$auth_header = $request->get_header('Authorization');
				if (!empty($auth_header) && str_starts_with($auth_header, 'Basic ')) {
					$app_auth_result = $this->verifyAppPasswordAuth($request);
					if ($app_auth_result) {
						// If Basic Auth succeeds, allow access regardless of endpoint type
						return true;
					}
					// If Basic Auth fails but was attempted, don't fall through to other auth methods
					// This prevents auth method confusion/downgrade attacks
					if ($this->isServiceEndpoint($method, $endpoint_auth_config['service'])) {
						$this->log('Service endpoint access denied: Invalid Basic Auth credentials', 'WARNING');
						return false;
					}
					// For non-service endpoints, continue to other auth methods if Basic Auth fails
				}

				// For service endpoints, Basic Auth is required and we've already checked it
				if ($this->isServiceEndpoint($method, $endpoint_auth_config['service'])) {
					$this->log('Service endpoint access denied: Basic Auth required', 'WARNING');
					return false;
				}

                // 2. Frontend-to-Backend Authentication using WordPress Nonces
                if ($this->isFrontendEndpoint($method, $endpoint_auth_config['frontend'])) {
					return $this->verifyNonceAuth($request);
				}

                // 3. Admin-only endpoints require admin capabilities
                if ($this->isAdminEndpoint($method, $endpoint_auth_config['admin'])) {

                    // If onboarding is not finalized, we block allow access to admin optimiser endpoints
                    if (str_starts_with($method, 'optimiser') && CurrentUserManager::getInstance()->isValidInternalOnboardingData() === false) {
                        $this->log('Optimiser endpoint access denied: Onboarding not finalized', 'WARNING');
                        return new WP_Error(
                            'rest_forbidden',
                            __('You must complete onboarding before accessing this endpoint.', 'beyond-seo'),
                            ['status' => rest_authorization_required_code()]
                        );
                    }

                    // Check if the request is internal (from wp_remote_post within WordPress)
                    $is_internal_request = defined('DOING_AJAX') && DOING_AJAX;

                    // Check for referer from admin URL
                    $http_referer = wp_get_referer();
                    if ($http_referer && str_contains($http_referer, admin_url())) {
                        $is_internal_request = true;
                    }

                    // Check for internal API calls with specific user agent pattern
                    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
                    if ($user_agent && str_contains($user_agent, 'WordPress')) {
                        $is_internal_request = true;
                    }

                    // For internal requests from WordPress admin, we can trust the user is already authenticated
                    if ($is_internal_request && is_user_logged_in() && current_user_can('manage_options')) {
                        return true;
                    }

                    $verified = $this->verifyNonceAuth($request);
                    if ($verified) {
                        // If nonce verification passed, we can assume the user is authenticated
                        return true;
                    }

                    // No valid authentication method found
                    $this->log('Admin endpoint access denied: No valid authentication provided', 'WARNING');
                    return false;
				}

				// Default: require at least edit_posts capability
				return current_user_can('edit_posts');
			},
			'callback' => function() {
				defined('RANKINGCOACH_API_CONTEXT') || define('RANKINGCOACH_API_CONTEXT', true);
				header('Content-Type: application/json');

				// Intentionally setting SCRIPT_FILENAME to point to the Symfony entry point
				// This is necessary for the API integration to work correctly
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated
				$_SERVER['SCRIPT_FILENAME'] = RANKINGCOACH_PLUGIN_APP_DIR . 'public/index.php';

				require_once RANKINGCOACH_PLUGIN_APP_DIR . 'vendor/autoload_runtime.php';
			}
		]);
	}

	/**
	 * Check if the endpoint is a service endpoint
	 * 
	 * @param string $method The endpoint method
	 * @param array $service_endpoints List of service endpoints
	 * @return bool
	 */
	private function isServiceEndpoint(string $method, array $service_endpoints): bool {
		foreach ($service_endpoints as $endpoint) {
			if (str_starts_with($method, $endpoint)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Check if the endpoint is a frontend endpoint
	 * 
	 * @param string $method The endpoint method
	 * @param array $frontend_endpoints List of frontend endpoints
	 * @return bool
	 */
	private function isFrontendEndpoint(string $method, array $frontend_endpoints): bool {
		foreach ($frontend_endpoints as $endpoint) {
			if (str_starts_with($method, $endpoint)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Check if the endpoint is an admin endpoint
	 * 
	 * @param string $method The endpoint method
	 * @param array $admin_endpoints List of admin endpoints
	 * @return bool
	 */
	private function isAdminEndpoint(string $method, array $admin_endpoints): bool {
		foreach ($admin_endpoints as $endpoint) {
			if (str_starts_with($method, $endpoint)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Verify authentication using WordPress Application Passwords
	 * Used for service-to-service communication and can be used for any endpoint
	 * that accepts Basic Authentication
	 * @param WP_REST_Request $request The request object
	 * @return bool
	 */
	private function verifyAppPasswordAuth(WP_REST_Request $request): bool {
		// Check for Authorization header
		$auth_header = $request->get_header('Authorization');
		if (empty($auth_header) || !str_starts_with($auth_header, 'Basic ')) {
			$this->log('App password authentication failed: Missing or invalid Authorization header', 'WARNING');
			return false;
		}

		// Extract credentials
		$auth = substr($auth_header, 6);
		$auth = base64_decode($auth);
		if (!$auth) {
			$this->log('App password authentication failed: Invalid base64 encoding', 'WARNING');
			return false;
		}

		// Split the decoded credentials into username and password
		if (!str_contains($auth, ':')) {
			$this->log('App password authentication failed: Invalid credential format', 'WARNING');
			return false;
		}

		[$username, $password] = explode(':', $auth, 2);

		// Validate username and password are not empty
		if (empty($username) || empty($password)) {
			$this->log('App password authentication failed: Empty username or password', 'WARNING');
			return false;
		}

		// Prevent timing attacks by using a constant-time comparison for username validation
		// First check if the user exists
		$user_data = get_user_by('login', $username);
		if (!$user_data) {
			// Use the same WordPress core function to maintain consistent timing
			// even when the username is invalid
			wp_authenticate_application_password(null, $username, $password);
			$this->log('App password authentication failed: Invalid username', 'WARNING');
			return false;
		}

		// Use WordPress core function to verify application password
		$user = wp_authenticate_application_password(null, $username, $password);

		// Check if authentication was successful
		if (is_wp_error($user)) {
			$this->log('App password authentication failed: ' . $user->get_error_message(), 'WARNING');
			return false;
		}

		// Verify the user has the required capability for API access
		// For service endpoints, we require at least edit_posts capability
		if (!user_can($user, 'edit_posts')) {
			$this->log('App password authentication failed: User lacks required capabilities', 'WARNING');
			return false;
		}

		// Check if the user account is active
		if (!$user->has_cap('exist')) {
			$this->log('App password authentication failed: User account is not active', 'WARNING');
			return false;
		}

		// Authentication successful, set the current user
		wp_set_current_user($user->ID);

		// Log successful authentication for audit purposes
		$this->log('App password authentication successful for user: ' . $username, 'INFO');

		return true;
	}

    /**
     * Verify authentication using WordPress Nonces
     * Used for frontend-to-backend communication
     *
     * @param WP_REST_Request $request The request object
     * @return bool
     */
	private function verifyNonceAuth(WP_REST_Request $request): bool {
		// Get the nonce from the request headers first (preferred method)
        $nonce = $request->get_header('X-WP-Nonce');
        if (empty($nonce) && isset($_SERVER['HTTP_X_WP_NONCE'])) {
            $nonce = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_WP_NONCE']));
        }

        // Where here nonce is null if the request comes through wp_remote_post?
		if (empty($nonce)) {
            // Try to get nonce from request parameters
            $body_params = $request->get_body_params();
            $nonce = $request->get_param('_ajax_nonce')
                ?? $request->get_param('_wpnonce')
                ?? (isset($body_params['_ajax_nonce']) ? $body_params['_ajax_nonce'] : null)
                ?? (isset($body_params['_wpnonce']) ? $body_params['_wpnonce'] : null)
                ?? (isset($_POST['_ajax_nonce']) ? sanitize_text_field(wp_unslash($_POST['_ajax_nonce'])) : null)
                ?? (isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : null)
                ?? null;
        }

        if (empty($nonce)) {
			return false;
		}

		// Verify the nonce
        $verified = wp_verify_nonce($nonce, 'wp_rest');

		if (!$verified) {
			$this->log('Nonce verification failed', 'WARNING');
			return false;
		}

		// Check if user is logged in and has required capabilities
		return is_user_logged_in() && current_user_can('read');
	}

	/**
	 * Registers the routes for the objects of the controller.
     */
	public function registerLegacyRoutes(): void {
		$routes = [
			'/feature_modules' => [
				'callback' => 'rankingcoachModules',
				'permission_callback' => fn() => current_user_can(CapabilityManager::CAPABILITY_READ_MODULES_LIST),
			],
			'/account_details' => [
				'callback' => 'rankingcoachAccountDetails',
				'permission_callback' => fn() => current_user_can(CapabilityManager::CAPABILITY_READ_ACCOUNT_DETAILS),
			],
			'/location_keywords' => [
				'callback' => 'rankingcoachLocationKeywords',
				'permission_callback' => fn() => current_user_can(CapabilityManager::CAPABILITY_READ_LOCATION_KEYWORDS),
			],
			'/rc_variables/(?P<id>\d+)/data' => [
				'callback' => 'rankingcoachVariables',
				'permission_callback' => fn() => current_user_can(CapabilityManager::CAPABILITY_READ_ACCOUNT_DETAILS),
				'args' => [
					'id' => [
						'description' => 'The ID of the post',
						'in' => 'path',
						'type' => 'integer',
						'required' => true,
						'validate_callback' => fn($param) => is_numeric($param) && (int)$param > 0,
						'sanitize_callback' => 'absint',
					]
				]
			],
            '/generate_sdk' => [
                'callback' => 'generateOpenApiSpecifications'
            ],
            '/settings' => [
                'callback' => 'handleGeneralSettings',
                'permission_callback' => fn() => current_user_can('manage_options'),
                'methods' => ['GET', 'POST'],
                'args' => []
            ],
            '/settings/(?P<key>[a-zA-Z0-9_-]+)' => [
                'callback' => 'handleSingleSetting',
                'permission_callback' => fn() => current_user_can('manage_options'),
                'methods' => ['GET', 'POST', 'DELETE'],
                'args' => [
                    'key' => [
                        'description' => 'The setting key to retrieve or update',
                        'type' => 'string',
                        'required' => true,
                        'in' => 'path',
                        'validate_callback' => fn($param) => !empty($param) && is_string($param),
                        'sanitize_callback' => 'sanitize_key',
                    ]
                ]
            ],
            '/breadcrumbs' => [
                'callback' => 'handleBreadcrumbs',
                'permission_callback' => fn() => current_user_can('read'),
                'methods' => ['POST'],
                'args' => [
                    'types' => [
                        'description' => 'Array of breadcrumb types to generate',
                        'type' => 'array',
                        'required' => true,
                        'validate_callback' => fn($param) => is_array($param) && !empty($param),
                        'sanitize_callback' => fn($param) => array_map('sanitize_text_field', $param),
                    ]
                ]
            ],
            '/plugin_update_check' => [
                'callback' => 'handlePluginUpdateCheck',
                'permission_callback' => fn() => current_user_can('manage_options'),
                'methods' => ['POST'],
                'args' => []
            ],
        ];

		foreach ($routes as $endpoint => $options) {
			RestHelpers::registerRoute(
				$this->getLegacyApiBase(),
				$endpoint,
				[
					'methods' => $options['methods'] ?? WP_REST_Server::READABLE,
					'callback' => [$this, $options['callback']],
					'permission_callback' => $options['permission_callback'] ?? '__return_true',
					'args' => $options['args'] ?? [],
				]
			);
		}
	}

	/**
	 * Get the list of available modules.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_REST_Response
	 * @throws ReflectionException
	 * @throws Exception
	 */
	#[RcDocumentation(
        responseDto: ModulesResponseDto::class,
        description: 'Returns the list of available modules with their names.',
        summary: 'Get the list of available modules.'
    )]
	public function rankingcoachModules( WP_REST_Request $request ): WP_REST_Response {

		// accept only GET requests
		if ( 'GET' !== $request->get_method() ) {
			return $this->generateErrorResponse(null, 'Method not allowed', 405);
		}

		/** @var ModuleManager $moduleManager */
		$moduleManager = ModuleManager::instance();
		try {
			$modulesData = apply_filters( 'rankingcoach_modules/data', $moduleManager->get_modules_names() );

			$data = [
				'modules' => $modulesData,
			];

			return $this->generateApiResponse($data);
		} catch ( Exception $e ) {
			// Log the error for debugging
			$this->log( 'RankingCoach Modules Error: ' . $e->getMessage(), 'ERROR');
			return $this->generateErrorResponse($e, $e->getMessage(), 500);
		}
	}

    /**
     * Get the account details.
     *
     * @param WP_REST_Request $request Full data about the request.
     *
     * @return WP_REST_Response
     * @throws Throwable
     */
	#[RcDocumentation(
		description: 'Returns the rankingCoach account details including user data, subscription data, and other account details.',
		summary: 'Get the current logged account details.'
	)]
	public function rankingcoachAccountDetails( WP_REST_Request $request ): WP_REST_Response {

		if ( 'GET' !== $request->get_method() ) {
			return $this->generateErrorResponse(null, 'Method not allowed', 405);
		}

		try {
			$accountDetails = UserApiManager::getInstance(bearerToken: true)->fetchAndInsertAccountData();
			return $this->generateSuccessResponse($accountDetails);
		} catch ( Exception $e ) {
			// Log the error for debugging
			$this->log( 'RankingCoach Account Details Error: ' . $e->getMessage(), 'ERROR');
			return $this->generateErrorResponse($e, $e->getMessage(), 500);
		}
	}

    /**
     * Get the location keywords.
     *
     * @param WP_REST_Request $request Full data about the request.
     *
     * @return WP_REST_Response
     * @throws Throwable
     */
	#[RcDocumentation(
		description: 'Returns from rankingCoach the location keywords for the current logged account.',
		summary: 'Get the location keywords.'
	)]
	public function rankingcoachLocationKeywords( WP_REST_Request $request ): WP_REST_Response {

		if ( 'GET' !== $request->get_method() ) {
			return $this->generateErrorResponse(null, 'Method not allowed', 405);
		}

		try {
			$keywordsDetails = UserApiManager::getInstance(bearerToken: true)->fetchAndInsertAccountData();
			return $this->generateSuccessResponse($keywordsDetails);
		} catch ( Exception $e ) {
			// Log the error for debugging
			$this->log( 'Location keywords error: ' . $e->getMessage(), 'ERROR' );
			return $this->generateErrorResponse($e, $e->getMessage(), 500);
		}
	}

	/**
	 * Get the location keywords.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_REST_Response
	 */
	#[RcDocumentation(
		description: 'Returns the available variables for a post.',
		summary: 'Get the available variables for a post.'
	)]
	public function rankingcoachVariables( WP_REST_Request $request ): WP_REST_Response {

		if ( 'GET' !== $request->get_method() ) {
			return $this->generateErrorResponse(null, 'Method not allowed', 405);
		}
		/** @noinspection PhpUnusedLocalVariableInspection */
		$debug = (bool) ( (int) $request->get_param( 'debug' ) );

		$postId = (int) $request->get_param('id');
		$content = get_post($postId);
		if(!$content) {
			return $this->generateErrorResponse(null, __('Invalid post ID', 'beyond-seo'));
		}

		try {
			$variables = WordpressHelpers::get_available_WPVariables([ 'post' => $content]);
			return $this->generateSuccessResponse($variables);
		} catch ( Exception $e ) {
			// Log the error for debugging
			$this->log( 'RankingCoach Variables Error: ' . $e->getMessage(), 'ERROR');
			return $this->generateErrorResponse($e, $e->getMessage(), 500);
		}
	}

	/**
	 * Generate OpenAPI spec for the plugin.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_REST_Response
	 */
	#[RcDocumentation(
		description: 'Generates the OpenAPI spec for registered routes. This is used for generating stores on frontend.',
		summary: 'Generate OpenAPI specification.'
	)]
    public function generateOpenApiSpecifications(WP_REST_Request $request ): WP_REST_Response {

		if ( 'GET' !== $request->get_method() ) {
			return $this->generateErrorResponse(null, 'Method not allowed', 405);
		}

		$generator = new OpenApiGenerator();
		header('Content-Type: application/json');
        echo json_encode($generator->getOpenApi(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Retrieve the download logs URL.
     *
     * @param WP_REST_Request $request Full data about the request.
     *
     * @return WP_REST_Response
     * @throws Throwable
     */
    public function createDownloadLogs(WP_REST_Request $request): WP_REST_Response {
        if ('POST' !== $request->get_method()) {
            return $this->generateErrorResponse(null, 'Method not allowed', 405);
        }

        // Check if user has enabled log sharing
        $settingsManager = SettingsManager::instance();
        $enableLogSharing = $settingsManager->get_option('enable_user_action_and_event_logs_sharing', false);

        // temporary disable the check to allow log downloads for debugging
        if (!$enableLogSharing) {
            return $this->generateErrorResponse(
                null,
                __('Log sharing is disabled. Please enable log sharing in plugin settings to allow log downloads.', 'beyond-seo'),
                403
            );
        }

        // Read and require request correlation ID header
        $requestId = $request->get_header('X-RC-Request-ID');
        if (empty($requestId) && isset($_SERVER['HTTP_X_RC_REQUEST_ID'])) {
            $requestId = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_RC_REQUEST_ID']));
        }
        if(empty($requestId)) {
            $requestId = CoreHelper::generateSecureToken(); // generate a random request id
        }

        // Point 1: Create a one-time download token with 5-minute TTL
        $token = CoreHelper::generateSecureToken();
        $expires_at = time() + $this->tokenTTL;
        $tokenData = [
            'expires_at' => $expires_at,
            'used' => false,
            'created_at' => time(),
            'request_id' => (string) $requestId,
        ];
        update_option($this->tokenOptionPrefix . $token, $tokenData);

        // Build a REST URL that respects fallback if needed
        $download_url = RestHelpers::buildUrl(RANKINGCOACH_REST_API_BASE . '/download_logs') . (str_contains(RestHelpers::getRestRoot(), 'rest_route=') ? '&' : '?') . 'rc_token=' . $token;

        // Point 2: Return required JSON format
        $responseData = [
            'download_url' => $download_url,
            'expires_at' => gmdate('c', $expires_at), // ISO 8601 format
            'filename' => 'rankingcoach_logs_' . gmdate('Ymd_His') . '.zip',
            'files' => FileManager::getInstance()->getLogsFilesList(),
            'meta_data' => FileManager::getInstance()->generateInstanceMetadata(),
            'request_id' => (string) $requestId,
            'message' => 'Download link created successfully. Expires in 5 minutes.'
        ];

        if (!headers_sent()) {
            header('X-RC-Request-ID: ' . (string) $requestId);
        }

        return $this->generateSuccessResponse(['data' => $responseData]);
    }

    /**
     * Download all plugin logs as a ZIP archive.
     *
     * @param WP_REST_Request $request Full data about the request.
     */
    public function downloadLogsZip(WP_REST_Request $request): ?WP_Error
    {
        if ('GET' !== $request->get_method()) {
            $this->generateErrorResponse(null, 'Method not allowed', 405);
            return null;
        }

        // Check if user has enabled log sharing
        $settingsManager = SettingsManager::instance();
        $enableLogSharing = $settingsManager->get_option('enable_user_action_and_event_logs_sharing', false);

        if (!$enableLogSharing) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => [
                    'code' => 'logs_sharing_disabled',
                    'message' => __('Log sharing is disabled. The client has not agreed to share logs for debugging purposes. Please enable log sharing in plugin settings to allow log downloads.', 'beyond-seo')
                ]
            ], JSON_PRETTY_PRINT);
            http_response_code(403);
            exit;
        }

        $token = $request->get_param('rc_token');
        if (empty($token)) {
            return new WP_Error('missing_token', esc_html__('Download token is required', 'beyond-seo'), ['status' => 400]);
        }

        // Validate token
        $tokenData = get_option($this->tokenOptionPrefix . $token);
        if (!$tokenData) {
            return new WP_Error('invalid_token', esc_html__('Invalid or expired download token', 'beyond-seo'), ['status' => 410]);
        }

        // Check if token is expired
        if (time() > $tokenData['expires_at']) {
            delete_option($this->tokenOptionPrefix . $token);
            return new WP_Error('expired_token', esc_html__('Download token has expired', 'beyond-seo'), ['status' => 410]);
        }

        // Check if token was already used (one-time use)
        if (!empty($tokenData['used']) && $tokenData['used']) {
            delete_option($this->tokenOptionPrefix . $token);
            return new WP_Error('used_token', esc_html__('Download token has already been used', 'beyond-seo'), ['status' => 410]);
        }

        // Validate request correlation ID
        $requestId = $request->get_header('X-RC-Request-ID');
        if (empty($requestId) && isset($_SERVER['HTTP_X_RC_REQUEST_ID'])) {
            $requestId = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_RC_REQUEST_ID']));
        }
        if (empty($requestId)) {
            return new WP_Error('missing_request_id', esc_html__('Missing X-RC-Request-ID header', 'beyond-seo'), ['status' => 400]);
        }
        if (!isset($tokenData['request_id']) || (string) $tokenData['request_id'] !== (string) $requestId) {
            return new WP_Error('request_id_mismatch', esc_html__('X-RC-Request-ID does not match the token', 'beyond-seo'), ['status' => 409]);
        }

        // Mark token as used and delete it
        delete_option($this->tokenOptionPrefix . $token);

        $manager = FileManager::getInstance();
        $zipPath = $manager->createLogsArchive();

        if (!$zipPath) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => [
                    'code' => 'no_logs_found',
                    'message' => __('No log files found', 'beyond-seo')
                ]
            ], JSON_PRETTY_PRINT);
            http_response_code(404);
            exit;
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename=' . basename($zipPath));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Content-Length: ' . filesize($zipPath));

        global $wp_filesystem;
        if (!$wp_filesystem) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
        
        if ($wp_filesystem && $wp_filesystem->is_readable($zipPath)) {
            echo $wp_filesystem->get_contents($zipPath);
        }
        
        wp_delete_file($zipPath);

        exit;
    }

    /**
     * Public health-check endpoint.
     * This endpoint is used to perform a self-check of the plugin's health status.
     * It is accessible without authentication and provides non-sensitive diagnostics.
     *
     * @param WP_REST_Request $request The REST request object.
     * @return array
     */
    public function selfCheckPublic(WP_REST_Request $request): array
    {
        // Public health-check endpoint; expose only non-sensitive diagnostics
        try {
            // Apply permissive CORS for public health-check
            if (!headers_sent()) {
                header('X-RC-Request-ID: ' . (string) CoreHelper::generateSecureToken());
            }
            self::wpFullCorsHeaders(true);
            return HealthManager::selfCheckPublic();
        } catch (Throwable $e) {
            $this->log('selfCheckPublic error: ' . $e->getMessage(), 'ERROR');
            //
        }
        return [
            'success' => false,
            'error' => [
                'code' => 'self_check_error',
                'message' => esc_html__('An error occurred while performing the self-check.', 'beyond-seo')
            ]
        ];
    }

	/**
	 * Apply CORS headers for REST API requests
	 * 
	 * This method handles both standard CORS headers and JWT-specific headers
	 * based on WordPress version and plugin configuration.
	 * 
	 * @param bool $allowFullCors Force applying headers regardless of configuration
	 * @return void
	 */
	public static function wpFullCorsHeaders(bool $allowFullCors = false): void {
		global $wp_version;

		if (!$allowFullCors) {
			return;
		}

		// Allow all origins (you can restrict this to a specific domain if needed)
		header('Access-Control-Allow-Origin: *');
		// Allow these HTTP methods
		header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

		// Get JWT auth headers through filter
		$jwtHeaders = apply_filters('jwt_auth_cors_allow_headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization, Cookie');
		// Standard REST headers
		$restHeaders = 'Content-Type, Authorization, X-Requested-With';

		// Merge and deduplicate headers
		$allHeaders = $jwtHeaders . ', ' . $restHeaders;
		$split = preg_split('/[\s,]+/', $allHeaders);
		$uniqueHeaders = implode(', ', array_unique(array_filter($split)));

		// Apply headers based on the WordPress version
		if (version_compare($wp_version, '5.5.0', '>=') && !headers_sent()) {
			// For WP 5.5.0+, we'll still set the header directly for immediate effect
			header(sprintf('Access-Control-Allow-Headers: %s', $uniqueHeaders));

			// Also hook into the WP filter for proper integration
			add_filter('rest_allowed_cors_headers', function(array $headers) use ($split) {
				return array_unique(array_merge($headers, $split));
			});
		} elseif (!headers_sent()) {
            header(sprintf('Access-Control-Allow-Headers: %s', $uniqueHeaders));
		}

		// Allow credentials if needed (not ideal with wildcard '*')
		header('Access-Control-Allow-Credentials: true');

		// Handle preflight (OPTIONS request)
		if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
			http_response_code(200);
			exit();
		}
	}

    /**
     * Register the schema cache meta to the REST response.
     * This allows the schema cache to be accessible via the REST API for pages and posts.
     */
    public function registerMetaToRestResponse(): void
    {
        // For pages
        register_post_meta( 'page', BaseConstants::OPTION_SCHEMA_CACHE, [
            'show_in_rest' => true,
            'type'         => 'string',
            'single'       => true,
        ] );

        // For posts
        register_post_meta( 'post', BaseConstants::OPTION_SCHEMA_CACHE, [
            'show_in_rest' => true,
            'type'         => 'string',
            'single'       => true,
        ] );

        // Register meta fields for posts
        register_post_meta('post', BaseConstants::META_KEY_SEO_TITLE, [
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'auth_callback' => function() {
                return current_user_can('edit_posts');
            }
        ]);

        register_post_meta('post', BaseConstants::META_KEY_SEO_DESCRIPTION, [
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'auth_callback' => function() {
                return current_user_can('edit_posts');
            }
        ]);

        // Register meta fields for pages
        register_post_meta('page', BaseConstants::META_KEY_SEO_TITLE, [
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'auth_callback' => function() {
                return current_user_can('edit_pages');
            }
        ]);

        register_post_meta('page', BaseConstants::META_KEY_SEO_DESCRIPTION, [
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'auth_callback' => function() {
                return current_user_can('edit_pages');
            }
        ]);
    }

    /**
     * Modify the REST API post-excerpt.
     * @param $response
     * @param $post
     * @param $request
     * @return mixed
     */
    public static function addFilteredExcerptToRestResponse($response, $post, $request): mixed
    {
        if ($post->post_type !== 'post' && $post->post_type !== 'page' && $post->post_type !== 'attachment') {
            return $response;
        }

        if (isset($response->data['excerpt']['rendered'])) {
            $excerpt = wp_strip_all_tags($response->data['excerpt']['rendered']); // Remove HTML
            // No more than 300 characters including "..." from the final
            $max_chars = 300;
            $excerpt = trim($excerpt);

            if (strlen($excerpt) > $max_chars) {
                $excerpt = mb_substr($excerpt, 0, $max_chars - 3); // leave room for "..."
                $excerpt = preg_replace('/\s+\S*$/u', '', $excerpt); // Remove trailing words if any
                $excerpt .= '...';
            }

            // Inject the cleaned excerpt into the JSON response
            $response->data['excerpt']['filtered'] = $excerpt;
        }

        return $response;
    }

    /**
     * Handle general settings endpoint for CRUD operations
     * 
     * @param WP_REST_Request $request The REST request object
     * @return WP_REST_Response
     */
    #[RcDocumentation(
        requestDto: SettingsRequestDto::class,
        responseDto: SettingsResponseDto::class,
        description: 'Handle general settings CRUD operations',
        summary: 'General Settings Management'
    )]
    public function handleGeneralSettings(WP_REST_Request $request): WP_REST_Response {
        try {
            $method = $request->get_method();
            $settingsManager = SettingsManager::instance();

            switch ($method) {
                case 'GET':
                    return $this->getGeneralSettings($settingsManager);

                case 'POST':
                    return $this->updateGeneralSettings($request, $settingsManager);

                default:
                    return $this->generateErrorResponse(
                        null,
                        __('Method not allowed', 'beyond-seo'),
                        405
                    );
            }
        } catch (Exception $e) {
            $this->log('Settings endpoint error: ' . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse(
                null,
                /* translators: %s: error message */
                sprintf(__('Internal server error: %s', 'beyond-seo'), $e->getMessage()),
                500
            );
        }
    }

    /**
     * Handle single setting endpoint for individual setting operations
     * 
     * @param WP_REST_Request $request The REST request object
     * @return WP_REST_Response
     */
    #[RcDocumentation(
        requestDto: SingleSettingRequestDto::class,
        responseDto: SingleSettingResponseDto::class,
        description: 'Handle individual setting CRUD operations',
        summary: 'Single Setting Management'
    )]
    public function handleSingleSetting(WP_REST_Request $request): WP_REST_Response {
        try {
            $method = $request->get_method();
            $key = $request->get_param('key');
            $settingsManager = SettingsManager::instance();

            switch ($method) {
                case 'GET':
                    return $this->getSingleSetting($key, $settingsManager);

                case 'POST':
                    return $this->updateSingleSetting($request, $key, $settingsManager);

                case 'DELETE':
                    return $this->resetSingleSetting($key, $settingsManager);

                default:
                    return $this->generateErrorResponse(
                        null,
                        'Method not allowed',
                        405
                    );
            }
        } catch (Exception $e) {
            $this->log('Single setting endpoint error: ' . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse(
                null,
                /* translators: %s: error message */
                sprintf(__('Internal server error: %s', 'beyond-seo'), $e->getMessage()),
                500
            );
        }
    }

    /**
     * Handle breadcrumbs endpoint for generating breadcrumbs for multiple types
     * 
     * @param WP_REST_Request $request The REST request object
     * @return WP_REST_Response
     */
    #[RcDocumentation(
        requestDto: BreadcrumbsRequestDto::class,
        responseDto: BreadcrumbsResponseDto::class,
        description: 'Generate breadcrumbs for multiple context types with full customization support. Supports post types, archives, search results, 404 pages, taxonomies, and date archives. Context-aware generation based on provided parameters.',
        summary: 'Multi-context Breadcrumbs Generator'
    )]
    public function handleBreadcrumbs(WP_REST_Request $request): WP_REST_Response {
        try {
            $method = $request->get_method();
            if ($method !== 'POST') {
                return $this->generateErrorResponse(
                    null,
                    'Method not allowed. Use POST.',
                    405
                );
            }

            // Check if breadcrumbs are enabled
            $settingsManager = SettingsManager::instance();
            if (!$settingsManager->enable_breadcrumbs) {
                return $this->generateErrorResponse(
                    null,
                    'Breadcrumbs are disabled in settings',
                    403
                );
            }

            $body = $request->get_json_params();
            
            // Create and validate DTO
            $requestDto = BreadcrumbsRequestDto::fromArray($body ?? []);
            $validationErrors = $requestDto->validate();
            
            if (!empty($validationErrors)) {
                return $this->generateErrorResponse(
                    null,
                    'Request validation failed: ' . implode(', ', $validationErrors),
                    400
                );
            }

            // Process breadcrumbs using the handler
            $responseHandler = new BreadcrumbsMultipleResponseHandler();
            $responseDto = $responseHandler->processMultipleTypes($requestDto->types, $requestDto->context);

            return $this->generateSuccessResponse(
                $responseDto->toArray(), 
                'Breadcrumbs generated successfully for ' . count($requestDto->types) . ' type(s)'
            );

        } catch (Exception $e) {
            $this->log('Breadcrumbs endpoint error: ' . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse(
                null,
                /* translators: %s: error message */
                sprintf(__('Internal server error: %s', 'beyond-seo'), $e->getMessage()),
                500
            );
        }
    }

    /**
     * Handle plugin update check endpoint
     *
     * @param WP_REST_Request $request The REST request object
     * @return WP_REST_Response
     */
    #[RcDocumentation(
        requestDto: \RankingCoach\Inc\Core\Plugin\Dtos\PluginUpdateCheckRequestDto::class,
        responseDto: \RankingCoach\Inc\Core\Plugin\Dtos\PluginUpdateCheckResponseDto::class,
        description: 'Checks if the rankingCoach plugin has an available update and returns current/available versions with a boolean flag.',
        summary: 'Plugin update availability'
    )]
    public function handlePluginUpdateCheck(WP_REST_Request $request): WP_REST_Response {
        try {
            if ($request->get_method() !== 'POST') {
                return $this->generateErrorResponse(null, 'Method not allowed. Use POST.', 405);
            }

            // Force WordPress to check for plugin updates
            CustomVersionLoader::forceUpdateCheck();

            $plugin_basename = RANKINGCOACH_PLUGIN_BASENAME; // ex: beyond-seo/beyond-seo.php
            $currentVersion  = defined('RANKINGCOACH_VERSION') ? (string) RANKINGCOACH_VERSION : '';

            $updates     = get_site_transient('update_plugins');
            $has_updates = false;
            $latestVersion = $currentVersion;

            if (is_object($updates) && isset($updates->response[$plugin_basename])) {
                $candidate = $updates->response[$plugin_basename];

                if (isset($candidate->new_version) && version_compare($candidate->new_version, $currentVersion, '>')) {
                    $has_updates   = true;
                    $latestVersion = (string) $candidate->new_version;
                } else {
                    unset($updates->response[$plugin_basename]);
                    set_site_transient('update_plugins', $updates);
                }
            }

            $responseDto = new \RankingCoach\Inc\Core\Plugin\Dtos\PluginUpdateCheckResponseDto(
                $currentVersion,
                $latestVersion,
                (bool) $has_updates
            );

            return $this->generateSuccessResponse(
                $responseDto->toArray(),
                'Plugin update check completed'
            );

        } catch (\Throwable $e) {
            $this->log('Plugin update check failed: ' . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse(
                $e,
                __('Failed to check plugin updates', 'beyond-seo'),
                500
            );
        }
    }


    /**
     * Get all general settings
     * 
     * @param SettingsManager $settingsManager
     * @return WP_REST_Response
     */
    private function getGeneralSettings(SettingsManager $settingsManager): WP_REST_Response {
        try {
            $settings = $settingsManager->get_options();

            // Group settings by category for better frontend consumption
            $categorizedSettings = $this->categorizeSettings($settings);

            return $this->generateSuccessResponse([
                'settings' => $settings,
                'categorized' => $categorizedSettings,
                'meta' => [
                    'total_settings' => count($settings),
                    'timestamp' => wp_date('Y-m-d H:i:s'),
                    'version' => get_option('rankingcoach_version', '1.0.0')
                ]
            ]);
        } catch (Exception $e) {
            $this->log('Error retrieving general settings: ' . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse(
                null,
                __('Failed to retrieve settings', 'beyond-seo'),
                500
            );
        }
    }

    /**
     * Update general settings (bulk update)
     * 
     * @param WP_REST_Request $request
     * @param SettingsManager $settingsManager
     * @return WP_REST_Response
     */
    private function updateGeneralSettings(WP_REST_Request $request, SettingsManager $settingsManager): WP_REST_Response {
        try {
            $body = $request->get_json_params();
            if (empty($body) || !is_array($body) || !isset($body['settings']) || !is_array($body['settings'])) {
                return $this->generateErrorResponse(
                    null,
                    __('Invalid request body. Expected JSON object with settings.', 'beyond-seo'),
                    400
                );
            }

            $validatedSettings = $this->validateSettings($body['settings']);
            if (is_wp_error($validatedSettings)) {
                return $this->generateErrorResponse(
                    null,
                    $validatedSettings->get_error_message(),
                    400
                );
            }

            $updatedSettings = [];
            $errors = [];

            foreach ($validatedSettings as $key => $value) {
                try {
                    $settingsManager->update_option($key, $value);
                    $updatedSettings[$key] = $value;
                } catch (Exception $e) {
                    $errors[$key] = $e->getMessage();
                    $this->log("Failed to update setting '{$key}': " . $e->getMessage(), 'ERROR');
                }
            }

            if (!empty($errors)) {
                return $this->generateErrorResponse(
                    $e ?? null,
                    'Some settings could not be updated. Reason: ' . json_encode($errors),
                    207 // Multi-Status
                );
            }

            return $this->generateSuccessResponse([
                'updated_settings' => $updatedSettings,
                'count' => count($updatedSettings),
                'timestamp' => wp_date('Y-m-d H:i:s')
            ], 'Settings updated successfully');

        } catch (Exception $e) {
            $this->log('Error updating general settings: ' . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse(
                null,
                /* translators: %s: error message */
                sprintf(__('Failed to update settings: %s', 'beyond-seo'), $e->getMessage()),
                500
            );
        }
    }

    /**
     * Get a single setting
     * 
     * @param string $key
     * @param SettingsManager $settingsManager
     * @return WP_REST_Response
     */
    private function getSingleSetting(string $key, SettingsManager $settingsManager): WP_REST_Response {
        try {
            if (!$this->isValidSettingKey($key)) {
                return $this->generateErrorResponse(
                    null,
                    /* translators: %s: setting key name */
                    sprintf(__('Invalid setting key: %s', 'beyond-seo'), $key),
                    400
                );
            }

            $value = $settingsManager->get_option($key);

            if ($value === null) {
                return $this->generateErrorResponse(
                    null,
                    "Setting '{$key}' not found",
                    404
                );
            }

            return $this->generateSuccessResponse([
                'key' => $key,
                'value' => $value,
                'type' => gettype($value)
            ]);

        } catch (Exception $e) {
            $this->log("Error retrieving setting '{$key}': " . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse(
                null,
                __('Failed to retrieve setting', 'beyond-seo'),
                500
            );
        }
    }

    /**
     * Update a single setting
     * 
     * @param WP_REST_Request $request
     * @param string $key
     * @param SettingsManager $settingsManager
     * @return WP_REST_Response
     */
    private function updateSingleSetting(WP_REST_Request $request, string $key, SettingsManager $settingsManager): WP_REST_Response {
        try {
            if (!$this->isValidSettingKey($key)) {
                return $this->generateErrorResponse(
                    null,
                    /* translators: %s: setting key name */
                    sprintf(__('Invalid setting key: %s', 'beyond-seo'), $key),
                    400
                );
            }

            $body = $request->get_json_params();
            if (!isset($body['value'])) {
                return $this->generateErrorResponse(
                    null,
                    "Missing 'value' parameter in request body",
                    400
                );
            }

            $value = $body['value'];
            $validatedValue = $this->validateSingleSetting($key, $value);

            if (is_wp_error($validatedValue)) {
                return $this->generateErrorResponse(
                    null,
                    $validatedValue->get_error_message(),
                    400
                );
            }

            // Get previous value before updating
            $previousValue = $settingsManager->get_option($key);
            $settingsManager->update_option($key, $validatedValue);

            return $this->generateSuccessResponse([
                'key' => $key,
                'value' => $validatedValue,
                'previous_value' => $previousValue,
                'timestamp' => wp_date('Y-m-d H:i:s')
            ], "Setting '{$key}' updated successfully");

        } catch (Exception $e) {
            $this->log("Error updating setting '{$key}': " . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse(
                null,
                /* translators: %s: error message */
                sprintf(__('Failed to update setting: %s', 'beyond-seo'), $e->getMessage()),
                500
            );
        }
    }

    /**
     * Reset a single setting to its default value
     * 
     * @param string $key
     * @param SettingsManager $settingsManager
     * @return WP_REST_Response
     */
    private function resetSingleSetting(string $key, SettingsManager $settingsManager): WP_REST_Response {
        try {
            if (!$this->isValidSettingKey($key)) {
                return $this->generateErrorResponse(
                    null,
                    /* translators: %s: setting key name */
                    sprintf(__('Invalid setting key: %s', 'beyond-seo'), $key),
                    400
                );
            }

            $defaultValue = $this->getDefaultSettingValue($key);
            if ($defaultValue === null) {
                return $this->generateErrorResponse(
                    null,
                    "No default value found for setting: {$key}",
                    400
                );
            }

            $previousValue = $settingsManager->get_option($key);
            $settingsManager->update_option($key, $defaultValue);

            $this->log("Setting '{$key}' reset to default value", 'INFO');

            return $this->generateSuccessResponse([
                'key' => $key,
                'value' => $defaultValue,
                'previous_value' => $previousValue,
                'timestamp' => wp_date('Y-m-d H:i:s')
            ], "Setting '{$key}' reset to default value");

        } catch (Exception $e) {
            $this->log("Error resetting setting '{$key}': " . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse(
                null,
                /* translators: %s: error message */
                sprintf(__('Failed to reset setting: %s', 'beyond-seo'), $e->getMessage()),
                500
            );
        }
    }

    /**
     * Validate settings data
     * 
     * @param array $settings
     * @return array|WP_Error
     */
    private function validateSettings(array $settings): array|WP_Error {
        $validatedSettings = [];
        $errors = [];

        foreach ($settings as $key => $value) {
            if (!$this->isValidSettingKey($key)) {
                /* translators: %s: setting key name */
                $errors[] = sprintf(__('Invalid setting key: %s', 'beyond-seo'), $key);
                continue;
            }

            $validatedValue = $this->validateSingleSetting($key, $value);
            if (is_wp_error($validatedValue)) {
                /* translators: 1: setting key name, 2: error message */
                $errors[] = sprintf(__("Setting '%1\$s': %2\$s", 'beyond-seo'), $key, $validatedValue->get_error_message());
                continue;
            }

            $validatedSettings[$key] = $validatedValue;
        }

        if (!empty($errors)) {
            return new WP_Error('validation_failed', implode('; ', $errors));
        }

        return $validatedSettings;
    }

    /**
     * Validate a single setting value
     * 
     * @param string $key
     * @param mixed $value
     * @return mixed|WP_Error
     */
    private function validateSingleSetting(string $key, mixed $value): mixed {
        // Get the expected type and validation rules for this setting
        $validationRules = $this->getSettingValidationRules($key);

        if (empty($validationRules)) {
            // If no specific validation rules, return the value as-is
            return $value;
        }

        // Type validation
        if (isset($validationRules['type'])) {
            $expectedType = $validationRules['type'];

            switch ($expectedType) {
                case 'boolean':
                    if (!is_bool($value) && !in_array($value, [0, 1, '0', '1', true, false], true)) {
                        /* translators: %s: setting key name */
                        return new WP_Error('invalid_type', sprintf(__("Setting '%s' must be a boolean value", 'beyond-seo'), $key));
                    }
                    $value = (bool) $value;
                    break;

                case 'integer':
                    if (!is_numeric($value)) {
                        /* translators: %s: setting key name */
                        return new WP_Error('invalid_type', sprintf(__("Setting '%s' must be a numeric value", 'beyond-seo'), $key));
                    }
                    $value = (int) $value;
                    break;

                case 'string':
                    $value = (string) $value;
                    break;

                case 'array':
                    if (!is_array($value)) {
                        return new WP_Error('invalid_type', "Setting '{$key}' must be an array");
                    }
                    break;
            }
        }

        // Range validation for numeric values
        if (isset($validationRules['min']) && is_numeric($value) && $value < $validationRules['min']) {
            return new WP_Error('value_too_small', "Setting '{$key}' must be at least {$validationRules['min']}");
        }

        if (isset($validationRules['max']) && is_numeric($value) && $value > $validationRules['max']) {
            return new WP_Error('value_too_large', "Setting '{$key}' must not exceed {$validationRules['max']}");
        }

        // String length validation
        if (isset($validationRules['max_length']) && is_string($value) && strlen($value) > $validationRules['max_length']) {
            return new WP_Error('string_too_long', "Setting '{$key}' must not exceed {$validationRules['max_length']} characters");
        }

        // Enum validation
        if (isset($validationRules['enum']) && !in_array($value, $validationRules['enum'], true)) {
            $allowed = implode(', ', $validationRules['enum']);
            return new WP_Error('invalid_value', "Setting '{$key}' must be one of: {$allowed}");
        }

        return $value;
    }

    /**
     * Check if a setting key is valid
     * 
     * @param string $key
     * @return bool
     */
    private function isValidSettingKey(string $key): bool {
        $validKeys = $this->getValidSettingKeys();
        return in_array($key, $validKeys, true);
    }

    /**
     * Get all valid setting keys from WPSettings entity
     * 
     * @return array
     */
    private function getValidSettingKeys(): array {
        static $validKeys = null;

        if ($validKeys === null) {
            try {
                $reflection = new ReflectionClass(WPSettings::class);
                $validKeys = [];

                foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
                    $validKeys[] = strtolower($property->getName());
                }
            } catch (ReflectionException $e) {
                $this->log('Error getting valid setting keys: ' . $e->getMessage(), 'ERROR');
                $validKeys = [];
            }
        }

        return $validKeys;
    }

    /**
     * Get validation rules for a specific setting
     * 
     * @param string $key
     * @return array
     */
    private function getSettingValidationRules(string $key): array {
        $rules = [
            // SEO Analysis
            'seo_analysis' => ['type' => 'boolean'],
            'seo_score_threshold' => ['type' => 'integer', 'min' => 1, 'max' => 100],
            'enable_readability_check' => ['type' => 'boolean'],

            // Keyword Optimization
            'focus_keyword_limit' => ['type' => 'integer', 'min' => 1, 'max' => 20],
            'focus_keyword_analysis' => ['type' => 'boolean'],

            // Google Analytics
            'google_analytics_integration' => ['type' => 'boolean'],
            'ga_tracking_id' => ['type' => 'string', 'max_length' => 50],

            // Schema Markup
            'enable_schema_markup' => ['type' => 'boolean'],
            'default_schema_type' => ['type' => 'string', 'enum' => ['Article', 'BlogPosting', 'NewsArticle', 'WebPage']],
            'site_represents' => ['type' => 'string', 'enum' => ['organization', 'person']],
            'site_links' => ['type' => 'boolean'],
            'organisation_or_person_name' => ['type' => 'string', 'max_length' => 100],
            'organisation_email' => ['type' => 'string', 'max_length' => 100],
            'organisation_phone' => ['type' => 'string', 'max_length' => 20],
            'organisation_logo' => ['type' => 'string', 'max_length' => 255],
            'run_shortcodes' => ['type' => 'boolean'],
            'website_alternate_name' => ['type' => 'string', 'max_length' => 100],

            // Redirects and 404
            'redirect_manager' => ['type' => 'boolean'],
            'redirect_404_to_home' => ['type' => 'boolean'],
            'monitoring_404' => ['type' => 'boolean'],

            // Indexing Control
            'default_noindex_posts' => ['type' => 'boolean'],
            'default_noindex_pages' => ['type' => 'boolean'],
            'index_categories' => ['type' => 'boolean'],
            'index_tags' => ['type' => 'boolean'],

            // Social Media
            'enable_social_optimization' => ['type' => 'boolean'],
            'default_og_image' => ['type' => 'string', 'max_length' => 255],
            'default_twitter_card' => ['type' => 'string', 'enum' => ['summary', 'summary_large_image', 'app', 'player']],
            'organization_social_facebook' => ['type' => 'string', 'max_length' => 255],
            'organization_social_twitter' => ['type' => 'string', 'max_length' => 255],
            'organization_social_instagram' => ['type' => 'string', 'max_length' => 255],
            'organization_social_linkedin' => ['type' => 'string', 'max_length' => 255],
            'organization_social_youtube' => ['type' => 'string', 'max_length' => 255],
            'organization_social_tiktok' => ['type' => 'string', 'max_length' => 255],
            'organization_social_pinterest' => ['type' => 'string', 'max_length' => 255],
            'organization_social_github' => ['type' => 'string', 'max_length' => 255],
            'organization_social_snapchat' => ['type' => 'string', 'max_length' => 255],
            'organization_social_tumblr' => ['type' => 'string', 'max_length' => 255],
            'organization_social_reddit' => ['type' => 'string', 'max_length' => 255],
            'organization_social_whatsapp' => ['type' => 'string', 'max_length' => 255],
            'organization_social_telegram' => ['type' => 'string', 'max_length' => 255],
            'organization_social_mastodon' => ['type' => 'string', 'max_length' => 255],
            'organization_social_flickr' => ['type' => 'string', 'max_length' => 255],
            'organization_social_vimeo' => ['type' => 'string', 'max_length' => 255],
            'organization_social_foursquare' => ['type' => 'string', 'max_length' => 255],
            'organization_social_yelp' => ['type' => 'string', 'max_length' => 255],
            'organization_social_quora' => ['type' => 'string', 'max_length' => 255],
            'organization_social_discord' => ['type' => 'string', 'max_length' => 255],
            'organization_social_slack' => ['type' => 'string', 'max_length' => 255],
            'organization_social_wechat' => ['type' => 'string', 'max_length' => 255],
            'organization_social_weibo' => ['type' => 'string', 'max_length' => 255],
            'organization_social_line' => ['type' => 'string', 'max_length' => 255],
            'organization_social_vk' => ['type' => 'string', 'max_length' => 255],
            'organization_social_telegram_channel' => ['type' => 'string', 'max_length' => 255],
            'organization_social_telegram_group' => ['type' => 'string', 'max_length' => 255],
            'organization_social_messenger' => ['type' => 'string', 'max_length' => 255],
            'organization_social_whatsapp_group' => ['type' => 'string', 'max_length' => 255],
            'organization_social_signal' => ['type' => 'string', 'max_length' => 255],
            'organization_additional_social_urls' => ['type' => 'array', 'items' => ['type' => 'string', 'max_length' => 255]],

            // Sitemap
            'sitemap' => ['type' => 'array'],

            // Local SEO
            'enable_local_seo' => ['type' => 'boolean'],
            'default_business_type' => ['type' => 'string', 'max_length' => 50],
            'business_latitude' => ['type' => 'string', 'max_length' => 20],
            'business_longitude' => ['type' => 'string', 'max_length' => 20],

            // Internal Links
            'internal_link_suggestions' => ['type' => 'boolean'],
            'enable_breadcrumbs' => ['type' => 'boolean'],

            // Security
            'security_noopen' => ['type' => 'boolean'],
            'security_nosnippet' => ['type' => 'boolean'],

            // Performance
            'enable_lazy_loading' => ['type' => 'boolean'],
            'minify_html' => ['type' => 'boolean'],

            // Cache
            'account_details_cache_seconds' => ['type' => 'integer', 'min' => 300, 'max' => 86400],
            'gmb_categories_cache_seconds' => ['type' => 'integer', 'min' => 300, 'max' => 86400],

            // RSS
            'rss' => ['type' => 'array'],

            // Miscellaneous
            'enable_user_action_and_event_logs_sharing' => ['type' => 'boolean'],
            'enable_wp_cron_service' => ['type' => 'boolean'],
            'allow_seo_optimiser_on_saved_posts' => ['type' => 'boolean'],
            'allow_sync_keywords_to_rankingcoach' => ['type' => 'boolean'],

            // Cleanup and Uninstall
            'remove_settings_on_deactivation' => ['type' => 'boolean'],

            // Complex arrays
            'separators' => ['type' => 'array'],
            'organisation_number_of_employees' => ['type' => 'array'],
        ];

        return $rules[$key] ?? [];
    }

    /**
     * Get default value for a setting
     * 
     * @param string $key
     * @return mixed|null
     */
    private function getDefaultSettingValue(string $key): mixed {
        try {
            $reflection = new ReflectionClass(WPSettings::class);
            $property = $reflection->getProperty($key);
            return $property->getDefaultValue();
        } catch (ReflectionException $e) {
            $this->log("Error getting default value for setting '{$key}': " . $e->getMessage(), 'ERROR');
            return null;
        }
    }

    /**
     * Categorize settings for better frontend organization
     * 
     * @param array $settings
     * @return array
     */
    private function categorizeSettings(array $settings): array {
        $categories = [
            'seo' => [
                'label' => 'SEO Analysis',
                'settings' => []
            ],
            'keywords' => [
                'label' => 'Keywords',
                'settings' => []
            ],
            'analytics' => [
                'label' => 'Analytics',
                'settings' => []
            ],
            'schema' => [
                'label' => 'Schema Markup',
                'settings' => []
            ],
            'redirects' => [
                'label' => 'Redirects & 404',
                'settings' => []
            ],
            'indexing' => [
                'label' => 'Indexing',
                'settings' => []
            ],
            'social' => [
                'label' => 'Social Media',
                'settings' => []
            ],
            'sitemap' => [
                'label' => 'XML Sitemap',
                'settings' => []
            ],
            'local' => [
                'label' => 'Local SEO',
                'settings' => []
            ],
            'links' => [
                'label' => 'Internal Links',
                'settings' => []
            ],
            'security' => [
                'label' => 'Security',
                'settings' => []
            ],
            'performance' => [
                'label' => 'Performance',
                'settings' => []
            ],
            'advanced' => [
                'label' => 'Advanced',
                'settings' => []
            ]
        ];

        $categoryMapping = [
            'enable_user_action_and_event_logs_sharing' => 'advanced',
            'enable_wp_cron_service' => 'advanced',
            'allow_seo_optimiser_on_saved_posts' => 'advanced',
            'allow_sync_keywords_to_rankingcoach' => 'advanced',
            'remove_settings_on_deactivation' => 'advanced',
            'seo_analysis' => 'seo',
            'seo_score_threshold' => 'seo',
            'enable_readability_check' => 'seo',
            'focus_keyword_limit' => 'keywords',
            'focus_keyword_analysis' => 'keywords',
            'google_analytics_integration' => 'analytics',
            'ga_tracking_id' => 'analytics',
            'enable_schema_markup' => 'schema',
            'default_schema_type' => 'schema',
            'site_represents' => 'schema',
            'site_links' => 'schema',
            'organisation_or_person_name' => 'schema',
            'organisation_email' => 'schema',
            'organisation_phone' => 'schema',
            'organisation_logo' => 'schema',
            'organisation_founding_date' => 'schema',
            'organisation_number_of_employees' => 'schema',
            'run_shortcodes' => 'schema',
            'website_alternate_name' => 'schema',
            'person_manual_name' => 'schema',
            'person_manual_image' => 'schema',
            'organization_social_facebook' => 'social',
            'organization_social_twitter' => 'social',
            'organization_social_instagram' => 'social',
            'organization_social_linkedin' => 'social',
            'organization_social_youtube' => 'social',
            'organization_social_tiktok' => 'social',
            'organization_social_pinterest' => 'social',
            'organization_social_github' => 'social',
            'organization_social_tumblr' => 'social',
            'organization_social_snapchat' => 'social',
            'organization_social_wikipedia' => 'social',
            'organization_social_personal_website' => 'social',
            'organization_additional_social_urls' => 'social',
            'redirect_manager' => 'redirects',
            'redirect_404_to_home' => 'redirects',
            'monitoring_404' => 'redirects',
            'default_noindex_posts' => 'indexing',
            'default_noindex_pages' => 'indexing',
            'index_categories' => 'indexing',
            'index_tags' => 'indexing',
            'enable_social_optimization' => 'social',
            'default_og_image' => 'social',
            'default_twitter_card' => 'social',
            'sitemap' => 'sitemap',
            'enable_local_seo' => 'local',
            'default_business_type' => 'local',
            'business_latitude' => 'local',
            'business_longitude' => 'local',
            'internal_link_suggestions' => 'links',
            'enable_breadcrumbs' => 'links',
            'security_noopen' => 'security',
            'security_nosnippet' => 'security',
            'enable_lazy_loading' => 'performance',
            'minify_html' => 'performance',
        ];

        foreach ($settings as $key => $value) {
            $category = $categoryMapping[$key] ?? 'advanced';
            $categories[$category]['settings'][$key] = $value;
        }

        // Remove empty categories
        return array_filter($categories, fn($category) => !empty($category['settings']));
    }
}
