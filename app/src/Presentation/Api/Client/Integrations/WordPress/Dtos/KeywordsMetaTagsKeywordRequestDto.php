<?php
declare( strict_types=1 );

namespace App\Presentation\Api\Client\Integrations\WordPress\Dtos;

use DDD\Presentation\Base\OpenApi\Attributes\Parameter;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class KeywordsMetaTagsKeywordRequestDto
 */
class KeywordsMetaTagsKeywordRequestDto extends MetaTagsRequestDto
{
	/** @var string $keyword The keyword to add/delete */
    #[Parameter(in: Parameter::BODY, required: true)]
	public string $keyword;

    /**
     * MetaTagsPostRequestDto constructor.
     * @param RequestStack $requestStack
     */
    public function __construct(RequestStack $requestStack)
    {
        parent::__construct($requestStack);
    }
}