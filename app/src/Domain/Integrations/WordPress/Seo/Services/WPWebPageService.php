<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Services;

use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\ContentAnalysis\Keywords\WPKeyword;
use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\ContentAnalysis\Keywords\WPKeywords;
use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\MetaTags\Social\WPWebPageSocialDescriptionMetaTag;
use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\MetaTags\Social\WPWebPageSocialTitleMetaTag;
use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\MetaTags\Tags\WPWebPageDescriptionMetaTag;
use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\MetaTags\Tags\WPWebPageKeywordsMetaTag;
use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\MetaTags\Tags\WPWebPageTitleMetaTag;
use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\MetaTags\WPWebPageMetaTag;
use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\MetaTags\WPWebPageMetaTags;
use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Types\WPPages;
use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Types\WPPosts;
use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\WPWebPage;
use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\WPWebPages;
use App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\WebPages\InternalDBWPKeyword;
use App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\WebPages\InternalDBWPWebPage;
use App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\WebPages\InternalDBWPWebPageMetaTagDescription;
use App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\WebPages\InternalDBWPWebPageMetaTagKeyword;
use App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\WebPages\InternalDBWPWebPageMetaTagTitle;
use App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\WebPages\InternalDBWPWebPages;
use App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\WebPages\InternalDBWPWebPageSocialMetaTagDescription;
use App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\WebPages\InternalDBWPWebPageSocialMetaTagTitle;
use App\Presentation\Api\Client\Integrations\WordPress\Dtos\AdvancedSettingsMetaTagsPostRequestDto;
use App\Presentation\Api\Client\Integrations\WordPress\Dtos\KeywordsMetaTagsKeywordRequestDto;
use App\Presentation\Api\Client\Integrations\WordPress\Dtos\MetaTagsPostRequestDto;
use App\Presentation\Api\Client\Integrations\WordPress\Dtos\MetaTagsRequestDto;
use App\Presentation\Api\Client\Integrations\WordPress\Dtos\SocialMetaTagsPostRequestDto;
use DDD\Domain\Base\Entities\Entity;
use DDD\Domain\Base\Services\EntitiesService;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\ForbiddenException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use Doctrine\ORM\Mapping\MappingException;
use Exception;
use JsonException;
use Psr\Cache\InvalidArgumentException;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\Helpers\SocialMediaHelper;
use RankingCoach\Inc\Modules\ModuleLibrary\Technical\MetaTags\MetaTags;
use RankingCoach\Inc\Modules\ModuleManager;
use ReflectionException;
use Throwable;

/**
 * Service for WPWebPageService entities.
 *
 * @method static WPWebPage getEntityClassInstance()
 */
class WPWebPageService extends EntitiesService
{
    use RcLoggerTrait;

    /** @var string DEFAULT_ENTITY_CLASS The default entity class. */
    public const DEFAULT_ENTITY_CLASS = WPWebPage::class;

    /**
     * Retrieves a WPWebPage entity by its ID.
     *
     * @param int $postId The ID of the content to retrieve.
     * @return WPWebPage|null The WPWebPage entity.
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws MappingException
     * @throws Exception
     * @noinspection PhpExpressionResultUnusedInspection
     */
    public function getWebPageById(int $postId): ?WPWebPage
    {
        $repo = new InternalDBWPWebPage();
        $entity = $repo->find($postId);

        // TODO by Romeo: this throw an error if is enabled
        $entity->author;
//        if($entity->authorId) {
//            $wpAccountService = new WPAccountService();
//            $entity->author = $wpAccountService->getUserWithMeta($entity->authorId);
//        }
        $entity->postMeta;
        //$entity->site;

        return $entity;
    }

    /**
     * Retrieves a WPWebPage entity by its type.
     *
     * @param string $type The type of content to retrieve.
     * @return WPWebPages The WPWebPage entity.
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws MappingException
     * @throws Exception
     */
    private function getWebPagesByType(string $type): WPWebPages
    {
        $repo = new InternalDBWPWebPages();
        $result = $repo->findWebPagesByType($type);
        $entitySet = new WPWebPages();
        if(count($result) > 0) {
            foreach ($result as $item) {
                $repoOne = new InternalDBWPWebPage();
                $entity = $repoOne->find($item->id);
                $entitySet->add($entity);
            }
        }
        return $entitySet;
    }

    /** Retrieves a WPPages entities of type page.
     *
     * @return WPPages The WPPages entity.
     * @throws BadRequestException
     * @throws Exception
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws MappingException
     * @throws ReflectionException
     */
    public function getPages(): WPWebPages
    {
        return $this->getWebPagesByType(WPWebPage::CONTENT_TYPE_PAGE);
    }

