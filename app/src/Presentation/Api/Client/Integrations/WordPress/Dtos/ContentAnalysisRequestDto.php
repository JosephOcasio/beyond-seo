<?php
declare( strict_types=1 );

namespace App\Presentation\Api\Client\Integrations\WordPress\Dtos;

use DDD\Presentation\Base\Dtos\RequestDto;
use DDD\Presentation\Base\OpenApi\Attributes\Parameter;

/**
 * Class ContentAnalysisRequestDto
 */
class ContentAnalysisRequestDto extends RequestDto
{
    /** @var int $postId The post-ID */
    #[Parameter(in: Parameter::PATH, required: true)]
    public int $postId;

    /** @var bool If set to true, debug mode will be activated */
    #[Parameter(in: Parameter::QUERY, required: false)]
    public bool $debug = false;
}