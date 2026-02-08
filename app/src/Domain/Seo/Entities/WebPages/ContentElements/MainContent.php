<?php

declare(strict_types=1);

namespace App\Domain\Seo\Entities\WebPages\ContentElements;

use App\Domain\Seo\Entities\WebPages\WebPageContent;

/**
 * @method WebPageContent getParent()
 * @property WebPageContent $parent
 */
class MainContent extends WebPageContentElement
{
    /** @var int The default optimal lengths for this content element */
    public const OPTIMAL_CONTENT_LENGTH = 2000;

//    /** @var AIGeneratedMainContents AI generated or optimized versions of this Content Element */
//    #[LazyLoad(repoType: LazyLoadRepo::ARGUS)]
//    public AIGeneratedMainContents $aiGeneratedVersions;
}
