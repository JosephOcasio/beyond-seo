<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Setup\Entities\Flows\Completions;

use App\Domain\Integrations\WordPress\Setup\Repo\InternalDB\Flows\Completions\InternalDBWPFlowCompletions;
use App\Domain\Integrations\WordPress\Setup\Services\WPFlowDataCompletionsService;
use DDD\Domain\Base\Entities\EntitySet;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;

/**
 * Class WPFlowDataCompletions
 *
 * @method WPFlowDataCompletion[] getElements()
 * @method WPFlowDataCompletion|null first()
 * @method WPFlowDataCompletion|null getByUniqueKey(string $uniqueKey)
 * @property WPFlowDataCompletion[] $elements
 */
#[LazyLoadRepo(LazyLoadRepo::INTERNAL_DB, InternalDBWPFlowCompletions::class)]
class WPFlowDataCompletions extends EntitySet
{
    public const ENTITY_CLASS = WPFlowDataCompletion::class;
    public const SERVICE_NAME = WPFlowDataCompletionsService::class;
}