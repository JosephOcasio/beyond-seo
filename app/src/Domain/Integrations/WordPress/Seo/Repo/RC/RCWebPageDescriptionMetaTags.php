<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Repo\RC;

use App\Domain\Base\Repo\RC\Attributes\RCLoad;
use App\Domain\Base\Repo\RC\Traits\RCTrait;
use App\Domain\Base\Repo\RC\Traits\RCSecurityTrait;
use App\Domain\Base\Repo\RC\Utils\RCApiOperation;
use App\Domain\Base\Repo\RC\Utils\RCCache;
use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\MetaTags\Tags\WPWebPageDescriptionMetaTag;
use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\WPWebPage;
use App\Domain\Integrations\WordPress\Seo\Libs\ContentFetcher;
use DDD\Domain\Base\Entities\Entity;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoad;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use RankingCoach\Inc\Core\Plugin\RankingCoachPlugin;
use RankingCoach\Inc\Core\TokensManager;
use RankingCoach\Inc\Exceptions\HttpApiException;
use RankingCoach\Inc\Modules\ModuleLibrary\Technical\MetaTags\MetaTags;
use ReflectionException;

/**
 * Class RCDescriptionMetaTags
 */
#[RCLoad(
    loadEndpoint: 'POST:{CUSTOM_FULL_DOMAIN}',
    cacheLevel: RCCache::CACHELEVEL_NONE,
    cacheTtl: RCCache::CACHE_TTL_TEN_MINUTES
)]
class RCWebPageDescriptionMetaTags extends WPWebPageDescriptionMetaTag
{
    use RCTrait;
    use RCSecurityTrait;

    /**
     * Get the endpoint for loading the description meta tags
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
        $url = 'https://' . $prefix . '.rankingcoach.com/app/api/client/integrations/wordpress/autogenerate?debug=1&noCache=true';
        ContentFetcher::setUrlToCache($url);
        return $url;
    }

    /**
     * @param WPWebPageDescriptionMetaTag $parent
     * @param LazyLoad $lazyloadAttributeInstance
     * @return Entity|null
     * @throws InternalErrorException
     * @throws ReflectionException
     */
    public function lazyload(
        WPWebPageDescriptionMetaTag &$parent,
        LazyLoad $lazyloadAttributeInstance
    ): ?WPWebPageDescriptionMetaTag {
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
        $post = $this->post;
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
    public function handleLoadResponse(
        mixed &$callResponseData = null,
        RCApiOperation &$apiOperation = null
    ): void {
        //echo json_encode($callResponseData); die;
        /** @var WPWebPage $responseObject */
        $responseObject = clone $callResponseData->webPage ?? null;
        if ($responseObject) {
            $filteredMeta = array_values(array_filter((array)$responseObject->postMeta?->elements ?? [], function($meta) {
                if($meta->metaKey == MetaTags::META_SEO_DESCRIPTION) {
                    return $meta;
                }
                return false;
            }));
            $this->content = $filteredMeta[0]->metaValue[0] ?? '';
        }

        $this->postProcessLoadResponse($responseObject);
    }
}
