<?php

declare(strict_types=1);

namespace App\Domain\Seo\Entities\WebPages\ContentElements;

use App\Domain\Seo\Entities\WebPages\ContentElements\AI\AIGeneratedTitles;
use App\Domain\Seo\Entities\WebPages\WebPageContent;

/**
 * @method WebPageContent getParent()
 * @property WebPageContent $parent
 */
class Title extends WebPageContentElement
{
    /** @var int The default optimal lengths for this content element */
    public const OPTIMAL_CONTENT_LENGTH = 70;

    /** @var AIGeneratedTitles AI generated or optimized versions of this Content Element */
    public AIGeneratedTitles $aiGeneratedVersions;
}
