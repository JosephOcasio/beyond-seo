<?php
declare( strict_types=1 );

namespace App\Presentation\Api\Client\Integrations\WordPress\Dtos;

use DDD\Presentation\Base\Dtos\RequestDto;
use DDD\Presentation\Base\OpenApi\Attributes\Parameter;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class MetaTagsRequestDto
 */
class MetaTagsRequestDto extends RequestDto {

	/** @var int $postId The post-ID */
	#[Parameter(in: Parameter::PATH, required: true)]
	public int $postId;

    /**
     * MetaTagsRequestDto constructor.
     * @param RequestStack $requestStack
     */
    public function __construct(RequestStack $requestStack)
    {
        parent::__construct($requestStack);
    }
}