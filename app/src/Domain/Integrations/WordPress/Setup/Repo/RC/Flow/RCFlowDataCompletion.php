<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Setup\Repo\RC\Flow;

use App\Domain\Base\Repo\RC\Attributes\RCLoad;
use App\Domain\Base\Repo\RC\Traits\RCTrait;
use App\Domain\Base\Repo\RC\Traits\RCSecurityTrait;
use App\Domain\Base\Repo\RC\Utils\RCApiOperation;
use App\Domain\Base\Repo\RC\Utils\RCCache;
use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Completions\WPFlowDataCompletion;
use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Completions\WPFlowEvaluateData;
use App\Domain\Integrations\WordPress\Seo\Libs\ContentFetcher;
use Exception;
use RankingCoach\Inc\Core\AutoSetup\Onboarding\AutoSetupOnboarding;
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
class RCFlowDataCompletion extends WPFlowDataCompletion
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
        $url = 'https://' . $prefix . '.rankingcoach.com/app/api/client/integrations/wordpress/onboarding/answers/aiValidation?debug=1&noCache=true';
        ContentFetcher::setUrlToCache($url);
        return $url;
    }

    /**
     * @return array|null
     * @throws ReflectionException
     * @throws HttpApiException
     * @throws Exception
     */
    protected function getLoadPayload(): ?array
    {
        /** @var WPFlowDataCompletion $entity */
        $entity = $this->toEntity();
        $payloadData = (array) $entity->data;
        unset($payloadData['objectType']);
        // Generate the base payload with common security data

        // Instantiate the onboarding class
        $onboarding = new AutoSetupOnboarding();
        // Get the onboarding content
        $websiteGeneralDescription = $onboarding->getOnboardingContent(true) ?? '';

        $basePayload = $this->generateSecurityPayload([
            'dataForEvaluation' => (object) $payloadData,
            'websiteGeneralDescription' => $websiteGeneralDescription,
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
     */
    public function handleLoadResponse(
        mixed &$callResponseData = null,
        RCApiOperation &$apiOperation = null
    ): void {
        if($callResponseData) {
            /** @var WPFlowEvaluateData $evaluatedData */
            $evaluatedData = $callResponseData->evaluatedData ?? null;
            if(!empty($evaluatedData) && strlen($evaluatedData->evaluationRawAIResult ?? '')) {
                $this->data = new WPFlowEvaluateData(
                    true,
                    $evaluatedData->evaluationResult ?? false,
                    $evaluatedData->evaluationFeedback ?? '',
                    $evaluatedData->evaluationRawAIResult ?? '',
                    $evaluatedData->evaluationRawAIPrompt ?? '',
                    (array)$evaluatedData->metadata ?? []
                );
            }

            $this->postProcessLoadResponse($callResponseData);
        }
    }
}
