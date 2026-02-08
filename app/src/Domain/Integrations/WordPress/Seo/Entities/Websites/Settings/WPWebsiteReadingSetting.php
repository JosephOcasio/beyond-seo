<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\Websites\Settings;

use App\Domain\Common\Entities\Settings\Setting;

/**
 * Class WPWebsiteReading
 */
class WPWebsiteReadingSetting extends Setting
{
    /** @var string|null Determines what is displayed on the front page, or null if not set. */
    public ?string $showOnFront = null;

    /** @var string|null ID or slug of the page set as the front page, or null if not set. */
    public ?string $pageOnFront = null;

    /** @var string|null ID or slug of the page set for displaying posts, or null if not set. */
    public ?string $pageForPosts = null;

    /** @var string|null Number of posts displayed per page, or null if not set. */
    public ?string $postsPerPage = null;
}