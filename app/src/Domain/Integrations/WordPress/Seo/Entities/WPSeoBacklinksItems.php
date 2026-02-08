<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities;

use DDD\Domain\Base\Entities\ObjectSet;

/**
 * Class WPSeoBacklinksItems
 * Collection of WPSeoBacklinksItem objects
 * 
 * @property WPSeoBacklinksItem[] $elements
 * @method WPSeoBacklinksItem getByUniqueKey(string $uniqueKey)
 * @method WPSeoBacklinksItem[] getElements()
 * @method WPSeoBacklinksItem first()
 */
class WPSeoBacklinksItems extends ObjectSet
{
}