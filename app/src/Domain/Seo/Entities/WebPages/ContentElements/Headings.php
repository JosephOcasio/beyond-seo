<?php

declare(strict_types=1);

namespace App\Domain\Seo\Entities\WebPages\ContentElements;

use App\Domain\Seo\Entities\WebPages\WebPageContent;

/**
 * @method WebPageContent getParent()
 * @property WebPageContent $parent
 * @property Heading[] $elements;
 * @method Heading getByUniqueKey(string $uniqueKey)
 * @method Heading[] getElements()
 */
class Headings extends WebPageContentElements
{
    /** @var int The default maximum lengths for this content element */
    public const OPTIMAL_CONTENT_LENGTH = 70;

//    /** @var AIGeneratedHeadings AI generated or optimized versions of this Content Element */
//    #[LazyLoad(repoType: LazyLoadRepo::ARGUS)]
//    public AIGeneratedHeadings $aiGeneratedVersions;

    public function getConcatenatedHeadingsDetails(): string
    {
        $concatenatedHeadings = '';
        foreach ($this->getElements() as $heading) {
            $concatenatedHeadings .= $heading->headingType . ': ' .  $heading->content . ', ';
        }

        return $concatenatedHeadings;
    }
}
