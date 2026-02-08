<?php

declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Setup\Repo\InternalDB;

use App\Domain\Common\Repo\InternalDB\InternalDBEntitySet;
use App\Domain\Integrations\WordPress\Setup\Entities\SetupSteps\SetupStepCompletions\WPSetupStepCompletions;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineQueryBuilder;

/**
 * @method WPSetupStepCompletions find(DoctrineQueryBuilder $queryBuilder = null, $useEntityRegistrCache = true)
 */
class InternalDBWPSetupStepCompletions extends InternalDBEntitySet
{
    public const BASE_REPO_CLASS = InternalDBWPSetupStepCompletion::class;
    public const BASE_ENTITY_SET_CLASS = WPSetupStepCompletions::class;
}