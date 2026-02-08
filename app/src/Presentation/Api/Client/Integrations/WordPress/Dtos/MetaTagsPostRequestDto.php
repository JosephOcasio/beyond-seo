<?php
declare( strict_types=1 );

namespace App\Presentation\Api\Client\Integrations\WordPress\Dtos;

use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\MetaTags\Tags\WPWebPageDescriptionMetaTag;
use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\MetaTags\Tags\WPWebPageKeywordsMetaTag;
use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\MetaTags\Tags\WPWebPageTitleMetaTag;
use DDD\Presentation\Base\OpenApi\Attributes\Parameter;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class MetaTagsPostRequestDto
 */
class MetaTagsPostRequestDto extends MetaTagsRequestDto
{
	/** @var WPWebPageTitleMetaTag|null $title The meta-title of the entity */
    #[Parameter(in: Parameter::BODY, required: false)]
	public ?WPWebPageTitleMetaTag $title = null;

	/** @var WPWebPageDescriptionMetaTag|null $description The meta-description of the entity */
    #[Parameter(in: Parameter::BODY, required: false)]
	public ?WPWebPageDescriptionMetaTag $description = null;

	/** @var WPWebPageKeywordsMetaTag|null $keywords The keywords of the entity */
    #[Parameter(in: Parameter::BODY, required: false)]
	public ?WPWebPageKeywordsMetaTag $keywords = null;

    /**
     * MetaTagsPostRequestDto constructor.
     * @param RequestStack $requestStack
     */
    public function __construct(RequestStack $requestStack)
    {
        parent::__construct($requestStack);
    }
}