<?php

declare(strict_types=1);

namespace App\Infrastructure\Traits;

use DDD\Infrastructure\Exceptions\InternalErrorException;
use DDD\Presentation\Base\Dtos\RestResponseDto;
use Exception;
use JsonException;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RuntimeException;

trait ResponseErrorTrait {
    use RcLoggerTrait;

    /**
     * Cached result of WordPress production check
     * @var bool|null
     */
    private ?bool $isWordPressProduction = null;

    public function __construct()
    {
        $this->isWordPressProduction = wp_get_environment_type() === 'production' ||
            !defined('WP_DEBUG') || WP_DEBUG === false;
    }

    /**
     * Process an exception and return a response
     *
     * @param Exception $e
     * @param string $responseClass
     * @return mixed
     * @throws InternalErrorException
     */
    public function processException(Exception $e, string $responseClass = RestResponseDto::class): mixed
    {
        $this->log($e->getMessage() . ' --> ' . $e->getTraceAsString(), 'ERROR');

        if (!class_exists($responseClass)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            /* translators: %s is the response class name */
            throw new InternalErrorException(sprintf(__('Response class does not exist: %s', 'beyond-seo'), $responseClass));
        }

        // Build base response data
        $data = [
            'message' => $this->getFirstErrorMessage($e),
            'error' => true
        ];

        // Add detailed information only in non-production environments
        if (!$this->isWordPressProduction) {
            if ($e instanceof InternalErrorException) {
                $data['content'] = $e->exceptionDetails?->getElements()[0]?->message ?? null;
            } else {
                $data['content'] = $e->getTraceAsString();
            }
            $data['trace'] = $e->getTraceAsString();
        }

        return new $responseClass($data, 500);
    }

    /**
     * Get the first error message from the exception
     *
     * @param Exception $e
     * @return string|null
     */
    private function getFirstErrorMessage(Exception $e): ?string
    {
        if ($e instanceof InternalErrorException
            && $e->getMessage() === __('Error API', 'beyond-seo')
            && $e->exceptionDetails?->getElements()[0]?->message) {
            try {
                $details = json_decode($e->exceptionDetails->getElements()[0]->message, false, 512, JSON_THROW_ON_ERROR);

                if (property_exists($details, 'error')
                    && !empty($details->error)) {
                    $errorMessage = $details->error;
                    return sprintf(__('%1$s', 'beyond-seo'), $errorMessage);
                }

                // Check if this is a validation error response
                if (property_exists($details, 'validationErrors') &&
                    property_exists($details->validationErrors, 'elements') &&
                    !empty($details->validationErrors->elements)) {

                    $error = $details->validationErrors->elements[0];

                    // Extract entity name from jsonPath
                    $entityPath = trim($error->jsonPath, '.');
                    $pathParts = explode('.', $entityPath);
                    $entityName = ucfirst($pathParts[0] ?? '');

                    // Extract field error details
                    if (isset($error->elements) && count($error->elements) > 0) {
                        $fieldError = $error->elements[0];
                        $propertyName = preg_replace('/\[\d+\]\./', '', $fieldError->propertyName);
                        $errorMessage = $fieldError->errorMessage;

                        /* translators: %1$s is the entity name, %2$s is the property name, %3$s is the error message */
                        return sprintf(__('%1$s > %2$s: %3$s', 'beyond-seo'), $entityName, $propertyName, $errorMessage);
                    }
                }
            } catch (JsonException $jsonEx) {
                return $e->getMessage();
            }
        }
        if ($e->getMessage() === 'Invalid API') {
            return __('There was a problem with the API request. Please try again later.', 'beyond-seo');
        }
        return $e->getMessage();
    }
}
