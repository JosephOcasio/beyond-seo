<?php
declare(strict_types=1);

namespace App\Presentation\Api\Client\Integrations\WordPress\Dtos\Flow;

use DDD\Presentation\Base\Dtos\RequestDto;
use DDD\Presentation\Base\OpenApi\Attributes\Parameter;

/**
 * Class WPFlowPostSaveDataCompletionRequestDto
 */
class WPFlowPostSaveDataCompletionRequestDto extends RequestDto
{
    /** @var int $step The step that was requested */
    #[Parameter(in: Parameter::BODY, required: true)]
    public int $stepId;
}