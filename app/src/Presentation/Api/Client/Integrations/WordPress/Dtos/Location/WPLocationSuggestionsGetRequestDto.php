<?php

declare(strict_types=1);

namespace App\Presentation\Api\Client\Integrations\WordPress\Dtos\Location;

use DDD\Presentation\Base\Dtos\RequestDto;
use DDD\Presentation\Base\OpenApi\Attributes\Parameter;

/**
 * Class WPLocationSuggestionsGetRequestDto
 */
class WPLocationSuggestionsGetRequestDto extends RequestDto
{
    /** @var string $address */
    #[Parameter(in: Parameter::BODY, required: true)]
    public string $address;

    /** @var string $country */
    #[Parameter(in: Parameter::BODY, required: true)]
    public string $country;

    /** @var string|null $city */
    #[Parameter(in: Parameter::BODY, required: false)]
    public ?string $city = null;

    /** @var string|null $zip */
    #[Parameter(in: Parameter::BODY, required: false)]
    public ?string $zip = null;

    /** @var bool|null $allowAnyLocationType */
    #[Parameter(in: Parameter::BODY, required: false)]
    public ?bool $allowAnyLocationType = false;
}