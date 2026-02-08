<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Repo\RC\Optimiser;

use App\Domain\Base\Repo\RC\Attributes\RCLoad;
use App\Domain\Base\Repo\RC\Traits\RCTrait;
use App\Domain\Base\Repo\RC\Traits\RCSecurityTrait;
use App\Domain\Base\Repo\RC\Utils\RCCache;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\SearchEngineIndexation\GoogleAndBingIndexationCheckOperation;
use App\Domain\Integrations\WordPress\Seo\Libs\ContentFetcher;
use DDD\Domain\Base\Entities\ValueObject;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\Plugin\RankingCoachPlugin;
use RankingCoach\Inc\Core\TokensManager;
use RankingCoach\Inc\Exceptions\HttpApiException;
use ReflectionException;

/**
 * Class RCWebPageUrlGoogleAndBingIndexation
 */
#[RCLoad(
    loadEndpoint: 'POST:{CUSTOM_FULL_DOMAIN}',
    cacheLevel: RCCache::CACHELEVEL_NONE,
    cacheTtl: RCCache::CACHE_TTL_TEN_MINUTES
)]
class RCWebPageUrlGoogleAndBingIndexation extends ValueObject
{
    use RCTrait;
    use RCSecurityTrait;
    use RcLoggerTrait;

    /** @var string $pageUrl Page URL */
    public string $pageUrl;

    /** Page URL indexation check results */
    public array $analyse = [];

    /**
     * Constructor initializing the indexation check structure.
     */
    public function __construct()
    {
        parent::__construct();
        $this->analyse = [];
    }

    /**
     * Get the endpoint for loading the user intent analysis
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
        $url = 'https://' . $prefix . '.rankingcoach.com/app/api/client/integrations/wordpress/seo/optimiser/webpage/isIndexed?debug=1&noCache=true';
        ContentFetcher::setUrlToCache($url);
        return $url;
    }


    /**
     * Get the load payload for the API request
     *
     * @return array|null
     * @throws HttpApiException
     * @throws ReflectionException
     */
    public function getLoadPayload(): ?array
    {
        // Generate the base payload with common security data
        $basePayload = $this->generateSecurityPayload([
            'pageUrl' => $this->pageUrl,
        ]);

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
     * Handle the API response and map it to the parent entity
     *
     * @param mixed|null $callResponseData
     * @return void
     */
    public function handleLoadResponse(mixed &$callResponseData = null): void
    {
        if ($callResponseData === null) {
            return;
        }
        /** @var GoogleAndBingIndexationCheckOperation $parent */
        $parent = $this->getParent();
        if ($callResponseData && isset($callResponseData->analyse)) {
            $parent->indexationChecker->analyse = (array)$callResponseData->analyse ?? [];
        }
    }
}
