<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Repo\RC\Optimiser;

use App\Domain\Base\Repo\RC\Attributes\RCLoad;
use App\Domain\Base\Repo\RC\Traits\RCTrait;
use App\Domain\Base\Repo\RC\Traits\RCSecurityTrait;
use App\Domain\Base\Repo\RC\Utils\RCCache;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\PageContentKeywords\ContentUpdateSuggestionsOperation;
use App\Domain\Integrations\WordPress\Seo\Libs\ContentFetcher;
use DDD\Domain\Base\Entities\ValueObject;
use RankingCoach\Inc\Core\Plugin\RankingCoachPlugin;
use RankingCoach\Inc\Core\TokensManager;
use RankingCoach\Inc\Exceptions\HttpApiException;
use ReflectionException;

/**
 * Class RCContentUpdateSuggestions
 */
#[RCLoad(
    loadEndpoint: 'POST:{CUSTOM_FULL_DOMAIN}',
    cacheLevel: RCCache::CACHELEVEL_NONE,
    cacheTtl: RCCache::CACHE_TTL_ONE_DAY
)]
class RCContentUpdateSuggestions extends ValueObject
{
    use RCTrait;
    use RCSecurityTrait;

    /** Responses */
    public ?array $recommendations = null;

    /** Category scores */
    public ?int $contentFreshnessScore = null;
    public ?int $industryChangesScore = null;
    public ?int $contentExpansionScore = null;
    public ?int $competitiveAdvantageScore = null;

    /** Payloads */
    public ?string $fullContent = null;
    public ?string $domainUrl = null;
    public ?string $contentCategory = null;
    public ?string $primaryKeyword = null;
    public ?string $publicationDate = null;
    public ?string $lastUpdated = null;
    public ?string $locale = null;
    public ?string $language = null;


    /**
     * Constructor for initializing the content update suggestions analysis.
     */
    public function __construct()
    {
        parent::__construct();
        $this->recommendations = [];
    }

    /**
     * Get the endpoint for loading the content update suggestions
     *
     * @return string|null
     */
    public function getLoadEndpoint(): string|null
    {
        $config = require RANKINGCOACH_PLUGIN_APP_DIR . 'config/app/externalIntegrations.php';
        // Set prefix based on production mode
        if (RankingCoachPlugin::isProductionMode()) {
            $prefix = $config['liveEnv'];
        } else {
            $prefix = get_option('testing_environment', $config['devEnv']);
        }
        $url = 'https://' . $prefix . '.rankingcoach.com/app/api/client/integrations/wordpress/seo/optimiser/content/updateSuggestions?debug=1&noCache=true';
        ContentFetcher::setUrlToCache($url);
        return $url;
    }

    /**
     * Get the load payload for the API request.
     * @throws HttpApiException
     * @throws ReflectionException
    */
    public function getLoadPayload(): ?array
    {
        // Generate the base payload with common security data
        $basePayload = $this->generateSecurityPayload([
            'fullContent' => $this->fullContent,
            'domainUrl' => $this->domainUrl,
            'contentCategory' => $this->contentCategory,
            'primaryKeyword' => $this->primaryKeyword,
            'publicationDate' => $this->publicationDate,
            'lastUpdated' => $this->lastUpdated,
            'locale' => $this->locale,
            'language' => $this->language,
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
     * @param mixed|null $callResponseData
     * @return void
     */
    public function handleLoadResponse(mixed &$callResponseData = null): void
    {
        /** @var ContentUpdateSuggestionsOperation $parent */
        $parent = $this->getParent();
        if ($callResponseData && isset($callResponseData->recommendations)) {
            $parent->contentAnalysis->recommendations = (array)$callResponseData->recommendations ?? [];
            
            // Store individual category scores if available
            if (isset($callResponseData->recommendations->contentFreshness->score)) {
                $parent->contentAnalysis->contentFreshnessScore = $callResponseData->recommendations->contentFreshness->score;
            }
            
            if (isset($callResponseData->recommendations->industryChanges->score)) {
                $parent->contentAnalysis->industryChangesScore = $callResponseData->recommendations->industryChanges->score;
            }
            
            if (isset($callResponseData->recommendations->contentExpansion->score)) {
                $parent->contentAnalysis->contentExpansionScore = $callResponseData->recommendations->contentExpansion->score;
            }
            
            if (isset($callResponseData->recommendations->competitiveAdvantage->score)) {
                $parent->contentAnalysis->competitiveAdvantageScore = $callResponseData->recommendations->competitiveAdvantage->score;
            }
        }
    }
}
