<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Setup\Repo\RC\Onboarding;

use App\Domain\Base\Repo\RC\Attributes\RCLoad;
use App\Domain\Base\Repo\RC\Traits\RCSecurityTrait;
use App\Domain\Base\Repo\RC\Traits\RCTrait;
use App\Domain\Base\Repo\RC\Utils\RCApiOperation;
use App\Domain\Base\Repo\RC\Utils\RCCache;
use App\Domain\Integrations\WordPress\Setup\Entities\Extracts\WPSetupExtractAuto;
use RankingCoach\Inc\Core\Plugin\RankingCoachPlugin;
use RankingCoach\Inc\Core\TokensManager;
use RankingCoach\Inc\Exceptions\HttpApiException;
use ReflectionException;

/**
 * Class RCFlowDataCompletion
 */
#[RCLoad(
    loadEndpoint: 'POST:{CUSTOM_FULL_DOMAIN}',
    cacheLevel: RCCache::CACHELEVEL_NONE,
    cacheTtl: RCCache::CACHE_TTL_TEN_MINUTES
)]
class RCOnboardingExtractAuto extends WPSetupExtractAuto
{
    use RCTrait;
    use RCSecurityTrait;

    /**
     * Get the endpoint for loading the flow data completion
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
        return 'https://' . $prefix . '.rankingcoach.com/app/api/client/integrations/wordpress/onboarding/extractFromText?debug=1&noCache=true';
    }

    /**
     * @return array|null
     * @throws ReflectionException
     * @throws HttpApiException
     */
    protected function getLoadPayload(): ?array
    {
        /** @var WPSetupExtractAuto $parent */
        $parent = $this->toEntity();
        unset($parent->objectType);
        // Generate the base payload with common security data

        $basePayload = $this->generateSecurityPayload([
            'countryCode' => $parent->countryCode,
            'content' => $parent->content,
        ]);

        /** @var TokensManager $tokensManager */
        $tokensManager = TokensManager::instance();
        $token = $tokensManager->getAccessToken(static::class);

        // Prepare security headers
        $this->prepareSecurityHeaders($token, $basePayload);

        // Get security-enhanced payload structure
        $securityEnhancedPayload = $this->getSecurityEnhancedPayload([
            'timeout' => 10,
            'body' => $basePayload,
            'path' => [
                'CUSTOM_FULL_DOMAIN' => $this->getLoadEndpoint()
            ],
        ], $token);

        // Add Authorization header
        $securityEnhancedPayload['headers']['Authorization'] = 'Bearer ' . $token;

        $params = array_map(static fn($a) => $a, $securityEnhancedPayload);
        return $params;
    }

    /**
     * @param mixed|null $callResponseData
     * @param RCApiOperation|null $apiOperation
     * @return void
     * @throws \JsonException
     */
    public function handleLoadResponse(
        mixed &$callResponseData = null,?RCApiOperation &$apiOperation = null
    ): void {

        if($callResponseData) {
            /** @var WPSetupExtractAuto $data */
            $data = $callResponseData->extractedValues ?? null;
            if($data) {
                $prefilledAddress = $callResponseData->prefillCountryRelevantAddress ?? false;
                $this->setRequirements($data, $prefilledAddress);
                $this->setPrefillAddressRequirement($prefilledAddress);
            }
        }
        $this->postProcessLoadResponse($callResponseData);
    }
}
