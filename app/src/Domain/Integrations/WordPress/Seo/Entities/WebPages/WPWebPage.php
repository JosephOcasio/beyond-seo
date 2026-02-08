<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\WebPages;

use App\Domain\Integrations\WordPress\Common\Entities\Accounts\WPAccount;
use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\WPWebPageContent;
use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\WPWebPageContentMetaTags;
use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Settings\WPWebPageSettings;
use App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\WebPages\InternalDBWPWebPage;
use App\Domain\Integrations\WordPress\Seo\Services\WPWebPageService;
use App\Domain\Seo\Entities\WebPages\WebPage;
use App\Domain\Seo\Entities\WebPages\WebPageContent;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoad;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;
use DDD\Infrastructure\Validation\Constraints\Choice;

/**
 * Represents a WordPress page entity.
 *
 * @method WPWebPageService getService()
 * @method WPWebPages getParent()
 */
#[LazyLoadRepo(LazyLoadRepo::INTERNAL_DB, repoClass: InternalDBWPWebPage::class)]
class WPWebPage extends WebPage
{
    #============================================
    # region Constants
    #============================================
    /** @var string CONTENT_TYPE_PAGE Represents a type page in WordPress */
    public const CONTENT_TYPE_PAGE = 'PAGE';

    /** @var string CONTENT_TYPE_POST Represents a blog post in WordPress */
    public const CONTENT_TYPE_POST = 'POST';

    /** @var string CONTENT_TYPE_ATTACHMENT Represents an attached media file (image, video, document) */
    public const CONTENT_TYPE_ATTACHMENT = 'ATTACHMENT';

    /** @var string CONTENT_TYPE_REVISION Represents a revision of a page or post */
    public const CONTENT_TYPE_REVISION = 'REVISION';

    /** @var string CONTENT_TYPE_NAVIGATION_MENU_ITEM Represents a navigation menu item */
    public const CONTENT_TYPE_NAVIGATION_MENU_ITEM = 'NAV_MENU_ITEM';

    /** @var string CONTENT_TYPE_WP_BLOCK Represents a reusable block in the Gutenberg editor */
    public const CONTENT_TYPE_WP_BLOCK = 'WP_BLOCK';

    /** @var string CONTENT_TYPE_CUSTOM Represents a custom post type */
    public const CONTENT_TYPE_CUSTOM = 'CUSTOM';
    # endregion
    #============================================

    #============================================
    # region Attributes
    #============================================
    /** @var int|null Unique identifier for the content */
    #[LazyLoad]
    public ?int $id;

    /** @var int Author ID of the content */
    #[LazyLoad]
    public int $authorId;

    /** @var WPAccount|null Author of the content */
    #[LazyLoad(LazyLoadRepo::INTERNAL_DB)]
    public ?WPAccount $author = null;

    /** @var string Post type */
    #[Choice([], [self::CONTENT_TYPE_PAGE, self::CONTENT_TYPE_POST, self::CONTENT_TYPE_ATTACHMENT, self::CONTENT_TYPE_REVISION, self::CONTENT_TYPE_NAVIGATION_MENU_ITEM, self::CONTENT_TYPE_WP_BLOCK, self::CONTENT_TYPE_CUSTOM])]
    public string $postType = self::CONTENT_TYPE_PAGE;

    /** @var string Date when the content was created */
    public string $dateCreated;

    /** @var string Date in GMT when the content was created */
    public string $dateCreatedGmt;

    /** @var WPWebPageContent Loaded from website */
    public WebPageContent $content;

    /** @var WPWebPageContentMetaTags Loaded from website */
    public WPWebPageContentMetaTags $metaTags;

    /** @var string|null The content of the web page */
    public ?string $webPageContent = null;

    /** @var string Excerpt or summary of the content */
    public string $excerpt;

    /** @var string Current status of the content */
    public string $status;

    /** @var string Comment status */
    public string $commentStatus;

    /** @var string Ping status */
    public string $pingStatus;

    /** @var string Content password */
    public string $password;

    /** @var string URL-friendly version of the content title */
    public string $slug;

    /** @var string List of URLs to ping */
    public string $toPing;

    /** @var string List of pinged URLs */
    public string $pinged;

    /** @var string Date when the content was last modified */
    public string $dateModified;

    /** @var string Date in GMT when the content was last modified */
    public string $dateModifiedGmt;

    /** @var string Filtered content */
    public string $contentFiltered;

    /** @var int Parent content ID */
    public int $parentId;

    /** @var string GUID */
    public string $guid;

    /** @var int Menu order */
    public int $menuOrder;

    /** @var string MIME type of the post */
    public string $mimeType;

    /** @var int Comment count */
    public int $commentCount;

    /** @var string|null The unique key of the content */
    public ?string $uniqueKey = null;

    /** @var WPWebPageSettings|null $postMeta The meta-data of the content. */
    #[LazyLoad(LazyLoadRepo::INTERNAL_DB)]
    public ?WPWebPageSettings $postMeta = null;

    # endregion
    #============================================
}