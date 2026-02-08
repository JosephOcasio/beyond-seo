<?php

declare(strict_types=1);

namespace App\Presentation\Api\Documentation\Dtos;

use DDD\Infrastructure\Traits\Serializer\Attributes\ExposePropertyInsteadOfClass;
use DDD\Presentation\Base\Dtos\RequestDto;
use DDD\Presentation\Base\OpenApi\Attributes\Parameter;

#[ExposePropertyInsteadOfClass('document')]
class DocumentationOpenApiRequestDto extends RequestDto
{
    /**
     * @var bool If true, Schema Tags are ommited:
     * Schema Tags are usefull: on Documentation Platofrms such as redocly to document all Entity / DTO schemas
     * Schema Tags are not usefull: On Postman, if you want to use a tag based organisation structure, as it will create empty folders for all schema tags
     */
    #[Parameter(in: Parameter::QUERY, required: false)]
    public ?bool $removeSchemaTags = false;
}