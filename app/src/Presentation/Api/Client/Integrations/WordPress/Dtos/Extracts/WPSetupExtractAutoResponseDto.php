<?php
declare(strict_types=1);

namespace App\Presentation\Api\Client\Integrations\WordPress\Dtos\Extracts;

use App\Domain\Integrations\WordPress\Setup\Entities\Extracts\WPSetupExtractAuto;
use DDD\Presentation\Base\Dtos\RestResponseDto;
use DDD\Presentation\Base\OpenApi\Attributes\Parameter;

/**
 * Class WPSetupExtractAutoResponseDto
 */
class WPSetupExtractAutoResponseDto extends RestResponseDto
{
    /** @var WPSetupExtractAuto|null */
    #[Parameter(in: Parameter::RESPONSE, required: true)]
    public ?WPSetupExtractAuto $extracted = null;
}