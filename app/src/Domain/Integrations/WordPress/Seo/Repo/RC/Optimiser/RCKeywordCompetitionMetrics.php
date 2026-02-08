<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Repo\RC\Optimiser;

use App\Domain\Base\Repo\RC\Attributes\RCLoad;
use App\Domain\Base\Repo\RC\Traits\RCTrait;
use App\Domain\Base\Repo\RC\Traits\RCSecurityTrait;
use App\Domain\Base\Repo\RC\Utils\RCCache;
use App\Domain\Common\Entities\Keywords\Keywords;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\AssignKeywords\KeywordCompetitionVolumeCheckOperation;
use App\Domain\Integrations\WordPress\Seo\Libs\ContentFetcher;
use DDD\Domain\Base\Entities\ValueObject;
use RankingCoach\Inc\Core\Plugin\RankingCoachPlugin;
use RankingCoach\Inc\Core\TokensManager;
use RankingCoach\Inc\Exceptions\HttpApiException;
use ReflectionException;

/**
 * Class KeywordCompetitionMetrics
 *
 * This class represents the keyword competition metrics for SEO analysis.
 * It extends the ValueObject class and can be used to encapsulate various metrics related to keyword competition.
 */
#[RCLoad(
    loadEndpoint: 'POST:{CUSTOM_FULL_DOMAIN}',
    cacheLevel: RCCache::CACHELEVEL_NONE,
    cacheTtl: RCCache::CACHE_TTL_TEN_MINUTES
)]
class RCKeywordCompetitionMetrics extends ValueObject
{
    use RCTrait;
    use RCSecurityTrait;

    /** Responses */
    public ?Keywords $keywords = null;
    public ?array $keyword_metrics = null;
    public ?string $raw_response = null;

    /** Metrics */
    public ?int $volume_metric = null;
    public ?int $cpc_metric = null;
    public ?int $difficulty_metric = null;

    /** Payloads */
    public ?string $primary_keyword = null;
    public ?array $secondary_keywords = null;
    public ?string $domainUrl = null;
    public ?string $contentCategory = null;
    public ?string $locale = null;
    public ?string $language = null;


    /**
     * Constructor for initializing the keyword competition metrics.
     */
    public function __construct()
    {
        parent::__construct();
        $this->keyword_metrics = [];
    }

    /**
     * Get the endpoint for loading the meta-tags factor
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
        $url = 'https://' . $prefix . '.rankingcoach.com/app/api/client/integrations/wordpress/seo/optimiser/keyword/competitionMetrics?debug=1&noCache=true';
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
            'keywords' => $this->keywords,
            'domain_url' => $this->domainUrl,
            'content_category' => $this->contentCategory,
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
            'timeout' => 10,
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
        /** @var KeywordCompetitionVolumeCheckOperation $parent */
        $parent = $this->getParent();
        if ($callResponseData) {
            $parent->metrics->keyword_metrics = (array)$callResponseData->metrics ?? null;
            $parent->metrics->raw_response = $callResponseData->rawResponse ?? null;
        }
    }
}
