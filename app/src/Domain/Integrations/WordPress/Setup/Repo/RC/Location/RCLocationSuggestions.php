<?php

declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Setup\Repo\RC\Location;

use App\Domain\Base\Repo\RC\Attributes\RCLoad;
use App\Domain\Base\Repo\RC\Traits\RCTrait;
use App\Domain\Base\Repo\RC\Traits\RCSecurityTrait;
use App\Domain\Base\Repo\RC\Utils\RCApiOperation;
use App\Domain\Base\Repo\RC\Utils\RCCache;
use DDD\Domain\Base\Entities\ValueObject;
use JsonException;
use RankingCoach\Inc\Core\Plugin\RankingCoachPlugin;
use RankingCoach\Inc\Core\TokensManager;
use RankingCoach\Inc\Exceptions\HttpApiException;
use ReflectionException;

/**
 * Class RCLocationSuggestions
 */
#[RCLoad(
    loadEndpoint: 'POST:{CUSTOM_FULL_DOMAIN}',
    cacheLevel: RCCache::CACHELEVEL_NONE,
    cacheTtl: RCCache::CACHE_TTL_TEN_MINUTES
)]
class RCLocationSuggestions extends ValueObject
{
    use RCTrait;
    use RCSecurityTrait;

    /** @var string $address */
    public string $address;

    /** @var string $countryShortCode */
    public string $countryShortCode;

    /** @var string|null $city */
    public ?string $city = null;

    /** @var string|null $zip */
    public ?string $zip = null;

    /** @var string|null $language */
    public ?string $language = null;

    /** @var bool $allowAnyLocationType */
    public bool $allowAnyLocationType = false;

    /** @var array|null $businessLocationMatches */
    public ?array $businessLocationMatches = null;

    /** @var bool $success */
    public bool $success = false;

    /** @var string|null $message */
    public ?string $message = null;

    /**
     * Get the endpoint for loading location suggestions
     *
     * @return string|null
     */
    public function getLoadEndpoint(): ?string
    {
        $config = require RANKINGCOACH_PLUGIN_APP_DIR . 'config/app/externalIntegrations.php';
        // Set prefix based on production mode
        if (RankingCoachPlugin::isProductionMode()) {
            $prefix = $config['liveEnv'];
        } else {
            $prefix = get_option('testing_environment', $config['devEnv']);
        }
        $url = 'https://' . $prefix . '.rankingcoach.com/app/api/client/integrations/wordpress/location/suggestions?debug=1&noCache=true';
        return $url;
    }

    /**
     * @return array|null
     * @throws ReflectionException
     * @throws JsonException
     * @throws HttpApiException
     */
    protected function getLoadPayload(): ?array
    {
        $payload = [
            'address' => $this->address,
            'countryShortCode' => $this->countryShortCode,
            'allowAnyLocationType' => $this->allowAnyLocationType,
        ];

        if ($this->city) {
            $payload['city'] = $this->city;
        }

        if ($this->zip) {
            $payload['zip'] = $this->zip;
        }

        if ($this->language) {
            $payload['language'] = $this->language;
        }

        // Generate the base payload with common security data
        $basePayload = $this->generateSecurityPayload($payload);

        /** @var TokensManager $tokensManager */
        $tokensManager = TokensManager::instance();
        $token = $tokensManager->getAccessToken(static::class);

        // Prepare security headers
        $this->prepareSecurityHeaders($token, $basePayload);

        // Get security-enhanced payload structure
        $securityEnhancedPayload = $this->getSecurityEnhancedPayload([
            'timeout' => 15,
            'body' => $basePayload,
            'path' => [
                'CUSTOM_FULL_DOMAIN' => $this->getLoadEndpoint()
            ],
        ], $token);

        // Add Authorization header
        $securityEnhancedPayload['headers']['Authorization'] = 'Bearer ' . $token;

        return array_map(fn($a) => $a, $securityEnhancedPayload);
    }

    /**
     * @param mixed|null $callResponseData
     * @param RCApiOperation|null $apiOperation
     * @return void
     */
    public function handleLoadResponse(
        mixed &$callResponseData = null,
        RCApiOperation &$apiOperation = null
    ): void {
        if ($callResponseData) {
            $this->businessLocationMatches = (array)$callResponseData->businessLocationMatches ?? null;
            $this->success = $callResponseData->success ?? false;
            $this->message = $callResponseData->message ?? null;
            $this->postProcessLoadResponse($callResponseData);
        }
    }
}
