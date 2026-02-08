<?php

declare(strict_types=1);

namespace App\Presentation\Api\Client\Integrations\WordPress\Dtos\Categories;

use App\Domain\Integrations\WordPress\Common\Entities\Categories\WPCategories;
use DDD\Presentation\Base\Dtos\RestResponseDto;
use DDD\Presentation\Base\OpenApi\Attributes\Parameter;

/**
 * Class WPCategoriesGetResponseDto
 */
class WPCategoriesGetResponseDto extends RestResponseDto{

    /** @var WPCategories|null */
    #[Parameter(in: Parameter::RESPONSE, required: false)]
    public ?WPCategories $categories = null;

}