    /**
     * Retrieves a WPPosts entities of type post.
     *
     * @return WPPosts The WPPosts entity.
     * @throws BadRequestException
     * @throws Exception
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws MappingException
     * @throws ReflectionException
     */
    public function getPosts(): WPWebPages
    {
        return $this->getWebPagesByType(WPWebPage::CONTENT_TYPE_POST);
    }

    /**
     * Retrieves a WPWebPage entity of type attachment.
     *
     * @return WPWebPages The WPWebPage entity.
     * @throws BadRequestException
     * @throws Exception
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws MappingException
     * @throws ReflectionException
     */
    public function getAttachments(): WPWebPages
    {
        return $this->getWebPagesByType(WPWebPage::CONTENT_TYPE_ATTACHMENT);
    }

    /**
     * Retrieves a WPWebPage entity of type revision.
     *
     * @return WPWebPages The WPWebPage entity.
     * @throws BadRequestException
     * @throws Exception
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws MappingException
     * @throws ReflectionException
     */
    public function getRevisions(): WPWebPages
    {
        return $this->getWebPagesByType(WPWebPage::CONTENT_TYPE_REVISION);
    }

    /**
     * Retrieves a WPWebPage entity of type navigation menu item.
     *
     * @return WPWebPages The WPWebPage entity.
     * @throws BadRequestException
     * @throws Exception
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws MappingException
     * @throws ReflectionException
     */
    public function getNavigationMenuItems(): WPWebPages
    {
        return $this->getWebPagesByType(WPWebPage::CONTENT_TYPE_NAVIGATION_MENU_ITEM);
    }

