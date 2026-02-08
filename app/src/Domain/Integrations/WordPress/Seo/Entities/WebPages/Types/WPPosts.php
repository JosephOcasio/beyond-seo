<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Types;

use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\WPWebPages;
use App\Domain\Integrations\WordPress\Seo\Services\WPWebPageService;

/**
 * Represents a set of WPPosts pages.
 *
 * @method WPPost[] getElements()
 * @method WPPost|null first()
 * @method WPPost|null getByUniqueKey(string $uniqueKey)
 * @property WPPost[] $elements
 */
class WPPosts extends WPWebPages
{
    public const SERVICE_NAME = WPWebPageService::class;
}