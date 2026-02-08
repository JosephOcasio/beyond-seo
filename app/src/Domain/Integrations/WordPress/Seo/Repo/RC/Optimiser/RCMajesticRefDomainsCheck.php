<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Repo\RC\Optimiser;

use App\Domain\Base\Repo\RC\Attributes\RCLoad;
use App\Domain\Base\Repo\RC\Traits\RCTrait;
use App\Domain\Base\Repo\RC\Traits\RCSecurityTrait;
use App\Domain\Base\Repo\RC\Utils\RCCache;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\AnalyzeBacklinkProfile\ReferringDomainsAnalysisOperation;
use App\Domain\Integrations\WordPress\Seo\Entities\WPSeoMajesticRefDomainItem;
use App\Domain\Integrations\WordPress\Seo\Entities\WPSeoMajesticRefDomainItems;
use App\Domain\Integrations\WordPress\Seo\Libs\ContentFetcher;
use DDD\Domain\Base\Entities\ValueObject;
use DDD\Infrastructure\Validation\Constraints\Choice;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\Plugin\RankingCoachPlugin;
use RankingCoach\Inc\Core\TokensManager;
use RankingCoach\Inc\Exceptions\HttpApiException;
use ReflectionException;

/**
 * Class RCMajesticRefDomainsCheck
 * 
 * This class is responsible for fetching referring domains data from Majestic API.
 */
#[RCLoad(
    loadEndpoint: 'POST:{CUSTOM_FULL_DOMAIN}',
    cacheLevel: RCCache::CACHELEVEL_NONE,
    cacheTtl: RCCache::CACHE_TTL_TEN_MINUTES
)]
class RCMajesticRefDomainsCheck extends ValueObject
{
    use RCTrait;
    use RCSecurityTrait;
    use RcLoggerTrait;

    public const DATASOURCE_FRESH = 'fresh';
    public const DATASOURCE_HISTORIC = 'historic';

    /** @var array $urls URLs to analyze */
    public array $urls = [];

    /** @var int $count Number of results to return */
    public int $count = 50;

    /** @var string $datasource Data source to use */
    #[Choice(choices: [self::DATASOURCE_FRESH, self::DATASOURCE_HISTORIC])]
    public string $datasource = self::DATASOURCE_FRESH;

    /** @var int $analysisDepth Analysis depth */
    public int $analysisDepth = 0;

    /** @var int $minMatchesRequired Minimum matches required */
    public int $minMatchesRequired = 1;

    /** @var int $orderBy1 Primary ordering field */
    public int $orderBy1 = 0;

    /** @var int $orderBy2 Secondary ordering field */
    public int $orderBy2 = 0;

    /** @var int $orderDir1 Ordering direction */
    public int $orderDir1 = 0;

    /** @var WPSeoMajesticRefDomainItems $refDomains Referring domains collection */
    public WPSeoMajesticRefDomainItems $refDomains;

    /**
     * Constructor initializing the referring domains collection.
     */
    public function __construct()
    {
        parent::__construct();
        $this->refDomains = new WPSeoMajesticRefDomainItems();
    }

    /**
     * Get the endpoint for loading the Majestic referring domains data
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
        $url = 'https://' . $prefix . '.rankingcoach.com/app/api/client/integrations/wordpress/seo/optimiser/majestic/refDomains?debug=1&noCache=true';
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
            'urls' => $this->urls,
            'count' => $this->count,
            'datasource' => $this->datasource,
            'analysisDepth' => $this->analysisDepth,
            'minMatchesRequired' => $this->minMatchesRequired,
            'orderBy1' => $this->orderBy1,
            'orderBy2' => $this->orderBy2,
            'orderDir1' => $this->orderDir1
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

        /** @var ReferringDomainsAnalysisOperation $parent */
        $parent = $this->getParent();
        
        if ($callResponseData && isset($callResponseData->refDomains->elements) && is_array($callResponseData->refDomains->elements)) {
            foreach ($callResponseData->refDomains->elements as $refDomainData) {
                $refDomainItem = new WPSeoMajesticRefDomainItem();
                
                // Map properties from API response to the item
                foreach (get_object_vars($refDomainData) as $property => $value) {
                    if (property_exists($refDomainItem, $property)) {
                        $refDomainItem->{$property} = $value;
                    }
                }
                
                $this->refDomains->add($refDomainItem);
            }
            
            // Assign the collection to the parent operation
            if (property_exists($parent, 'majesticRefDomains')) {
                $parent->majesticRefDomains = $this->refDomains;
            }
        }
    }
}