    /**
     * Retrieves a WPWebPageMetaTag entity by its ID.
     *
     * @param int $postId The ID of the post to retrieve.
     * @param string $type The type of the meta-tag to retrieve.
     *
     * @return WPWebPageMetaTag|null The WPWebPageMetaTag entity.
     * @throws InvalidArgumentException
     */
    public function getMetaTagByTypeAndId(int $postId, string $type): ?WPWebPageMetaTag {

        try {
            $repo = match ($type) {
                WPWebPageMetaTag::TAG_TYPE_TITLE => new InternalDBWPWebPageMetaTagTitle(),
                WPWebPageMetaTag::TAG_TYPE_SOCIAL_TITLE => new InternalDBWPWebPageSocialMetaTagTitle(),
                WPWebPageMetaTag::TAG_TYPE_DESCRIPTION => new InternalDBWPWebPageMetaTagDescription(),
                WPWebPageMetaTag::TAG_TYPE_SOCIAL_DESCRIPTION => new InternalDBWPWebPageSocialMetaTagDescription(),
                WPWebPageMetaTag::TAG_TYPE_KEYWORDS => new InternalDBWPWebPageMetaTagKeyword()
            };
            return $repo->getMetaTagByTypeAndId($postId, $type);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * @param Entity $entity
     * @param int $depth
     *
     * @return Entity
     * @throws Throwable
     */
    public function update(Entity $entity, int $depth = 1): Entity
    {
        if($entity instanceof WPWebPageTitleMetaTag) {
            $repo = new InternalDBWPWebPageMetaTagTitle();
        }
        elseif($entity instanceof WPWebPageDescriptionMetaTag) {
            $repo = new InternalDBWPWebPageMetaTagDescription();
        }
        elseif($entity instanceof WPWebPageKeywordsMetaTag) {
            $repo = new InternalDBWPWebPageMetaTagKeyword();
        }
        elseif($entity instanceof WPWebPageSocialTitleMetaTag) {
            $repo = new InternalDBWPWebPageSocialMetaTagTitle();
        }
        elseif($entity instanceof WPWebPageSocialDescriptionMetaTag) {
            $repo = new InternalDBWPWebPageSocialMetaTagDescription();
        }
        else {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new Exception(__('Invalid entity type on WebPageService update', 'beyond-seo'));
        }
        return $repo->update($entity, $depth);
    }

    /**
     * Updates the title and description of a post.
     *
     * @param array $payload The payload data.
     *
     * @param int $postId
     * @throws Throwable
     */
    public function updateMetaTags(array &$payload, int $postId): void {

        $metaTagKeys = [
            'title' => MetaTags::META_SEO_TITLE,
            'social_title' => MetaTags::META_SOCIAL_TITLE,
            'description' => MetaTags::META_SEO_DESCRIPTION,
            'social_description' => MetaTags::META_SOCIAL_DESCRIPTION,
            'keywords' => BaseConstants::META_KEY_SEO_KEYWORDS,
            'noindexForPage' => MetaTags::META_NOINDEX_FOR_PAGE,
            'excludeSitemapForPage' => MetaTags::META_EXCLUDE_SITEMAP_FOR_PAGE,
            'disableAutoLinks' => MetaTags::META_DISABLE_AUTO_LINKS,
            'canonicalUrl' => MetaTags::META_CANONICAL_URL,
            'viewportForPage' => MetaTags::META_VIEWPORT_FOR_PAGE,
        ];

        // Prepare meta payload for batch save
        $metaPayload = [];
        $additionalMeta = [];

        foreach ([
            'title' => WPWebPageTitleMetaTag::class,
            'social_title' => WPWebPageSocialTitleMetaTag::class,
            'description' => WPWebPageDescriptionMetaTag::class,
            'social_description' => WPWebPageSocialDescriptionMetaTag::class,
            'keywords' => WPWebPageKeywordsMetaTag::class
        ] as $key => $metaClass) {
            if (!isset($payload[$key]) || !$payload[$key] || (!is_array($payload[$key]) && !is_object($payload[$key]))) {
                continue;
            }

            /** @var WPWebPageTitleMetaTag|WPWebPageDescriptionMetaTag|WPWebPageKeywordsMetaTag|WPWebPageSocialTitleMetaTag|WPWebPageSocialDescriptionMetaTag $entity */
            $entity = $payload[$key];
            $entity->postId = $postId;
            
            // Parse content based on template if available
            $contentParsed = $entity->content;
            if(method_exists($entity, 'parseByTemplate') && $metaTagKeys[$key] !== MetaTags::META_SOCIAL_TITLE) {
                $contentParsed = $entity->parseByTemplate();
            }
            
            if ($entity instanceof WPWebPageMetaTag) {
                // Update entity in database
                $payload[$key] = $this->update($entity);
                
                // Prepare data for MetaTags::saveMetaData()
                $metaPayload[$key] = $contentParsed;
                
                // Handle special case for SEO title pre-parsing
                if($metaTagKeys[$key] === MetaTags::META_SEO_TITLE) {
                    $additionalMeta[MetaTags::META_SEO_TITLE] = $entity->parseByTemplate();
                }
                
                // Store template variables and template separately
                $additionalMeta[$metaTagKeys[$key] . '_variables'] = json_encode($entity->variables, JSON_THROW_ON_ERROR);
                $additionalMeta[$metaTagKeys[$key] . '_template'] = $entity->template;
            }
        }

        // Handle advanced settings (noindexForPage and canonicalUrl)
        if (isset($payload['noindexForPage'])) {
            $metaPayload['noindexForPage'] = $payload['noindexForPage'];
        }

        if (isset($payload['excludeSitemapForPage'])) {
            $metaPayload['excludeSitemapForPage'] = $payload['excludeSitemapForPage'];
        }

        if (isset($payload['disableAutoLinks'])) {
            $metaPayload['disableAutoLinks'] = $payload['disableAutoLinks'];
        }
        
        if (isset($payload['canonicalUrl'])) {
            $metaPayload['canonicalUrl'] = $payload['canonicalUrl'];
        }
        
        // Batch save all meta tags through MetaTags service
        if (!empty($metaPayload)) {
            try {
                $saveResult = ModuleManager::instance()->initialize()->metaTags()->saveMetaData($postId, $metaPayload);
                
                if (!$saveResult) {
                    throw new InternalErrorException(__('Failed to save meta tags through MetaTags service', 'beyond-seo'));
                }
                
                // Save additional meta data that's not handled by MetaTags service
                foreach ($additionalMeta as $metaKey => $metaValue) {
                    update_post_meta($postId, $metaKey, $metaValue);
                }
                
                // Save advanced settings to post meta directly
                if (isset($payload['noindexForPage'])) {
                    update_post_meta($postId, MetaTags::META_NOINDEX_FOR_PAGE, $payload['noindexForPage']);
                }

                if (isset($payload['excludeSitemapForPage'])) {
                    update_post_meta($postId, MetaTags::META_EXCLUDE_SITEMAP_FOR_PAGE, $payload['excludeSitemapForPage']);
                }

                if (isset($payload['disableAutoLinks'])) {
                    update_post_meta($postId, MetaTags::META_DISABLE_AUTO_LINKS, $payload['disableAutoLinks']);
                }
                
                if (isset($payload['canonicalUrl'])) {
                    update_post_meta($postId, MetaTags::META_CANONICAL_URL, $payload['canonicalUrl']);
                }

                if (isset($payload['viewportForPage'])) {
                    update_post_meta($postId, MetaTags::META_VIEWPORT_FOR_PAGE, $payload['viewportForPage']);
                }

            } catch (Exception $e) {
                // Log error and fallback to individual saves
                $this->fallbackToIndividualSaves($postId, $payload, $metaTagKeys, $additionalMeta);
            }
        }
    }

    /**
     * Fallback method for individual meta saves when batch save fails
     * 
     * @param int $postId
     * @param array $payload
     * @param array $metaTagKeys
     * @param array $additionalMeta
     * @return void
     */
    private function fallbackToIndividualSaves(int $postId, array $payload, array $metaTagKeys, array $additionalMeta): void
    {
        foreach ([
            'title' => WPWebPageTitleMetaTag::class,
            'social_title' => WPWebPageSocialTitleMetaTag::class,
            'description' => WPWebPageDescriptionMetaTag::class,
            'social_description' => WPWebPageSocialDescriptionMetaTag::class,
            'keywords' => WPWebPageKeywordsMetaTag::class
        ] as $key => $metaClass) {
            if (!isset($payload[$key]) || !($payload[$key] instanceof WPWebPageMetaTag)) {
                continue;
            }

            /** @var WPWebPageMetaTag $entity */
            $entity = $payload[$key];
            
            $contentParsed = $entity->content;
            if(method_exists($entity, 'parseByTemplate') && $metaTagKeys[$key] !== MetaTags::META_SOCIAL_TITLE) {
                $contentParsed = $entity->parseByTemplate();
            }
            
            // Individual save as fallback
            update_post_meta($postId, $metaTagKeys[$key], $contentParsed);
        }
        
        // Handle advanced settings
        if (isset($payload['noindexForPage'])) {
            update_post_meta($postId, MetaTags::META_NOINDEX_FOR_PAGE, $payload['noindexForPage']);
        }

        if (isset($payload['excludeSitemapForPage'])) {
            update_post_meta($postId, MetaTags::META_EXCLUDE_SITEMAP_FOR_PAGE, $payload['excludeSitemapForPage']);
        }
        
        if (isset($payload['canonicalUrl'])) {
            update_post_meta($postId, MetaTags::META_CANONICAL_URL, $payload['canonicalUrl']);
        }
        
        // Save additional meta
        foreach ($additionalMeta as $metaKey => $metaValue) {
            update_post_meta($postId, $metaKey, $metaValue);
        }
    }

    /**
     * Retrieves the meta-title of a post.
     *
     * @param int $postId The ID of the post to retrieve.
     * @return WPWebPageTitleMetaTag|null The title of the post.
     * @throws InvalidArgumentException
     */
    public function getTitle(int $postId): ?WPWebPageTitleMetaTag {
        /** @var WPWebPageTitleMetaTag $titleMetaTag */
        $titleMetaTag = $this->getMetaTagByTypeAndId($postId, WPWebPageMetaTag::TAG_TYPE_TITLE);
        if($titleMetaTag && $postId) {
            $titleMetaTag->parsed = get_post_meta($postId, MetaTags::META_SEO_TITLE, true) ?? null;
        }
        return $titleMetaTag ?? null;
    }

    /**
     * Retrieves the social meta-title of a post.
     *
     * @param int $postId The ID of the post to retrieve.
     * @return WPWebPageSocialTitleMetaTag|null The social title of the post.
     * @throws InvalidArgumentException
     */
    public function getSocialTitle(int $postId): ?WPWebPageSocialTitleMetaTag {
        /** @var WPWebPageSocialTitleMetaTag $socialTitleMetaTag */
        $socialTitleMetaTag = $this->getMetaTagByTypeAndId($postId, WPWebPageMetaTag::TAG_TYPE_SOCIAL_TITLE);
        if($socialTitleMetaTag && $postId) {
            $socialTitleMetaTag->parsed = get_post_meta($postId, MetaTags::META_SOCIAL_TITLE, true) ?? null;
        }
        return $socialTitleMetaTag ?? null;
    }
    
    /**
     * Retrieves the noindex setting of a post.
     *
     * @param int $postId The ID of the post to retrieve.
     * @return bool|string|null The noindex setting.
     */
    public function getAdvancedSettingsNoIndexForPage(int $postId): bool|string|null {
        return get_post_meta($postId, MetaTags::META_NOINDEX_FOR_PAGE, true) ?? null;
    }

    /**
     * Retrieves the meta-description of a post.
     *
     * @param int $postId
     * @return WPWebPageDescriptionMetaTag|null The description of the post.
     * @throws InvalidArgumentException
     */
    public function getDescription(int $postId): ?WPWebPageDescriptionMetaTag {
        /** @var WPWebPageDescriptionMetaTag $descriptionMetaTag */
        $descriptionMetaTag = $this->getMetaTagByTypeAndId($postId, WPWebPageMetaTag::TAG_TYPE_DESCRIPTION);
        if($descriptionMetaTag && $postId) {
            $descriptionMetaTag->parsed = get_post_meta($postId, MetaTags::META_SEO_DESCRIPTION, true) ?? null;
        }
        return $descriptionMetaTag ?? null;
    }

    /**
     * Retrieves the social meta-description of a post.
     *
     * @param int $postId
     * @return WPWebPageSocialDescriptionMetaTag|null The social description of the post.
     * @throws InvalidArgumentException
     */
    public function getSocialDescription(int $postId): ?WPWebPageSocialDescriptionMetaTag {
        /** @var WPWebPageSocialDescriptionMetaTag $socialDescriptionMetaTag */
        $socialDescriptionMetaTag = $this->getMetaTagByTypeAndId($postId, WPWebPageMetaTag::TAG_TYPE_SOCIAL_DESCRIPTION);
        if($socialDescriptionMetaTag && $postId) {
            $socialDescriptionMetaTag->parsed = get_post_meta($postId, MetaTags::META_SOCIAL_DESCRIPTION, true) ?? null;
        }
        return $socialDescriptionMetaTag ?? null;
    }
    
    /**
     * Retrieves the canonical URL setting of a post.
     *
     * @param int $postId The ID of the post to retrieve.
     * @return string|null The canonical URL setting.
     */
    public function getAdvancedSettingsCanonicalUrl(int $postId): ?string {
        return get_post_meta($postId, MetaTags::META_CANONICAL_URL, true) ?? null;
    }

    /**
     * Retrieves the meta-keywords of a post.
     *
     * @param int $postId
     * @return WPWebPageKeywordsMetaTag|null The keywords of the post.
     * @throws BadRequestException
     * @throws InvalidArgumentException
     */
    public function getKeywords(int $postId): ?WPWebPageKeywordsMetaTag {
        /** @var WPWebPageKeywordsMetaTag $keywordsMetaTag */
        $keywordsMetaTag = $this->getMetaTagByTypeAndId($postId, WPWebPageMetaTag::TAG_TYPE_KEYWORDS);
        if($keywordsMetaTag instanceof WPWebPageKeywordsMetaTag) {
            $keywordsMetaTag->loadKeywords();
        }
        if($keywordsMetaTag && empty($keywordsMetaTag->content) && $postId) {
            $keywordsMetaTag->content = get_post_meta($postId, BaseConstants::META_KEY_SEO_KEYWORDS, true) ?? null;
            if ( ! empty( $keywordsMetaTag->content ) ) {
                $keywordsArray = explode( ',', $keywordsMetaTag->content );
                $keywordsMetaTag->primaryKeyword     = $keywordsArray[0] ?? null;
                $keywordsMetaTag->additionalKeywords = array_map('trim', array_slice( $keywordsArray, 1 ));
            }
        }
        return $keywordsMetaTag;
    }

    /**
     * Retrieves the title, description and keywords of a post.
     *
     * @param int $postId The ID of the post to retrieve.
     * @param bool $returnAsEntity
     * @return WPWebPageMetaTags|array The title and description, keywords of the post.
     * @throws BadRequestException
     * @throws InvalidArgumentException
     */
    public function getTitleDescriptionAndKeywords(int $postId, bool $returnAsEntity = false): array|WPWebPageMetaTags {

        $returnArray = [
            'title'         => $this->getTitle($postId) ?? null,
            'description'   => $this->getDescription($postId) ?? null,
            'keywords'      => $this->getKeywords($postId) ?? null,
        ];

        $handleEntity = function() use ($returnArray) {
            $returnEntity = new WPWebPageMetaTags();
            $returnEntity->add($returnArray['title']);
            $returnEntity->add($returnArray['description']);
            $returnEntity->add($returnArray['keywords']);
            return $returnEntity;
        };

        return $returnAsEntity ? $handleEntity() : $returnArray;
    }

    /**
     * Retrieves the social title and description of the post.
     *
     * @param int $postId The ID of the post to retrieve.
     * @param bool $returnAsEntity
     * @return WPWebPageMetaTags|array The social title and description of the post.
     * @throws InvalidArgumentException
     */
    public function getSocialTitleDescription(int $postId, bool $returnAsEntity = false): array|WPWebPageMetaTags {

        $returnArray = [
            'social_title'         => $this->getSocialTitle($postId) ?? null,
            'social_description'   => $this->getSocialDescription($postId) ?? null
        ];

        $handleEntity = function() use ($returnArray) {
            $returnEntity = new WPWebPageMetaTags();
            $returnEntity->add($returnArray['social_title']);
            $returnEntity->add($returnArray['social_description']);
            return $returnEntity;
        };

        return $returnAsEntity ? $handleEntity() : $returnArray;
    }

    /**
     * Retrieves the selected social image source for a post.
     *
     * @param int $postId The ID of the post.
     * @return array The selected image source.
     */
    public function getAdvancedSettingsMetaByPostId(int $postId): array
    {
        $noindexForPage = get_post_meta($postId, MetaTags::META_NOINDEX_FOR_PAGE, true);
        $canonicalUrl = get_post_meta($postId, MetaTags::META_CANONICAL_URL, true);
        $excludeSitemapForPage = get_post_meta($postId, MetaTags::META_EXCLUDE_SITEMAP_FOR_PAGE, true);
        $disableAutoLinks = get_post_meta($postId, MetaTags::META_DISABLE_AUTO_LINKS, true);
        $viewportForPage = get_post_meta($postId, MetaTags::META_VIEWPORT_FOR_PAGE, true);

        return [
            'noindexForPage' => $noindexForPage,
            'canonicalUrl' => $canonicalUrl,
            'excludeSitemapForPage' => $excludeSitemapForPage,
            'disableAutoLinks' => $disableAutoLinks,
            'viewportForPage' => $viewportForPage,
        ];
    }

    /**
     * Retrieves the meta-tags of a post.
     *
     * @param MetaTagsRequestDto $requestDto The request DTO.
     *
     * @return array The meta-tags.
     * @throws BadRequestException
     * @throws InvalidArgumentException
     */
    public function getMetaTags(MetaTagsRequestDto $requestDto): array
    {
        $metatags = $this->getTitleDescriptionAndKeywords($requestDto->postId);
        return [
            'title' => $metatags['title'] ?? null,
            'description' => $metatags['description'] ?? null,
            'keywords' => $metatags['keywords'] ?? null
        ];
    }

    /**
     * Retrieves the social meta-tags of a post.
     *
     * @param MetaTagsRequestDto $requestDto The request DTO.
     *
     * @return array The social meta-tags.
     * @throws InvalidArgumentException
     */
    public function getSocialMetaTags(MetaTagsRequestDto $requestDto): array
    {
        $socialMetatags = $this->getSocialTitleDescription($requestDto->postId);
        return [
            'social_title' => $socialMetatags['social_title'] ?? null,
            'social_description' => $socialMetatags['social_description'] ?? null
        ];
    }

    /**
     * Remove the meta-tags specific keyword.
     *
     * @param KeywordsMetaTagsKeywordRequestDto $requestDto
     * @return array
     * @throws Throwable
     */
    public function removeMetaTagsKeyword(KeywordsMetaTagsKeywordRequestDto $requestDto): array
    {
        $postId = $requestDto->postId;
        $keyword = $requestDto->keyword;

        /** @var WPWebPageKeywordsMetaTag $currentKeywords */
        $currentKeywords = $this->getKeywords($postId);

        $currentKeywords->deleteKeywordByName($keyword);
        $currentKeywords->saveKeywords();

        $updatedMetaTags = $this->getMetaTags($requestDto);

        // Sync the keywords with the onboarding keywords
        // TODO: before syncing, we should check if the keyword is used in any post content or not
        // Just if the keyword is not used in any post content, we can remove it from the onboarding keywords
        // $this->syncMetaKeywordsWithOnboardingKeywords($updatedMetaTags['keywords'], $keyword);

        return $updatedMetaTags;
    }

    /**
     * Saves the meta-tags of a post.
     *
     * @param MetaTagsPostRequestDto $requestDto The request DTO.
     *
     * @throws Throwable
     */
    public function saveMetaTags(MetaTagsPostRequestDto $requestDto): array
    {
        $title = $this->getTitle($requestDto->postId);
        if($requestDto->title) {
            if(!$title) {
                $title = new WPWebPageTitleMetaTag($requestDto->postId);
            }
            // if the requested title is different from the current one, we need to set auto_generated flag to false
            if($title->content !== $requestDto->title->content) {
                $title->autoGenerated = false;
            }
            // Truncate title to 60 characters
            $title->content = mb_substr($requestDto->title->content, 0, 150);
            $title->variables = $requestDto->title->variables;
            $title->formatTemplate();
            $title->update();
        }

        $description = $this->getDescription($requestDto->postId);
        if($requestDto->description) {
            if(!$description) {
                $description = new WPWebPageDescriptionMetaTag($requestDto->postId);
            }
            // if the requested description is different from the current one, we need to set auto_generated flag to false
            if($description->content !== $requestDto->description->content) {
                $description->autoGenerated = false;
            }
            // Truncate description to 300 characters
            $description->content = mb_substr($requestDto->description->content, 0, 300);
            $description->variables = $requestDto->description->variables;
            $description->formatTemplate();
            $description->update();
        }

        $payload = [
            'title' => $title ?? null,
            'description' => $description ?? null,
            'keywords' => $requestDto->keywords ?? null
        ];

        $this->updateMetaTags($payload, $requestDto->postId);

        $updatedMetaTags = $this->getMetaTags($requestDto);

        if(!empty($updatedMetaTags['keywords'])) {
            // Sync the keywords with the onboarding keywords
            $this->syncMetaKeywordsWithOnboardingKeywords($updatedMetaTags['keywords']);
        }

        return $updatedMetaTags;
    }

    /**
     * Saves the social meta-tags of a post.
     *
     * @param SocialMetaTagsPostRequestDto $requestDto The request DTO.
     *
     * @throws Throwable
     */
    public function saveSocialMetaTags(SocialMetaTagsPostRequestDto $requestDto): void
    {
        $title = $this->getSocialTitle($requestDto->postId);
        if($requestDto->title) {
            if(!$title) {
                $title = new WPWebPageSocialTitleMetaTag($requestDto->postId);
            }
            // Truncate social title to 60 characters
            $title->content = mb_substr($requestDto->title->content, 0, 60);
            $title->variables = $requestDto->title->variables;
            $title->formatTemplate();
            $title->update();
        }

        $description = $this->getSocialDescription($requestDto->postId);
        if($requestDto->description) {
            if(!$description) {
                $description = new WPWebPageSocialDescriptionMetaTag($requestDto->postId);
            }
            // Truncate social description to 300 characters
            $description->content = mb_substr($requestDto->description->content, 0, 300);
            $description->variables = $requestDto->description->variables;
            $description->formatTemplate();
            $description->update();
        }
        
        // Save the selected image source if provided
        if ($requestDto->selectedImageSource) {
            // We only need to save the source identifier, the URL will be determined dynamically
            $this->saveSelectedSocialImageSource($requestDto->postId, $requestDto->selectedImageSource);
        }

        $payload = [
            'social_title' => $title ?? null,
            'social_description' => $description ?? null
        ];

        $this->updateMetaTags($payload, $requestDto->postId);
    }

    /**
     * Save the meta-tags specific keyword.
     *
     * @param KeywordsMetaTagsKeywordRequestDto $requestDto
     * @return array
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws MappingException
     * @throws ReflectionException
     * @throws Throwable
     */
    public function saveMetaTagsKeyword(KeywordsMetaTagsKeywordRequestDto $requestDto): array
    {
        $postId = $requestDto->postId;
        $keyword = $requestDto->keyword;

        /** @var WPWebPageKeywordsMetaTag $currentKeywords */
        $currentKeywords = $this->getKeywords($postId);
        if(!$currentKeywords) {
            $currentKeywords = new WPWebPageKeywordsMetaTag();
            $currentKeywords->postId = $postId;
        }
        $currentKeywords->addKeyword($keyword);
        $currentKeywords->saveKeywords();

        $updatedMetaTags = $this->getMetaTags($requestDto);

        // Sync the keywords with the onboarding keywords
        $this->syncMetaKeywordsWithOnboardingKeywords($updatedMetaTags['keywords']);

        return $updatedMetaTags;
    }

    /**
     * Auto-suggests a meta-tags title for a post.
     *
     * @param WPWebPageTitleMetaTag $metaTitle
     * @return void
     * @throws BadRequestException
     * @throws Exception
     * @throws ForbiddenException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws \Doctrine\Persistence\Mapping\MappingException
     * @throws ReflectionException
     * @throws MappingException
     */
    public function autoSuggestMetaTagsTitle(WPWebPageTitleMetaTag $metaTitle): void
    {
        $metaTitle->autoSuggest();
    }

    /**
     * Auto-suggests a meta-tags description for a post.
     *
     * @param WPWebPageDescriptionMetaTag $metaDescription
     * @return void
     * @throws BadRequestException
     * @throws Exception
     * @throws ForbiddenException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws MappingException
     * @throws ReflectionException
     * @throws \Doctrine\Persistence\Mapping\MappingException
     */
    public function autoSuggestMetaTagsDescription(WPWebPageDescriptionMetaTag $metaDescription): void
    {
        $metaDescription->autoSuggest();
    }

    /**
     * Swap the meta-tags specific keyword.
     *
     * @param KeywordsMetaTagsKeywordRequestDto $requestDto
     * @return array
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws MappingException
     * @throws ReflectionException
     * @throws Throwable
     */
    public function swapMetaTagsKeyword(KeywordsMetaTagsKeywordRequestDto $requestDto): array
    {
        $postId = $requestDto->postId;
        $keyword = $requestDto->keyword;

        /** @var WPWebPageKeywordsMetaTag $currentKeywords */
        $currentKeywords = $this->getKeywords($postId);
        $currentKeywords->swapKeyword($keyword);
        $currentKeywords->saveKeywords();

        return $this->getMetaTags($requestDto);
    }

    /**
     * Retrieves the social image sources of a post.
     *
     * @param int $postId
     * @return array The social image sources.
     */
    public function getSocialImageSources(int $postId): array
    {
        return SocialMediaHelper::getSocialImageSources($postId);
    }
    
    /**
     * Get Selected Social Image Source
     *
     * @param int $postId
     * @return string|null
     */
    public function getSelectedSocialImageSource(int $postId): ?string
    {
        return SocialMediaHelper::getSelectedSocialImageSource($postId);
    }
    
    /**
     * Get Selected Social Image URL
     *
     * @param int $postId
     * @return string|null
     */
    public function getSelectedSocialImageUrl(int $postId): ?string
    {
        return SocialMediaHelper::getSelectedSocialImageUrl($postId);
    }
    
    /**
     * Save Selected Social Image Source
     *
     * @param int $postId
     * @param string $sourceIdentifier
     * @return bool
     */
    public function saveSelectedSocialImageSource(int $postId, string $sourceIdentifier): bool
    {
        return SocialMediaHelper::saveSelectedSocialImageSource($postId, $sourceIdentifier);
    }

    /**
     * Sync meta keywords with onboarding keywords.
     *
     * @param WPWebPageKeywordsMetaTag $currentKeywords
     * @param string|null $deleteKeyword
     * @return void
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws MappingException
     * @throws ReflectionException
     */
    public function syncMetaKeywordsWithOnboardingKeywords(WPWebPageKeywordsMetaTag $currentKeywords, ?string $deleteKeyword = null): void
    {
        $keywords = new WPKeywords();

        $primaryKeyword = $currentKeywords->primaryKeyword;
        $additionalKeywords = $currentKeywords->additionalKeywords ?? [];

        // add primary keyword at start of the additional keywords
        if (!empty($primaryKeyword)) {
            $allKeywords = array_merge([$primaryKeyword], $additionalKeywords);
        }

        foreach (($allKeywords ?? []) as $keyword) {
            $wpKeyword = new WPKeyword();
            $wpKeyword->setName($keyword);
            $keywords->add($wpKeyword);
        }

        $wpService = $keywords->getService();
        try {
            // Pass true as the second parameter to delete keywords that are in the database but not in the current keywords list
            $wpService->addOnboardingKeywords($keywords);
        } catch (Throwable) {
            // Handle the exception if needed
        }

        // Delete keywords that are in the database but not in the provided keywords
        if ($deleteKeyword) {

            $dbKeywords = $wpService->getAllKeywords();
            /** @var WPKeyword $dbKeyword */
            foreach ($dbKeywords->getElements() as $dbKeyword) {
                if ($deleteKeyword === $dbKeyword->name) {
                    // This keyword exists in the database but not in the current keywords list
                    $repoItem = new InternalDBWPKeyword();
                    try {
                        $repoItem->delete($dbKeyword);
                    } catch (Throwable) {
                        // Handle the exception if needed
                    }
                }
            }
        }
    }

    /**
     * Retrieves the social meta-tags of a post.
     *
     * @param MetaTagsRequestDto $requestDto The request DTO.
     *
     * @return array The social meta-tags.
     */
    public function getAdvancedSettingsMetaTags(MetaTagsRequestDto $requestDto): array
    {
        $advancedSettingsMetatags = $this->getAdvancedSettingsMetaByPostId($requestDto->postId);
        return [
            'noindexForPage' => $advancedSettingsMetatags['noindexForPage'] ?? null,
            'canonicalUrl' => $advancedSettingsMetatags['canonicalUrl'] ?? null,
            'excludeSitemapForPage' => $advancedSettingsMetatags['excludeSitemapForPage'] ?? null,
            'disableAutoLinks' => $advancedSettingsMetatags['disableAutoLinks'] ?? null,
            'viewportForPage' => $advancedSettingsMetatags['viewportForPage'] ?? null
        ];
    }

    /**
     * Saves the advanced settings meta-tags of a post.
     *
     * @param AdvancedSettingsMetaTagsPostRequestDto $requestDto The request DTO.
     *
     * @throws Throwable
     */
    public function saveAdvancedSettingsMetaTags(AdvancedSettingsMetaTagsPostRequestDto $requestDto): void
    {
        // Get current values or use new values from request
        $noindexForPage = $requestDto->noindexForPage ?? false;
        $excludeSitemapForPage = $requestDto->excludeSitemapForPage ?? false;
        $disableAutoLinks = $requestDto->disableAutoLinks ?? false;
        $viewportForPage = $requestDto->viewportForPage ?? false;

        $currentCanonicalUrl = $this->getAdvancedSettingsCanonicalUrl($requestDto->postId);
        $canonicalUrl = $requestDto->canonicalUrl ?? $currentCanonicalUrl;

        // Prepare payload for updateMetaTags
        $payload = [
            'noindexForPage' => $noindexForPage,
            'canonicalUrl' => $canonicalUrl,
            'excludeSitemapForPage' => $excludeSitemapForPage,
            'disableAutoLinks' => $disableAutoLinks,
            'viewportForPage' => $viewportForPage
        ];

        // Update both in metaTags table and post meta
        $this->updateMetaTags($payload, $requestDto->postId);
    }
}
