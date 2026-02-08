<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Setup\Entities\Flows\Steps;

use App\Domain\Integrations\WordPress\Setup\Repo\InternalDB\Flows\Steps\InternalDBWPFlowSteps;
use App\Domain\Integrations\WordPress\Setup\Services\WPFlowStepsService;
use DDD\Domain\Base\Entities\EntitySet;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;

/**
 * @method WPFlowStep[] getElements()
 * @method WPFlowStep|null first()
 * @method WPFlowStep|null getByUniqueKey(string $uniqueKey)
 * @property WPFlowStep[] $elements
 */
#[LazyLoadRepo(repoType: LazyLoadRepo::INTERNAL_DB, repoClass: InternalDBWPFlowSteps::class)]
class WPFlowSteps extends EntitySet
{
    public const ENTITY_CLASS = WPFlowStep::class;
    public const SERVICE_NAME = WPFlowStepsService::class;

    /**
     * Get next step
     * @param int $stepId
     * @return WPFlowStep|null
     */
    public function getNextStep(int $stepId): ?WPFlowStep
    {
        $steps = $this->getElements();
        /** @var WPFlowStep $currentStep */
        $currentStep = null;
        foreach ($steps as $index => $step) {
            if ($step->id === $stepId) {
                $currentStep = $step;
            }

            $completed = $step->completed;
            if($completed) {
                continue;
            }
            $priority = $step->priority;
            if($currentStep && $currentStep->priority < $priority) {
                return $step;
            }
        }
        return null;
    }
}