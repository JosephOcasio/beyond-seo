<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Repo\RC;

use App\Domain\Base\Repo\RC\Attributes\RCLoad;
use App\Domain\Base\Repo\RC\Traits\RCTrait;
use App\Domain\Base\Repo\RC\Traits\RCSecurityTrait;
use App\Domain\Base\Repo\RC\Utils\RCApiOperation;
use App\Domain\Base\Repo\RC\Utils\RCCache;
use App\Domain\Common\Entities\Keywords\Keyword;
use App\Domain\Common\Entities\Keywords\Keywords;
use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\ContentAnalysis\Keywords\WPAdditionalKeywords;
use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\ContentAnalysis\Keywords\WPPrimaryKeyword;
use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\ContentAnalysis\WPContentAnalysis;
use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\ContentAnalysis\WPKeywordsAnalysis;
use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\WPWebPage;
use App\Domain\Integrations\WordPress\Seo\Libs\ContentFetcher;
use DDD\Domain\Base\Entities\Entity;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoad;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use RankingCoach\Inc\Core\Plugin\RankingCoachPlugin;
use RankingCoach\Inc\Core\TokensManager;
use RankingCoach\Inc\Exceptions\HttpApiException;
use ReflectionException;

/**
 * Class RCWebPageKeywordsAnalysis
 */
#[RCLoad(
    loadEndpoint: 'POST:{CUSTOM_FULL_DOMAIN}',
    cacheLevel: RCCache::CACHELEVEL_NONE,
    cacheTtl: RCCache::CACHE_TTL_TEN_MINUTES
)]
class RCWebPageKeywordsAnalysis extends WPKeywordsAnalysis
{
    use RCTrait;
    use RCSecurityTrait;

    /**
     * Get the endpoint for loading the keywords analysis
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
        $url = 'https://' . $prefix . '.rankingcoach.com/app/api/client/integrations/wordpress/getContentKeywordsAnalysis?debug=1&noCache=true';
        ContentFetcher::setUrlToCache($url);
        return $url;
    }

    /**
     * @param WPContentAnalysis $parent
     * @param LazyLoad $lazyloadAttributeInstance
     * @return Entity|null
     * @throws InternalErrorException
     * @throws ReflectionException
     */
    public function lazyload(
        WPContentAnalysis &$parent,
        LazyLoad          $lazyloadAttributeInstance
    ): ?WPKeywordsAnalysis {
        $this->setParent($parent);
        $this->rcLoad(
            useRCEntityCache: $lazyloadAttributeInstance->useCache,
            useApiACallCache: $lazyloadAttributeInstance->useCache
        );
        return $this->toEntity();
    }

    /**
     * @return array|null
     * @throws ReflectionException
     * @throws HttpApiException
     */
    protected function getLoadPayload(): ?array
    {
        /** @var WPContentAnalysis $parent */
        $parent = $this->getParent();

        /** @var WPWebPage $post */
        $post = $parent->post;
        unset($post->author);
        unset($post->site);

        // Generate the base payload with common security data
        $basePayload = $this->generateSecurityPayload([
            'webPage' => $post
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
     * @param RCApiOperation|null $apiOperation
     * @return void
     */
    public function handleLoadResponse (
        mixed &$callResponseData = null,
        RCApiOperation &$apiOperation = null
    ): void {
        $keywords = $callResponseData->keywords ?? null;
        if ($keywords) {
            $this->processKeywords($keywords);
        }
    }

    /**
     * Process the keyword list.
     *
     * @param mixed $proposedKeywords
     *
     * @return void
     */
    public function processKeywords(mixed $proposedKeywords): void {
        $this->processExistingKeywords($this->existingKeywords, $proposedKeywords->existingKeywords ?? null, Keyword::class);
        $this->processKeywordObject($this->primaryKeywordFromExisting, $proposedKeywords->matchPrimaryKeywordFromExisting ?? null);
        $this->processKeywordObject($this->primaryKeywordFromContent, $proposedKeywords->matchPrimaryKeywordFromContent ?? null);
        $this->processAdditionalKeywords($this->additionalKeywordsFromContent, $proposedKeywords->additionalKeywordsFromContent ?? null, Keyword::class);
        $this->processAdditionalKeywords($this->additionalKeywordsFromExisting, $proposedKeywords->additionalKeywordsFromExisting ?? null, Keyword::class);
    }

    /**
     * Process the keyword list.
     *
     * @param Keywords|null $keywords
     * @param mixed $proposedKeywords
     * @param string $className
     *
     * @return void
     */
    protected function processExistingKeywords(?Keywords &$keywords, mixed $proposedKeywords, string $className): void {
        if($proposedKeywords && get_class($proposedKeywords) && is_array($proposedKeywords->elements)) {
            $keywords = new Keywords();
            foreach ($proposedKeywords->elements as $proposedKeyword) {
                $keyword = new $className();
                $keyword->setName($proposedKeyword->name);
                $keywords->add($keyword);
            }
        }
    }

    /**
     * Process the keyword object.
     *
     * @param WPPrimaryKeyword|null $keyword
     * @param mixed $proposedKeyword
     *
     * @return void
     */
    protected function processKeywordObject(?WPPrimaryKeyword &$keyword, mixed $proposedKeyword): void {
        if($proposedKeyword && get_class($proposedKeyword)) {
            $keyword = new WPPrimaryKeyword(
                $proposedKeyword->relevance_score ?? null,
                $proposedKeyword->intent ?? null,
                $proposedKeyword->density ?? null,
            );
            $keyword->setName($proposedKeyword->name);
        }
    }

    /**
     * Process the keyword list.
     *
     * @param WPAdditionalKeywords|null $keywords
     * @param mixed $proposedKeywords
     * @param string $className
     *
     * @return void
     */
    protected function processAdditionalKeywords(?WPAdditionalKeywords &$keywords, mixed $proposedKeywords, string $className): void {
        if($proposedKeywords && get_class($proposedKeywords) && is_array($proposedKeywords->elements)) {
            $keywords = new WPAdditionalKeywords();
            foreach ($proposedKeywords->elements as $proposedKeyword) {
                $keyword = new $className();
                $keyword->setName($proposedKeyword->name);
                $keywords->add($keyword);
            }
        }
    }
}
