<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities;

use DDD\Domain\Base\Entities\ValueObject;

/**
 * Class WPSeoBacklinksItem
 * Represents a single backlink item from the backlinks API
 */
class WPSeoBacklinksItem extends ValueObject
{
    /** @var int $domain_rating Domain rating score */
    public int $domain_rating = 0;

    /** @var string $url_from URL of the referring page */
    public string $url_from = '';

    /** @var string $url_to URL being linked to */
    public string $url_to = '';
    
    /** @var string $from Source URL for backlinks filtering */
    public string $from = '';

    /** @var string $anchor Anchor text of the link */
    public string $anchor = '';
}