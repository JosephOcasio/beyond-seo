<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core\Api;

use Exception;
use RankingCoach\Inc\Exceptions\UnsupportedContentTypeException;
use RankingCoach\Inc\Exceptions\HttpApiException;
use RankingCoach\Inc\Exceptions\ResponseValidationException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use function apply_filters;
use function rceh;

/**
 * Class HttpAPIResponseHandler
 */
class HttpAPIResponseHandler {

	/** @var ResponseInterface $response The response. */
	public ResponseInterface $response;

	/**
	 * Validate the response.
	 *
	 * @param ResponseInterface|null $response The response.
	 *
	 * @throws ClientExceptionInterface
	 * @throws RedirectionExceptionInterface
	 * @throws ServerExceptionInterface
	 * @throws TransportExceptionInterface
	 * @throws Exception
	 */
	public function validate(?ResponseInterface $response = null): static
	{
		$response = $response ?? $this->response;
		if ($response->getStatusCode() >= 400) {
			$content = $response->getContent(false);
			$data = json_decode($content, true);

			$error = 'Unknown error';
			$message = '';

			if (json_last_error() !== JSON_ERROR_NONE) {
				$error = 'Invalid JSON response';
				$decodedContent = htmlspecialchars_decode($content);
				$doctypePosition = strrpos($decodedContent, '<!DOCTYPE');
				if ($doctypePosition !== false) {
					$decodedContent = substr($decodedContent, 0, $doctypePosition);
				}
				$message = trim($decodedContent);
			} else {
				if (is_array($data)) {
					$error = isset($data['error']) && is_string($data['error']) ? trim($data['error']) : 'Unknown error';
					$message = isset($data['message']) && is_string($data['message']) ? trim($data['message']) : '';
				}
			}

			$parts = array_filter(array_map(static fn($part) => is_string($part) ? trim($part) : '', [ $error, $message ]), static fn($part) => $part !== '');
			$errorText = $parts ? implode(' - ', array_unique($parts)) : 'Unknown error';
			$exception = new HttpApiException($errorText);

			$errorDetails = [
				'status_code' => $response->getStatusCode(),
				'error' => $error,
				'message' => $message,
				'content' => $content,
			];

			$shouldThrow = apply_filters('rankingcoach_http_api_response_throw_exception', false, $errorDetails, $response);

			if ($shouldThrow) {
				throw $exception;
			}

			rceh()->error($exception, $content);
		}
		return $this;
	}

	/**
	 * Parse the response.
	 *
	 * @param ResponseInterface|null $response The response.
	 *
	 * @return array
	 *
	 * @throws ClientExceptionInterface
	 * @throws RedirectionExceptionInterface
	 * @throws ServerExceptionInterface
	 * @throws TransportExceptionInterface
	 * @throws Exception
	 */
	public function parse(?ResponseInterface $response = null): array
	{
		$response = $response ?? $this->response;
		$content = $response->getContent(false);
		$contentType = $response->getHeaders(false)['content-type'][0] ?? '';

		if (str_contains($contentType, 'application/json')) {
			$data = json_decode($content);
			if (json_last_error() !== JSON_ERROR_NONE) {
				rceh()->error( new ResponseValidationException('Error decoding JSON response: ' . json_last_error_msg() ), $content );
			}
			return ['content' => $data];
		} elseif (str_contains($contentType, 'text/plain') || str_contains($contentType, 'text/html')) {
			return ['content' => $content];
		} else {
			rceh()->error( new UnsupportedContentTypeException("Unsupported content type: $contentType"), $content );
		}
	}
}