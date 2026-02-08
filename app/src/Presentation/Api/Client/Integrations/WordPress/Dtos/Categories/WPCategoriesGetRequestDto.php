<?php

declare(strict_types=1);

namespace App\Presentation\Api\Client\Integrations\WordPress\Dtos\Categories;

use DDD\Presentation\Base\Dtos\RequestDto;
use DDD\Presentation\Base\OpenApi\Attributes\Parameter;

/**
 * Class WPCategoriesGetRequestDto
 */
class WPCategoriesGetRequestDto extends RequestDto
{
    /** @var string $search */
    #[Parameter(in: Parameter::QUERY, required: true)]
    public string $search;
}
