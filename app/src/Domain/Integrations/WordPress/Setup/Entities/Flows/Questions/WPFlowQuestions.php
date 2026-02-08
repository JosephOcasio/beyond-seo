<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Setup\Entities\Flows\Questions;

use App\Domain\Integrations\WordPress\Setup\Repo\InternalDB\Flows\Questions\InternalDBWPFlowQuestions;
use App\Domain\Integrations\WordPress\Setup\Services\WPFlowQuestionsService;
use DDD\Domain\Base\Entities\EntitySet;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;

/**
 * Class WPFlowQuestions
 * @method WPFlowQuestion[] getElements()
 * @method WPFlowQuestion|null first()
 * @method WPFlowQuestion|null getByUniqueKey(string $uniqueKey)
 * @property WPFlowQuestion[] $elements
 */
#[LazyLoadRepo(repoType: LazyLoadRepo::INTERNAL_DB, repoClass: InternalDBWPFlowQuestions::class)]
class WPFlowQuestions  extends EntitySet
{
    public const ENTITY_CLASS = WPFlowQuestion::class;
    public const SERVICE_NAME = WPFlowQuestionsService::class;
}