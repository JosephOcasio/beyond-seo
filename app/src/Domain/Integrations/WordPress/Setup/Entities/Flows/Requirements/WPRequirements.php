<?php

namespace App\Domain\Integrations\WordPress\Setup\Entities\Flows\Requirements;

use App\Domain\Integrations\WordPress\Setup\Repo\InternalDB\Flows\Requirements\InternalDBWPRequirements;
use App\Domain\Integrations\WordPress\Setup\Services\WPRequirementsService;
use DDD\Domain\Base\Entities\EntitySet;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;

/**
 * Class WPRequirements
 * @method WPRequirement[] getElements()
 * @method WPRequirement|null first()
 * @method WPRequirement|null getByUniqueKey(string $uniqueKey)
 * @property WPRequirement[] $elements
 */


#[LazyLoadRepo(repoType: LazyLoadRepo::INTERNAL_DB, repoClass: InternalDBWPRequirements::class)]
class WPRequirements  extends EntitySet
{
    public const ENTITY_CLASS = WPRequirement::class;
    public const SERVICE_NAME = WPRequirementsService::class;

    /** @var array $entityAliasBasedOnRequirement Collection of entity alias based on requirement */
    public static array $entityAliasBasedOnRequirement = [
        'businessEmailAddress'          => 'emailaddress',
        'businessWebsiteUrl'            => 'websiteurl',
        'businessName'                  => 'name',
        'businessDescription'           => 'description',
        'businessAddress'               => 'address',
        'businessGeoAddress'            => 'geoaddress',
        'businessServiceArea'           => 'servicearea',
        'businessKeywords'              => 'keywords',
        'businessCategories'            => 'categories',
        'businessSpecificDescription'   => 'specificdescription',
    ];
}
