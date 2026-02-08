<?php
declare( strict_types=1 );

namespace App\Presentation\Api\Client\Integrations\WordPress\Dtos;

use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\MetaTags\Social\WPWebPageSocialDescriptionMetaTag;
use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\MetaTags\Social\WPWebPageSocialTitleMetaTag;
use DDD\Presentation\Base\OpenApi\Attributes\Parameter;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class MetaTagsPostRequestDto
 */
class SocialMetaTagsPostRequestDto extends MetaTagsRequestDto
{
	/** @var WPWebPageSocialTitleMetaTag|null $title The social meta-title of the entity */
    #[Parameter(in: Parameter::BODY, required: false)]
	public ?WPWebPageSocialTitleMetaTag $title = null;

	/** @var WPWebPageSocialDescriptionMetaTag|null $description The social meta-description of the entity */
    #[Parameter(in: Parameter::BODY, required: false)]
	public ?WPWebPageSocialDescriptionMetaTag $description = null;
    
    /** @var string|null $selectedImageSource The selected image source identifier */
    #[Parameter(in: Parameter::BODY, required: false)]
    public ?string $selectedImageSource = null;

    /**
     * MetaTagsPostRequestDto constructor.
     * @param RequestStack $requestStack
     */
    public function __construct(RequestStack $requestStack)
    {
        parent::__construct($requestStack);
    }
}