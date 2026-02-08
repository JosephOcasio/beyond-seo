<?php /** @noinspection PhpLackOfCohesionInspection */
declare( strict_types=1 );

namespace RankingCoach\Inc\Core\Api;

use DDD\Infrastructure\Validation\Constraints\Choice;
use Exception;
use RankingCoach\Inc\Core\Helpers\CoreHelper;
use RankingCoach\Inc\Exceptions\UnsupportedHttpMethodException;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Exceptions\HttpApiException;
use RankingCoach\Inc\Exceptions\InvalidUrlException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Class ExternalApi
 */
class HttpApiClient {

	use RcLoggerTrait;

	public const CALL_METHOD_GET = 'GET';
	public const CALL_METHOD_POST = 'POST';
	public const CALL_METHOD_PUT = 'PUT';
	public const CALL_METHOD_PATCH = 'PATCH';
	public const CALL_METHOD_DELETE = 'DELETE';

	/** @var HttpClientInterface $client The HTTP client. */
	protected HttpClientInterface $client;

	/** @var string|null $url The URL for the API. */
	protected ?string $url = null;

	/** @var array $defaultHeaders The default headers to include with each request. */
	protected array $defaultHeaders;

	/** @var array $securityHeaders The security headers to include with each request. */
	protected array $securityHeaders;

