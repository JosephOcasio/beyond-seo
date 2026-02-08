<?php

declare(strict_types=1);

namespace App\Domain\Base\Repo\RC\Traits;

use RankingCoach\Inc\Core\Helpers\CoreHelper;
use RankingCoach\Inc\Core\TokensManager;
use RankingCoach\Inc\Exceptions\HttpApiException;
use ReflectionException;
use Throwable;

/**
 * Trait RCSecurityTrait
 * 
 * Provides security header functionality for RC-style classes.
 * Integrates with the consolidated security logic in CoreHelper.
 */
trait RCSecurityTrait
{
    /** @var array Security headers for API requests */
    protected array $securityHeaders = [];

    /**
     * Set security headers for the RC class.
     * 
     * @param array $headers Headers to set
     * @return void
     */
    public function setHeaders(array $headers): void
    {
        $this->securityHeaders = array_merge($this->securityHeaders, $headers);
    }

    /**
     * Get all security headers.
     * 
     * @return array
     */
    public function getSecurityHeaders(): array
    {
        return $this->securityHeaders;
    }

    /**
     * Prepare security headers using the consolidated CoreHelper logic.
     * 
     * @param string|null $accessToken The access token
     * @param array $userPayload The payload for signature generation
     * @return void
     * @throws HttpApiException
     * @throws ReflectionException
     */
    public function prepareSecurityHeaders(?string $accessToken = null, array $userPayload = []): void
    {
        // If no access token provided, try to get it from TokensManager
        if (!$accessToken) {
            /** @var TokensManager $tokensManager */
            $tokensManager = TokensManager::instance();
            $accessToken = $tokensManager->getAccessToken(static::class);
        }

        // Use the consolidated security header generation
        CoreHelper::setSecurityHeaders($this, $accessToken, $userPayload);
    }

    /**
     * Get the load payload with security headers integrated.
     * This method should be called from the implementing class's getLoadPayload method.
     * 
     * @param array $basePayload The base payload from the implementing class
     * @return array The payload with security headers integrated
     * @throws HttpApiException
     * @throws ReflectionException
     */
    protected function getSecurityEnhancedPayload(array $basePayload = [], ?string $token = null): array
    {
        // Prepare security headers if not already done
        if (empty($this->securityHeaders) && $token) {
            $this->prepareSecurityHeaders($token, $basePayload);
        }

        // Merge security headers into the request headers
        $headers = array_merge([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ], $this->securityHeaders);

        // Return the enhanced payload structure
        return array_merge($basePayload, [
            'headers' => $headers,
            'verify' => (wp_get_environment_type() === 'production'),
        ]);
    }

    /**
     * Generate common security payload and merge with additional data.
     * Convenience method that combines CoreHelper functionality.
     * 
     * @param array $additionalData Additional data to merge
     * @return array Combined security payload
     * @throws Throwable
     */
    protected function generateSecurityPayload(array $additionalData = []): array
    {
        return CoreHelper::generateCommonSecurityPayload($additionalData);
    }
}