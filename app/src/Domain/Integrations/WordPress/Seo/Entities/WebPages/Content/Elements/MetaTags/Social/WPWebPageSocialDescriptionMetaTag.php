<?php
declare( strict_types=1 );

namespace App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\MetaTags\Social;

use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\MetaTags\WPWebPageMetaTag;
use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\MetaTags\WPWebPageMetaTags;
use App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\WebPages\InternalDBWPWebPageSocialMetaTagDescription;
use App\Domain\Integrations\WordPress\Seo\Repo\RC\RCWebPageDescriptionMetaTags;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;

/**
 * Class WPWebPageSocialDescriptionMetaTag
 * @property WPWebPageMetaTags $parent
 * @method WPWebPageMetaTags getParent()
 */
#[LazyLoadRepo(LazyLoadRepo::INTERNAL_DB, InternalDBWPWebPageSocialMetaTagDescription::class)]
#[LazyLoadRepo(LazyLoadRepo::RC, RCWebPageDescriptionMetaTags::class)]
class WPWebPageSocialDescriptionMetaTag extends WPWebPageMetaTag {

    /** @var string The type of the meta-tag */
    public string $type = WPWebPageMetaTag::TAG_TYPE_SOCIAL_DESCRIPTION;

    /** @var string|null The parsed content */
    public ?string $parsed = null;

    /**
     * WPWebPageDescriptionMetaTag constructor.
     *
     * @param int $postId
     */
    public function __construct(int $postId = 0) {
        $this->type = WPWebPageMetaTag::TAG_TYPE_SOCIAL_DESCRIPTION;
        parent::__construct($postId, $this->type);
    }
}