	/** @var string $methodType The method type. */
	#[Choice( choices: [
		self::CALL_METHOD_GET,
		self::CALL_METHOD_POST,
		self::CALL_METHOD_PATCH,
		self::CALL_METHOD_DELETE
	] )]
	protected string $methodType = self::CALL_METHOD_GET;

	protected array $allowedMethods = [
		self::CALL_METHOD_GET,
		self::CALL_METHOD_POST,
		self::CALL_METHOD_PUT,
		self::CALL_METHOD_PATCH,
		self::CALL_METHOD_DELETE
	];

	/** @var RetryHandler $retriesHandler The retry handler. */
	private RetryHandler $retriesHandler;

	/** @var HttpAPIResponseHandler $responseHandler The response handler. */
	private HttpAPIResponseHandler $responseHandler;
	private int $maxRetries = 2;
	private int $backoffFactor = 2;

	/**
	 * ExternalApi constructor.
	 *
	 * @param array $defaultHeaders Default headers to include with each request.
	 */
	public function __construct( ?HttpClientInterface $client = null, array $defaultHeaders = [], ?string $accessToken = null ) {
		$this->defaultHeaders = array_merge( [
			'Accept'       => 'application/json',
			'Content-Type' => 'application/json',
		], $defaultHeaders );
		$this->securityHeaders = [];

		$this->client         = $client ?? self::createHttpClient();

		if ( $accessToken ) {
			$this->setBearerToken( $accessToken );
		}

		$this->retriesHandler  = new RetryHandler( $this->maxRetries, $this->backoffFactor );
		$this->responseHandler = new HttpAPIResponseHandler();
	}

	/**
	 * Set the security headers for the API client.
	 *
	 * @param array $headers The headers to set.
	 */
	public function setSecurityHeaders( array $headers ): void {
		$this->securityHeaders = array_merge( $this->securityHeaders, $headers );
	}

	/**
	 * Create a secure HTTP client.
	 *
	 * @param array $config
	 *
	 * @return HttpClientInterface
	 */
	public static function createHttpClient( array $config = [] ): HttpClientInterface {
		$defaultConfig = [
			'verify_peer'   => true,
			'verify_host'   => true,
			'timeout'       => 60,
			'max_redirects' => 5
		];

		return HttpClient::create( array_merge( $defaultConfig, $config ) );
	}

	/**
	 * Set the retry strategy for the API client.
	 *
	 * @param int $maxRetries The maximum number of retries.
	 * @param int $backoffFactor The backoff factor.
	 */
	public function setRetryStrategy( int $maxRetries, int $backoffFactor ): void {
		$this->maxRetries    = $maxRetries;
		$this->backoffFactor = $backoffFactor;
	}

	/**
	 * Send a GET request to the API.
	 *
	 * @param array $queryParams Query parameters to include in the request.
	 *
	 * @return array The HTTP response parsed.
	 * @throws HttpApiException
	 */
	public function get( array $queryParams = [], array $jsonParams = [] ): array {
		$this->methodType = self::CALL_METHOD_GET;
		$options          = [];

        if (!empty($queryParams)) {
            $options['query'] = $queryParams;
        }

        if (!empty($jsonParams)) {
            $options['json'] = $jsonParams;
        }

		return $this->retriesHandler->execute( function () use ( $options ) {

			// Input validation
			$this->validateUrlAndMethod();
			// Request
			$this->sendClientRequest( $options, $this->securityHeaders );

			// Response
			return $this->responseHandler->validate()->parse();
		} ) ?? [];
	}

	/**
	 * Send a POST request to the API.
	 *
	 * @param array $data The data to include in the request body.
	 *
	 * @return array The HTTP response parsed.
	 * @throws HttpApiException
	 */
	public function post( array $data = [] ): array {
		$this->methodType = self::CALL_METHOD_POST;
		$options = [ 'json' => $data ];

		return $this->retriesHandler->execute( function () use ( $options ) {

			// Input validation
			$this->validateUrlAndMethod();
			// Request
			$this->sendClientRequest( $options, $this->securityHeaders );

			// Response
			return $this->responseHandler->validate()->parse();
		} ) ?? [];
	}

	/**
	 * Send a PUT request to the API.
	 *
	 * @param array $data The data to include in the request body.
	 *
	 * @return array The HTTP response parsed.
	 * @throws HttpApiException
	 */
	public function put( array $data = [] ): array {
		$this->methodType = self::CALL_METHOD_PUT;
		$options          = [ 'json' => $data ];

		return $this->retriesHandler->execute( function () use ( $options ) {

			// Input validation
			$this->validateUrlAndMethod();
			// Request
			$this->sendClientRequest( $options, $this->securityHeaders );

			// Response
			return $this->responseHandler->validate()->parse();
		} ) ?? [];
	}

	/**
	 * Send a DELETE request to the API.
	 *
	 * @return array The HTTP response parsed.
	 * @throws HttpApiException
	 */
	public function delete(): array {
		$this->methodType = self::CALL_METHOD_DELETE;

		return $this->retriesHandler->execute( function () {

			// Input validation
			$this->validateUrlAndMethod();
			// Request
			$this->sendClientRequest([], $this->securityHeaders);

			// Response
			return $this->responseHandler->validate()->parse();
		} ) ?? [];
	}

	/**
	 * Build the full URL for a given API endpoint.
	 *
	 * @param string $endpoint The API endpoint.
	 *
	 * @return string The full URL.
	 * @throws Exception
	 */
	public function buildUrl( string $endpoint ): string {
		if ( empty( $endpoint ) ) {
			throw new Exception( esc_html__('API endpoint cannot be empty.', 'beyond-seo') );
		}

		return $this->url . '/' . ltrim( $endpoint, '/' );
	}

	/**
	 * Set the base URL for the API.
	 *
	 * @param string $url The base URL.
	 *
	 * @throws Exception
	 */
	public function setUrl( string $url ): void {
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			throw new InvalidUrlException( esc_html__('Invalid URL format: ', 'beyond-seo') . esc_url($url) );
		}
		$this->url = $url;
	}

	/**
	 * Set default headers for the API client.
	 *
	 * @param array $headers The headers to set.
	 */
	public function setDefaultHeaders( array $headers ): void {
		$this->defaultHeaders = array_merge( $this->defaultHeaders, $headers );
	}

	/**
	 * Add a single header to the default headers.
	 *
	 * @param string $key The header name.
	 * @param string $value The header value.
	 */
	public function addDefaultHeader( string $key, string $value ): void {
		$this->defaultHeaders[ $key ] = $value;
	}

	/**
	 * Set the Bearer token for authorization.
	 *
	 * @param string $token The Bearer token.
	 *
	 * @return void
	 */
	public function setBearerToken( string $token ): void {
		$this->addDefaultHeader( 'Authorization', "Bearer $token" );
	}

	/**
	 * Get the Bearer token from authorization.
	 * @return string
	 */
	public function getBearerToken(): string {
		$authorization = $this->defaultHeaders['Authorization'] ?? '';
		return str_replace( 'Bearer ', '', $authorization );
	}

	/**
	 * Remove the Bearer token from authorization.
	 *
	 * @return void
	 */
	public function removeBearerToken(): void {
		unset( $this->defaultHeaders['Authorization'] );
	}

	/**
	 * @param array $options
	 * @param array $securityHeaders
	 *
	 * @return ResponseInterface
	 * @throws HttpApiException
	 */
	public function sendClientRequest( array $options = [], array $securityHeaders = [] ): ResponseInterface {
		$startTime = microtime(true);

		try {
			$this->setDefaultHeaders( $securityHeaders );
			$response = $this->responseHandler->response = $this->client->request(
				$this->methodType,
				$this->url,
				array_merge( [ 'headers' => $this->defaultHeaders ], $options )
			);
            //if(str_contains($this->url, 'register')!== false) {
            //    echo json_encode($response->getContent(false));
            //    die;
            //}

			$executionTime = round((microtime(true) - $startTime) * 1000, 2); // milliseconds

			// Log successful request
			$this->log_json([
				'operation_type' => 'http_request',
				'operation_status' => 'success',
				'api_calls' => null,
				'context_entity' => 'http_client',
				'context_id' => null,
				'context_type' => $this->methodType,
				'execution_time' => $executionTime,
				'error_details' => null,
				'metadata' => [
					'url' => $this->url,
					'method' => $this->methodType,
                    'payload' => $options,
                    'response' => $response->getContent(false),
					'status_code' => $response->getStatusCode(),
                    'request_headers' => $this->defaultHeaders, // Log only header keys for security
					'request_size' => json_encode($options) ? strlen(json_encode($options)) : 0
				]
			], 'api');

			return $response;

		} catch ( TransportExceptionInterface $e ) {
			$executionTime = round((microtime(true) - $startTime) * 1000, 2);
			$this->log_json([
				'operation_type' => 'http_request',
				'operation_status' => 'error',
				'api_calls' => null,
				'context_entity' => 'http_client',
				'context_id' => null,
				'context_type' => $this->methodType,
				'execution_time' => $executionTime,
				'error_details' => [
					'exception_type' => 'TransportException',
					'exception_message' => $e->getMessage(),
					'exception_code' => $e->getCode(),
					'exception_file' => $e->getFile(),
					'exception_line' => $e->getLine(),
                    'exception_trace' => $e->getTraceAsString()
				],
				'metadata' => [
					'url' => $this->url,
					'method' => $this->methodType,
                    'payload' => $options,
					'error_category' => 'transport_error'
				]
			], 'api');
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            /* translators: %s: transport error message */
			throw new HttpApiException( sprintf(__('Transport error occurred during request: %s', 'beyond-seo'), esc_html($e->getMessage())), 0, $e );
		} catch ( ClientExceptionInterface $e ) {
			$executionTime = round((microtime(true) - $startTime) * 1000, 2);
			$this->log_json([
				'operation_type' => 'http_request',
				'operation_status' => 'error',
				'api_calls' => null,
				'context_entity' => 'http_client',
				'context_id' => null,
				'context_type' => $this->methodType,
				'execution_time' => $executionTime,
				'error_details' => [
					'exception_type' => 'ClientException',
					'exception_message' => $e->getMessage(),
					'exception_code' => $e->getCode(),
					'exception_file' => $e->getFile(),
					'exception_line' => $e->getLine(),
                    'exception_trace' => $e->getTraceAsString()
				],
				'metadata' => [
					'url' => $this->url,
					'method' => $this->methodType,
                    'payload' => $options,
					'error_category' => 'client_error',
                    'error' => $e->getMessage(),
                    'error_file' => $e->getFile(),
                    'error_line' => $e->getLine()
				]
			], 'api');
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            /* translators: %s: client error message */
			throw new HttpApiException( sprintf(__('Client error: %s', 'beyond-seo'), esc_html($e->getMessage())), 400, $e );
		} catch ( ServerExceptionInterface $e ) {
			$executionTime = round((microtime(true) - $startTime) * 1000, 2);
			$this->log_json([
				'operation_type' => 'http_request',
				'operation_status' => 'error',
				'api_calls' => null,
				'context_entity' => 'http_client',
				'context_id' => null,
				'context_type' => $this->methodType,
				'execution_time' => $executionTime,
				'error_details' => [
					'exception_type' => 'ServerException',
					'exception_message' => $e->getMessage(),
					'exception_code' => $e->getCode(),
					'exception_file' => $e->getFile(),
					'exception_line' => $e->getLine(),
                    'exception_trace' => $e->getTraceAsString()
				],
				'metadata' => [
					'url' => $this->url,
					'method' => $this->methodType,
                    'payload' => $options,
					'error_category' => 'server_error'
				]
			], 'api');
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            /* translators: %s: server error message */
			throw new HttpApiException( sprintf(__('Server error: %s', 'beyond-seo'), esc_html($e->getMessage())), 500, $e );
		} catch ( RedirectionExceptionInterface $e ) {
			$executionTime = round((microtime(true) - $startTime) * 1000, 2);
			$this->log_json([
				'operation_type' => 'http_request',
				'operation_status' => 'error',
				'api_calls' => null,
				'context_entity' => 'http_client',
				'context_id' => null,
				'context_type' => $this->methodType,
				'execution_time' => $executionTime,
				'error_details' => [
					'exception_type' => 'RedirectionException',
					'exception_message' => $e->getMessage(),
					'exception_code' => $e->getCode(),
					'exception_file' => $e->getFile(),
					'exception_line' => $e->getLine(),
                    'exception_trace' => $e->getTraceAsString()
				],
				'metadata' => [
					'url' => $this->url,
					'method' => $this->methodType,
                    'payload' => $options,
					'error_category' => 'redirection_error'
				]
			], 'api');
            throw new HttpApiException(
                sprintf(
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                /* translators: %s is the error message. */
                    __( 'Redirection error: %s', 'beyond-seo' ),
                    esc_html( $e->getMessage() )
                ),
                300,
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                $e
            );
        }
	}

	/**
	 * @param ResponseInterface $response
	 *
	 * @return void
	 * @throws TransportExceptionInterface
	 * @throws ClientExceptionInterface
	 * @throws RedirectionExceptionInterface
	 * @throws ServerExceptionInterface
	 * @throws Exception
	 */
	public function validateResponse( ResponseInterface $response ): void {
		$content = $response->getContent();
		if ( str_contains( $response->getHeaders()['content-type'][0], 'application/json' ) ) {
			$data = json_decode( $content, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				/* translators: %s: JSON error message */
				throw new Exception( sprintf(esc_html__('Error decoding JSON response: %s', 'beyond-seo'), esc_html(json_last_error_msg())) );
			}
		} elseif ( str_contains( $response->getHeaders()['content-type'][0], 'text/plain' ) ) {
			$data = $content;
		} else {
            /* translators: %s: unsupported content type */
			throw new Exception( esc_html__('Unsupported content type.', 'beyond-seo') );
		}
		if ( $response->getStatusCode() >= 400 ) {
			$error   = $data['error'] ?? __('Unknown error', 'beyond-seo');
			$message = $data['message'] ?? __('Unknown message', 'beyond-seo');
			/* translators: 1: error type, 2: error message */
			throw new Exception( sprintf(esc_html__('API error: %1$s - %2$s', 'beyond-seo'), esc_html($error), esc_html($message)) );
		}
	}

	/**
	 * Validate the URL and method type.
	 * @return void
	 * @throws Exception
	 */
	public function validateUrlAndMethod(): void {
		if ( ! $this->url ) {
			throw new InvalidUrlException();
		}
		if ( ! $this->methodType ) {
            /* translators: %s: HTTP method type */
			throw new UnsupportedHttpMethodException( sprintf(esc_html__('Unsupported HTTP method: %s', 'beyond-seo'), esc_html($this->methodType)) );
		}
	}

	/**
	 * Update the object keys with a prefix.
	 *
	 * @param object|array $object The object to update.
	 * @param bool $addPrefix
	 * @param string $key The key to update.
	 */
	public function updateObjectKeys( mixed &$object, bool $addPrefix = true, string $key = 'objectType' ): void {
		$prefix = "RankingCoach\\";
		if ( is_array( $object ) || is_object( $object ) ) {
			foreach ( $object as &$value ) {
				$this->updateObjectKeys( $value, $addPrefix, $key );
			}
		} else {
			return;
		}

		if ( is_array( $object ) && isset( $object[ $key ] ) ) {
			if ( $addPrefix && !str_starts_with( $object[ $key ], $prefix ) ) {
				$object[ $key ] = $prefix . $object[ $key ];
			}
			if ( !$addPrefix && str_starts_with( $object[ $key ], $prefix ) ) {
				$object[ $key ] = substr( $object[ $key ], strlen( $prefix ) );
			}
		}

		if ( is_object( $object ) && property_exists( $object, $key ) ) {
			if ( $addPrefix && !str_starts_with( $object->{$key}, $prefix ) ) {
				$object->{$key} = $prefix . $object->{$key};
			}
			if ( !$addPrefix && str_starts_with( $object->{$key}, $prefix ) ) {
				$object->{$key} = substr( $object->{$key}, strlen( $prefix ) );
			}
		}
	}

    /**
     * Prepare security headers for the API client.
     * Now uses the consolidated logic from CoreHelper.
     *
     * @param string|null $accessToken
     * @param array $userPayload
     * @return void
     */
	public function prepareSecurityHeaders(?string $accessToken = null, array $userPayload = []): void {
		CoreHelper::setSecurityHeaders($this, $accessToken, $userPayload);
	}

}
