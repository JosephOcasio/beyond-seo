<?php

declare(strict_types=1);

namespace App\Infrastructure\Middleware;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * LogRedactionMiddleware
 *
 * Sanitizes HTTP requests and responses before logging to prevent
 * accidental credential exposure in logs.
 *
 * Redacts:
 * - Authorization, X-API-Key, X-Auth-Token headers
 * - Password, token, credential form fields
 * - Bearer tokens in Authorization header
 *
 * @since 1.0.0
 */
class LogRedactionMiddleware implements MiddlewareInterface
{
    /**
     * Sensitive headers to redact (case-insensitive)
     */
    private const SENSITIVE_HEADERS = [
        'authorization',
        'x-api-key',
        'x-auth-token',
        'x-access-token',
        'cookie',
        'set-cookie',
    ];

    /**
     * Sensitive form field patterns to redact
     */
    private const SENSITIVE_FIELDS = [
        'password',
        'token',
        'credential',
        'secret',
        'api_key',
        'apikey',
        'auth',
        'access_token',
        'refresh_token',
    ];

    /**
     * Process the request and redact sensitive data before logging.
     */
    public function process(RequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Redact sensitive headers from request
        $redactedRequest = $this->redactRequestHeaders($request);

        // Process the request
        $response = $handler->handle($redactedRequest);

        return $response;
    }

    /**
     * Redact sensitive headers from request
     */
    private function redactRequestHeaders(RequestInterface $request): RequestInterface
    {
        $redacted = $request;

        foreach (self::SENSITIVE_HEADERS as $header) {
            if ($redacted->hasHeader($header)) {
                $redacted = $redacted->withHeader($header, '[REDACTED]');
            }
        }

        return $redacted;
    }

    /**
     * Redact sensitive data from array (e.g., request parameters, form data)
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function redactArrayData(array $data): array
    {
        $redacted = [];

        foreach ($data as $key => $value) {
            $lowerKey = strtolower($key);

            // Check if key matches sensitive field patterns
            $isSensitive = false;
            foreach (self::SENSITIVE_FIELDS as $pattern) {
                if (strpos($lowerKey, $pattern) !== false) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $redacted[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $redacted[$key] = self::redactArrayData($value);
            } else {
                $redacted[$key] = $value;
            }
        }

        return $redacted;
    }

    /**
     * Redact sensitive data from string (e.g., request body JSON)
     */
    public static function redactStringData(string $data): string
    {
        // Pattern to match Bearer tokens
        $data = preg_replace(
            '/Bearer\s+[a-zA-Z0-9_\-\.]+/i',
            'Bearer [REDACTED]',
            $data
        );

        // Pattern to match common credential assignments in JSON/form data
        foreach (self::SENSITIVE_FIELDS as $field) {
            $data = preg_replace(
                '/"' . preg_quote($field) . '"\s*:\s*"[^"]*"/i',
                '"' . $field . '": "[REDACTED]"',
                $data
            );

            $data = preg_replace(
                '/(' . preg_quote($field) . ')=([^&\s]+)/i',
                '$1=[REDACTED]',
                $data
            );
        }

        return $data;
    }
}
