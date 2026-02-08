<?php
declare(strict_types=1);

namespace App\Presentation\Api\Client\Integrations\WordPress\Dtos;

use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Completions\WPFlowDataCompletion;
use DDD\Presentation\Base\Dtos\RequestDto;
use DDD\Presentation\Base\OpenApi\Attributes\Parameter;

/**
 * Class WPFlowStepCompletionRequestDto
 */
class WPFlowStepCompletionRequestDto extends RequestDto
{
    /** @var WPFlowDataCompletion $completion The step to save */
    #[Parameter(in: Parameter::BODY, required: true)]
    public WPFlowDataCompletion $completion;
}