<?php

declare (strict_types=1);

namespace App\Domain\Integrations\WordPress\Setup\Entities\SetupSteps\SetupStepCompletions;

use DDD\Domain\Base\Entities\EntitySet;

/**
 * @property WPSetupStepCompletion[] $elements;
 * @method WPSetupStepCompletion getByUniqueKey(string $uniqueKey)
 * @method WPSetupStepCompletion[] getElements
 * @method WPSetupStepCompletion first
 */
//#[LazyLoadRepo(LazyLoadRepo::INTERNAL_DB, InternalDBWPSetupStepCompletions::class)]
class WPSetupStepCompletions extends EntitySet
{

}
