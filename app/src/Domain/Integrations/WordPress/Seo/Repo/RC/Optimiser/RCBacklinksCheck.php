<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Repo\RC\Optimiser;

use App\Domain\Base\Repo\RC\Attributes\RCLoad;
use App\Domain\Base\Repo\RC\Traits\RCSecurityTrait;
use App\Domain\Base\Repo\RC\Traits\RCTrait;
use App\Domain\Base\Repo\RC\Utils\RCCache;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\AnalyzeBacklinkProfile\ReferringLinksQualityAssessmentOperation;
use App\Domain\Integrations\WordPress\Seo\Entities\WPSeoBacklinksItem;
use App\Domain\Integrations\WordPress\Seo\Entities\WPSeoBacklinksItems;
use App\Domain\Integrations\WordPress\Seo\Libs\ContentFetcher;
use DDD\Domain\Base\Entities\ValueObject;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\Plugin\RankingCoachPlugin;
use RankingCoach\Inc\Core\TokensManager;
use RankingCoach\Inc\Exceptions\HttpApiException;
use ReflectionException;

/**
 * Class RCBacklinksCheck
 * 
 * This class is responsible for fetching individual backlinks data from the API.
 */
#[RCLoad(
    loadEndpoint: 'POST:{CUSTOM_FULL_DOMAIN}',
    cacheLevel: RCCache::CACHELEVEL_NONE,
    cacheTtl: RCCache::CACHE_TTL_TEN_MINUTES
)]
class RCBacklinksCheck extends ValueObject
{
    use RCTrait;
    use RcLoggerTrait;
    use RCSecurityTrait;

    /** @var string $url Site URL to analyze */
    public string $url = '';
    
    /** @var string $from Source URL for backlinks */
    public string $from = '';
    
    /** @var int $count Number of results to return */
    public int $count = 50;
    
    /** @var WPSeoBacklinksItems $backlinks Backlinks collection */
    public WPSeoBacklinksItems $backlinks;

    /**
     * Constructor initializing the backlinks collection.
     */
    public function __construct()
    {
        parent::__construct();
        $this->backlinks = new WPSeoBacklinksItems();
    }

    /**
     * Get the endpoint for loading the backlinks data
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
        $url = 'https://' . $prefix . '.rankingcoach.com/app/api/client/integrations/wordpress/seo/optimiser/webpage/backlinks?debug=1&noCache=true';
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
            'url' => $this->url,
            'count' => $this->count
        ]);
        
        // Add from parameter only if it's set
        if (!empty($this->from)) {
            $basePayload['from'] = $this->from;
        }

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

        /** @var ReferringLinksQualityAssessmentOperation $parent */
        $parent = $this->getParent();
        
        if ($callResponseData && isset($callResponseData->backlinks->elements) && is_array($callResponseData->backlinks->elements)) {
            foreach ($callResponseData->backlinks->elements as $backlinkData) {
                $backlinkItem = new WPSeoBacklinksItem();
                
                // Map properties from API response to the item
                foreach (get_object_vars($backlinkData) as $property => $value) {
                    if (property_exists($backlinkItem, $property)) {
                        $backlinkItem->{$property} = $value;
                    }
                }
                
                // Set the 'from' property if it was provided in the request
                if (!empty($this->from)) {
                    $backlinkItem->from = $this->from;
                }
                
                $this->backlinks->add($backlinkItem);
            }
            
            // Assign the collection to the parent operation
            if (property_exists($parent, 'backlinks')) {
                $parent->backlinks = $this->backlinks;
            }
        }
    }
}
