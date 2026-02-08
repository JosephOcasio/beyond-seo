<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content;

use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\MetaTags\Tags\WPWebPageDescriptionMetaTag;
use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\MetaTags\Tags\WPWebPageKeywordsMetaTag;
use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\MetaTags\Tags\WPWebPageTitleMetaTag;
use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\WPWebPage;
use App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\WebPages\InternalDBWPWebPageMetaTagDescription;
use App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\WebPages\InternalDBWPWebPageMetaTagKeyword;
use App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\WebPages\InternalDBWPWebPageMetaTagTitle;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoad;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;
use DDD\Domain\Base\Entities\ValueObject;

/**
 * @method WPWebPage getParent()
 * @property WPWebPage $parent
 *
 * Class WPWebPageContentMetaTags
 */
class WPWebPageContentMetaTags extends ValueObject
{
    /** @var int|null $postId The ID of the post that the content is for */
    public ?int $postId = null;

    /** @var WPWebPageTitleMetaTag|null $title */
    #[LazyLoad(repoType: LazyLoadRepo::INTERNAL_DB, repoClass: InternalDBWPWebPageMetaTagTitle::class)]
    public ?WPWebPageTitleMetaTag $titleMetaTag = null;

    /** @var WPWebPageDescriptionMetaTag|null $seoDescription */
    #[LazyLoad(repoType: LazyLoadRepo::INTERNAL_DB, repoClass: InternalDBWPWebPageMetaTagDescription::class)]
    public ?WPWebPageDescriptionMetaTag $descriptionMetaTag = null;

    /** @var WPWebPageKeywordsMetaTag|null $seoKeywords */
    #[LazyLoad(repoType: LazyLoadRepo::INTERNAL_DB, repoClass: InternalDBWPWebPageMetaTagKeyword::class)]
    public ?WPWebPageKeywordsMetaTag $keywordsMetaTag = null;

    /**
     * WPWebPageContentMetaTags constructor.
     * @param int|null $postId
     */
    public function __construct(?int $postId = null)
    {
        $this->postId = $postId;
        parent::__construct();
    }
}
