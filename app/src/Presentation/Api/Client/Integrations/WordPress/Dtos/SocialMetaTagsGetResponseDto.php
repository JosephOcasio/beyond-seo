<?php
declare( strict_types=1 );

namespace App\Presentation\Api\Client\Integrations\WordPress\Dtos;

use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\MetaTags\Social\WPWebPageSocialDescriptionMetaTag;
use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\MetaTags\Social\WPWebPageSocialTitleMetaTag;
use DDD\Presentation\Base\Dtos\RestResponseDto;
use DDD\Presentation\Base\OpenApi\Attributes\Parameter;

/**
 * Class SocialMetaTagsGetResponseDto
 */
class SocialMetaTagsGetResponseDto extends RestResponseDto {

	/** @var WPWebPageSocialTitleMetaTag|null $social_title The meta tag */
	#[Parameter(in: Parameter::RESPONSE, required: false)]
	public ?WPWebPageSocialTitleMetaTag $social_title = null;

    /** @var WPWebPageSocialDescriptionMetaTag|null $social_description The meta tag */
    #[Parameter(in: Parameter::RESPONSE, required: false)]
    public ?WPWebPageSocialDescriptionMetaTag $social_description = null;
    
    /** @var string|null $selected_image_source The selected image source identifier */
    #[Parameter(in: Parameter::RESPONSE, required: false)]
    public ?string $selected_image_source = null;
    
    /** @var string|null $selected_image_url The URL of the selected image */
    #[Parameter(in: Parameter::RESPONSE, required: false)]
    public ?string $selected_image_url = null;

    /**
     * MetaTagsGetResponseDto constructor.
     * @param WPWebPageSocialTitleMetaTag|null $title
     * @param WPWebPageSocialDescriptionMetaTag|null $description
     * @param string|null $selectedImageSource
     * @param string|null $selectedImageUrl
     */
    public function __construct(
        ?WPWebPageSocialTitleMetaTag $title = null, 
        ?WPWebPageSocialDescriptionMetaTag $description = null,
        ?string $selectedImageSource = null,
        ?string $selectedImageUrl = null
    ) {
        $this->social_title = $title;
        $this->social_description = $description;
        $this->selected_image_source = $selectedImageSource;
        $this->selected_image_url = $selectedImageUrl;

        parent::__construct();
    }
}