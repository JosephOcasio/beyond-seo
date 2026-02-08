<?php

namespace App\Domain\Integrations\WordPress\Common\Entities\Categories;

use App\Domain\Common\Repo\InternalDB\Categories\InternalDBWPCategory;
use App\Domain\Common\Services\WPCategoriesService;
use DDD\Domain\Base\Entities\Entity;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;

/**
 * @property WPCategories $parent
 * @method WPCategories getParent()
 * @method static WPCategoriesService getService()
 */

#[LazyLoadRepo(repoType: LazyLoadRepo::INTERNAL_DB, repoClass: InternalDBWPCategory::class)]
class WPCategory extends Entity {

    /** @var int|null ID of the local db Category */
    public ?int $id;

    /** @var int categoryId in rC */
    public int $categoryId;

    /** @var string name on rC */
    public string $name;

    /** @var string alias on rC */
    public string $externalId;

    public static function fromArray(array $data): Entity {
        $entity = new self();
        $entity->id = $data['id'] ?? null;
        $entity->categoryId = $data['categoryId'] ?? $data['id'] ?? 0;
        $entity->name = $data['name'];
        $entity->externalId = $data['externalId'];
        return $entity;
    }

}
