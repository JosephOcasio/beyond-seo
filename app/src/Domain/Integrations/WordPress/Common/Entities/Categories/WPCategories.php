<?php

namespace App\Domain\Integrations\WordPress\Common\Entities\Categories;

use App\Domain\Common\Repo\InternalDB\Categories\InternalDBWPCategories;
use App\Domain\Common\Services\WPCategoriesService;
use DDD\Domain\Base\Entities\EntitySet;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;

/**
 * Class WPCategories
 * @method WPCategory[] getElements()
 * @method WPCategory|null first()
 * @method WPCategory|null getByUniqueKey(string $uniqueKey)
 * @property WPCategory[] $elements
 */


#[LazyLoadRepo(repoType: LazyLoadRepo::INTERNAL_DB, repoClass: InternalDBWPCategories::class)]
class WPCategories extends EntitySet
{
    public const ENTITY_CLASS = WPCategory::class;
    public const SERVICE_NAME = WPCategoriesService::class;
}
