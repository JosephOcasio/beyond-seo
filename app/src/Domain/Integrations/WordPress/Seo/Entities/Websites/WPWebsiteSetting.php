<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\Websites;

use App\Domain\Integrations\WordPress\Seo\Entities\Websites\Settings\WPWebsiteDiscussionSetting;
use App\Domain\Integrations\WordPress\Seo\Entities\Websites\Settings\WPWebsiteGeneralSetting;
use App\Domain\Integrations\WordPress\Seo\Entities\Websites\Settings\WPWebsiteReadingSetting;
use App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\Websites\InternalDBWPWebsiteSettings;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;
use DDD\Domain\Base\Entities\ValueObject;
use DDD\Infrastructure\Traits\Serializer\Attributes\HideProperty;

/**
 * Class WPWebsiteSetting
 */
#[LazyLoadRepo(repoType: LazyLoadRepo::INTERNAL_DB, repoClass: InternalDBWPWebsiteSettings::class)]
class WPWebsiteSetting extends ValueObject
{
    /** @var string Site URL */
    public string $siteUrl;

    /** @var string Home URL */
    public string $homeUrl;

    /** @var string Blog name */
    public string $blogName;

    /** @var string Blog description */
    public string $blogDescription;

    /** @var string Admin email */
    public string $adminEmail;

    /** @var string Site language */
    public string $siteLanguage;

    /** @var bool Is multisite */
    public bool $isMultisite;

    /** @var string Active plugins */
    public string $activePlugins;

    /** @var string Template */
    public string $template;

    /** @var string Stylesheet */
    public string $stylesheet;

    /** @var string|null The unique key of the site */
    public ?string $uniqueKey = null;

    /** @var int $rcUserId User ID on RC side */
    public int $rcUserId;

    /** @var string $rcAccessToken Access token for connection to RC side */
    #[HideProperty]
    public string $rcAccessToken;

    /** @var string $rcRefreshToken Refresh token for connection to RC side */
    #[HideProperty]
    public string $rcRefreshToken;

    /** @var string $theme Theme name */
    public string $theme;

    /** @var string $themeVersion Theme version */
    public string $themeVersion;

    /** @var string $themeAuthor Theme author */
    public string $themeAuthor;

    /** @var string $permalinkStructure Permalink structure */
    public string $permalinkStructure;

    /** @var array<string,string> $allowedCountries List of allowed countries from plugin settings */
    public array $allowedCountries = [];

    /** @var WPWebsiteDiscussionSetting|null The site discussion options */
    public ?WPWebsiteDiscussionSetting $discussion = null;

    /** @var WPWebsiteGeneralSetting|null The site general options */
    public ?WPWebsiteGeneralSetting $general = null;

    /** @var WPWebsiteReadingSetting|null The site reading options */
    public ?WPWebsiteReadingSetting $reading = null;

    // Constructor
    public function __construct()
    {
        parent::__construct();
    }
}