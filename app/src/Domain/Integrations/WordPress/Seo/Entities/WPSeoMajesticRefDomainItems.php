<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities;

use DDD\Domain\Base\Entities\ObjectSet;

/**
 * Class RefDomainItems
 * Collection of RefDomainItem objects
 * 
 * @property WPSeoMajesticRefDomainItem[] $elements
 * @method WPSeoMajesticRefDomainItem getByUniqueKey(string $uniqueKey)
 * @method WPSeoMajesticRefDomainItem[] getElements()
 * @method WPSeoMajesticRefDomainItem first()
 */
class WPSeoMajesticRefDomainItems extends ObjectSet
{
}