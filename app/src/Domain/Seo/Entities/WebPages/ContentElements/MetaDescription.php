<?php

declare(strict_types=1);

namespace App\Domain\Seo\Entities\WebPages\ContentElements;

use App\Domain\Seo\Entities\WebPages\ContentElements\AI\AIGeneratedMetaDescriptions;
use App\Domain\Seo\Entities\WebPages\WebPageContent;

/**
 * @method WebPageContent getParent()
 * @property WebPageContent $parent
 */
class MetaDescription extends WebPageContentElement
{
    /** @var int The default optimal lengths for this content element */
    public const OPTIMAL_CONTENT_LENGTH = 160;

    /** @var AIGeneratedMetaDescriptions AI generated or optimized versions of this Content Element */
    public AIGeneratedMetaDescriptions $aiGeneratedVersions;
}
