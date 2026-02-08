<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Setup\Entities\Flows\Completions;

use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Collectors\WPFlowCollector;
use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Questions\WPFlowQuestion;
use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Steps\WPFlowStep;
use App\Domain\Integrations\WordPress\Setup\Repo\InternalDB\Flows\Completions\InternalDBWPFlowCompletion;
use App\Domain\Integrations\WordPress\Setup\Repo\InternalDB\Flows\Steps\InternalDBWPFlowStep;
use DDD\Domain\Base\Entities\Entity;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoad;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;

/**
 * Class WPFlowDataCollector
 */
#[LazyLoadRepo(LazyLoadRepo::INTERNAL_DB, InternalDBWPFlowCompletion::class)]
class WPFlowDataCompletion extends Entity
{
    /** @var int The id of the FlowStep */
    public int $stepId;

    /** @var WPFlowStep|null The step */
    #[LazyLoad(repoType: LazyLoadRepo::INTERNAL_DB, repoClass: InternalDBWPFlowStep::class)]
    public ?WPFlowStep $step = null;

    /** @var int|null The id of the FlowCollector */
    public ?int $collectorId = null;

    // The issue in this case is sometime $collectorId is null,
    // meaning that the lazyload will fail, this avoids retrieving data programmatically
    /** @var WPFlowCollector|null The collector */
    ##[LazyLoad(repoType: LazyLoadRepo::INTERNAL_DB, repoClass: InternalDBWPFlowCollector::class)]
    public ?WPFlowCollector $collector = null;

    /** @var int|null The ID of the question being answered */
    public ?int $questionId = null;

    /** @var WPFlowQuestion|null The question that was answered */
    #[LazyLoad(repoType: LazyLoadRepo::INTERNAL_DB)]
    public ?WPFlowQuestion $question = null;

    /** @var string The answer */
    public string $answer;

    /** @var WPFlowEvaluateData|null The data */
    public ?WPFlowEvaluateData $data = null;

    /** @var int|null The time of completion */
    public ?int $timeOfCompletion = null;

    /** @var bool The completion status */
    public bool $isCompleted = false;

    /**
     * returns an individual unique key for current entity
     * @return string
     */
    public function uniqueKey(): string
    {
        $key = parent::uniqueKey();
        return md5(($this->collectorId ?? '') . '-' . $this->stepId . '-' . ($this->questionId ?? '') . '-' . $key);
    }
}
