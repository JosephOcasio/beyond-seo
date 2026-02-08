<?php

declare(strict_types=1);

namespace App\Domain\Seo\Entities\CMSTypes;

use DDD\Domain\Base\Entities\ValueObject;

/**
 * CMS Type entity
 */
class CMSType extends ValueObject
{

    /** @var string The name of the CMS */
    public string $name;

    /** @var string The alias of the CMS */
    public string $displayName;

    /** @var int The priority of the CMS*/
    public int $priority;

    /** @var bool Whether the CMS is enabled or not */
    public bool $active;

    /** @var string The img name or url of the CMS */
    public string $img;

    /** @var string|null The alias of the CMS */
    public ?string $alias;

    /** @var string|null The CSS class of the CMS */
    public ?string $class;

    /** @var string|null The version of the CMS */
    public ?string $version;

    /** @var bool Whether the CMS is shown on public pages */
    public bool $showOnPublic;

    /** @var bool Whether the CMS is an online shop*/
    public bool $isOnlineShop;

    /** @var string|null The detection name of the CMS */
    public ?string $cmsDetectionName;
}
