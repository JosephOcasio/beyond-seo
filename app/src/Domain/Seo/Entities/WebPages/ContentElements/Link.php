<?php

declare(strict_types=1);

namespace App\Domain\Seo\Entities\WebPages\ContentElements;

/**
 * @method Links getParent()
 * @property Links $parent
 */
class Link extends WebPageContentElement
{
    /** @var string The href property of the Link */
    public ?string $href;
}
