<?php
namespace App\Domain\Integrations\WordPress\Setup\Entities\Flows\Requirements;

use App\Domain\Integrations\WordPress\Setup\Repo\InternalDB\Flows\Requirements\InternalDBWPRequirement;
use App\Domain\Integrations\WordPress\Setup\Services\WPRequirementsService;
use DDD\Domain\Base\Entities\Entity;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;

/**
 * @property WPRequirements $parent
 * @method WPRequirements getParent()
 * @method static WPRequirementsService getService()
 */

#[LazyLoadRepo(repoType: LazyLoadRepo::INTERNAL_DB, repoClass: InternalDBWPRequirement::class)]
class WPRequirement extends Entity {

    /** @var int|null ID of the Requirement */
    public ?int $id;

    /** @var string Name of the requirement */
    public string $setupRequirement;

    /** @var string Alias of the requirement on rC */
    public string $entityAlias;

    /** @var string|null Value of the requirement */
    public ?string $value = null;

    public function uniqueKey(): string
    {
        return md5($this->entityAlias);
    }

}
