<?php

declare(strict_types=1);

namespace App\Presentation\Api\Documentation\Dtos;

use DDD\Infrastructure\Traits\Serializer\Attributes\ExposePropertyInsteadOfClass;
use DDD\Presentation\Base\Dtos\RestResponseDto;
use DDD\Presentation\Base\OpenApi\Document;

#[ExposePropertyInsteadOfClass('document')]
class DocumentationOpenApiResponseDto extends RestResponseDto
{
    public ?Document $document = null;
}