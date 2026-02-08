<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\Websites\Settings;

use App\Domain\Common\Entities\Settings\Setting;

/**
 * Class WPWebsiteDiscussionSettings
 */
class WPWebsiteDiscussionSetting extends Setting
{
    /** @var string|null Default status for new comments (e.g., 'open' or 'closed'), or null if not set. */
    public ?string $defaultCommentStatus = null;

    /** @var string|null List of moderation keywords for filtering comments, or null if not set. */
    public ?string $moderationKeys = null;

    /** @var string|null Whether comment moderation is enabled ('1' for enabled, '0' for disabled), or null if not set. */
    public ?string $commentModeration = null;
}