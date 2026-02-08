<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Repo\RC\Optimiser;

use App\Domain\Base\Repo\RC\Attributes\RCLoad;
use App\Domain\Base\Repo\RC\Traits\RCTrait;
use App\Domain\Base\Repo\RC\Traits\RCSecurityTrait;
use App\Domain\Base\Repo\RC\Utils\RCCache;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\FirstParagraphKeywordUsage\OpeningParagraphEngagementAnalysisOperation;
use App\Domain\Integrations\WordPress\Seo\Libs\ContentFetcher;
use DDD\Domain\Base\Entities\ValueObject;
use RankingCoach\Inc\Core\Plugin\RankingCoachPlugin;
use RankingCoach\Inc\Core\TokensManager;
use RankingCoach\Inc\Exceptions\HttpApiException;
use ReflectionException;

/**
 * Class RCAnalyzeOpeningParagraphEngagement
 */
#[RCLoad(
    loadEndpoint: 'POST:{CUSTOM_FULL_DOMAIN}',
    cacheLevel: RCCache::CACHELEVEL_NONE,
    cacheTtl: RCCache::CACHE_TTL_ONE_DAY
)]
class RCAnalyzeOpeningParagraphEngagement extends ValueObject
{
    use RCTrait;
    use RCSecurityTrait;

    /*
     * Short description about payloads:
     *  - Normalizes the text before sending (ex: no HTML tags, no inline JS, no footer text, etc.).
     *  - Shortens the full content to a maximum number of characters if there is API limit risk.
     *  - Sends the page URL in payload if the server needs complete context.
     */

    /** User Engagement Analyses Results */
    public ?array $analyse = null;

    /** Payload fields */
    public ?string $domainUrl = null;
    public ?string $locale = null;
    public ?string $language = null;
    public ?string $primaryKeyword = null;
    public array $secondaryKeywords = [];
    public ?string $pageTitle = null;
    public ?string $metaDescription = null;
    public ?string $firstParagraph = null;
    public array $headings = [];
    public ?string $fullContent = null;

    /**
     * Constructor initializing the engagement analysis structure
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
        $url = 'https://' . $prefix . '.rankingcoach.com/app/api/client/integrations/wordpress/seo/optimiser/content/engagementCheck?debug=1&noCache=true';
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
            'primaryKeyword' => $this->primaryKeyword,
            'secondaryKeywords' => json_encode($this->secondaryKeywords ?? []),
            'pageTitle' => $this->pageTitle,
            'metaDescription' => $this->metaDescription,
            'firstParagraph' => $this->firstParagraph,
            'headings' => json_encode($this->headings ?? []),
            'fullContent' => $this->fullContent,
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
        /** @var OpeningParagraphEngagementAnalysisOperation $parent */
        $parent = $this->getParent();
        if ($callResponseData && isset($callResponseData->analyse)) {
            $parent->engagementAnalyze->analyse = (array)$callResponseData->analyse ?? [];
        }
    }
}
