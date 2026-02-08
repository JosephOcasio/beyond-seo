<?php

declare(strict_types=1);

namespace App\Domain\Seo\Entities\WebPages;

//use App\Domain\AI\Entities\Settings\AIContentSettings\AIContentSettings;
//use App\Domain\Common\Entities\WebContent\HtmlDocument;
use App\Domain\Seo\Entities\WebPages\ContentElements\Headings;
use App\Domain\Seo\Entities\WebPages\ContentElements\Links;
use App\Domain\Seo\Entities\WebPages\ContentElements\MainContent;
use App\Domain\Seo\Entities\WebPages\ContentElements\MetaDescription;
use App\Domain\Seo\Entities\WebPages\ContentElements\Title;
use App\Domain\Seo\Entities\WebPages\ContentElements\WebPageContentElement;
use DDD\Domain\Base\Entities\ValueObject;

//use App\Domain\Seo\Repo\Argus\WebPages\ArgusWebPageContent;

/**
 * @method WebPage getParent()
 * @property WebPage $parent
 */
//#[LazyLoadRepo(LazyLoadRepo::ARGUS, ArgusWebPageContent::class)]
class WebPageContent extends ValueObject
{
    /** @var Title|null The title of the Page */
    public ?Title $title;

    /** @var MetaDescription|null The meta description Page */
    public ?MetaDescription $metaDescription;

    /** @var Headings|null Headings (h1-hx) within content */
    public ?Headings $headings;

    /** @var Links|null Links within content */
    public ?Links $links;

    /** @var MainContent|null Main content containing only tags for text formatting (no navigation, no gdpr blocks, no complex html structure) */
    public ?MainContent $mainContent;

//    /** @var AIContentSettings Settings for AI content writing and content manipulation */
//    public AIContentSettings $aiContentSettings;

    /** @var string Defined the set of html tags that are preserved when extracting website content */
    public const FILTER_TAG_PRESERVATION_MODE = 'TAG_PRESERVATION_MODE';

    /** @var string Removes all tags that are not relevant to inner content of a WebPage */
    public const TAG_PRESERVATION_INNER_CONTENT = 'INNER_CONTENT';

    /** @var string Removes all tags that are not relevant to understand page structure and inner content */
    public const TAG_PRESERVATION_PAGE_STRUCTURE = 'PAGE_STRUCTURE';

    /** @var string Removes all tags that are not relevant to understand page structure and inner content */
    public const TAG_PRESERVATION_FULL = 'FULL';

//    /** @var string|null Content extracted from WebPage */
//    #[HideProperty]
//    public ?HtmlDocument $htmlDocument;

    public function uniqueKey(): string
    {
        $key = '';
        if ($this->getParent()) {
            $key = $this->getParent()->uniqueKey();
        }
//        if (isset($this->aiContentSettings)) {
//            $key .= '_' . $this->aiContentSettings->uniqueKey();
//        }
        return self::uniqueKeyStatic($key);
    }

    /**
     * Calculates the content score of a webpage.
     *
     * The content score is calculated by assigning a score to different elements of the webpage,
     * such as the title, meta tags, links, and main content. The scores are pre-defined and stored
     * in an associative array ($scoreElements), where the keys represent the element names and
     * the values represent the maximum score that can be assigned to each element.
     *
     * The method iterates over each element in the $scoreElements array and checks if the
     * corresponding element's content exists. If the content exists, the method calculates a
     * score for the element based on the length of its content.
     *
     * The final content score is obtained by summing up the scores calculated for each element.
     * The score of each element is determined by multiplying the maximum score assigned to
     * the element by the ratio of the length of its content to the optimal content length
     * defined in the WebPageContentElement class.
     *
     * @return float The content score of the webpage.
     */
    public function getContentScore(): float
    {
        $scoreElements = ['title' => 0.2, 'meta' => 0.2, 'links' => 0.1, 'mainContent' => 0.5];
        $totalScore = 0;
        foreach ($scoreElements as $element => $maxScore) {
            if (isset($this->$element->content)) {
                /** @var WebPageContentElement $scoreElement */
                $scoreElement = $this->$element;
                $totalScore += $maxScore * min(
                        1,
                        strlen($scoreElement->content) / $scoreElement::OPTIMAL_CONTENT_LENGTH
                    );
            }
        }
        return $totalScore;
    }

    /**
     * Checks if the webpage is empty.
     *
     * The webpage is considered empty if none of the following conditions are met:
     * - The title element's content exists and has a non-zero length.
     * - The meta description element's content exists and has a non-zero length.
     * - The main content element's content exists and has a non-zero length.
     * - The links element exists and has a non-zero count.
     * - The headings element exists and has a non-zero count.
     *
     * @return bool True if the webpage is empty, false otherwise.
     */
    public function isEmpty(): bool
    {
        if (isset($this->title->content) && strlen($this->title->content)) {
            return false;
        }
        if (isset($this->metaDescription->content) && strlen($this->metaDescription->content)) {
            return false;
        }
        if (isset($this->mainContent->content) && strlen($this->mainContent->content)) {
            return false;
        }
        if (isset($this->links) && $this->links->count()) {
            return false;
        }
        if (isset($this->headings) && $this->headings->count()) {
            return false;
        }
        return true;
    }
}
