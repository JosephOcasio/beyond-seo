<?php
declare(strict_types=1);

namespace App\Presentation\Api\Client\Integrations\WordPress\Dtos\Flow;

use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Completions\WPFlowDataCompletion;
use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Steps\WPFlowStep;
use DDD\Presentation\Base\Dtos\RestResponseDto;
use DDD\Presentation\Base\OpenApi\Attributes\Parameter;

/**
 * Class WPFlowStepAndNextStepResponseDto
 */
class WPFlowStepAndNextStepResponseDto extends RestResponseDto
{
    /** @var WPFlowStep The FlowSteps requested */
    #[Parameter(in: Parameter::RESPONSE, required: true)]
    public WPFlowStep $step;

    /** @var WPFlowStep|null The next step */
    #[Parameter(in: Parameter::RESPONSE, required: true)]
    public ?WPFlowStep $nextStep = null;

    /** @var bool $allStepsCompleted ALl steps completed flag */
    #[Parameter(in: Parameter::RESPONSE, required: true)]
    public bool $allStepsCompleted = false;

    /** @var string $evaluationSucceeded Evaluation succeeded message */
    #[Parameter(in: Parameter::RESPONSE, required: false)]
    public string $evaluationSucceeded = '';

    /** @var string $failedAPICallFromResult Failed API call from result message */
    #[Parameter(in: Parameter::RESPONSE, required: false)]
    public string $failedAPICallFromResult = '';

    /** @var WPFlowDataCompletion|null $completion The step completion data */
    #[Parameter(in: Parameter::RESPONSE, required: false)]
    public ?WPFlowDataCompletion $completion = null;
}