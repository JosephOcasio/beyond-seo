<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\ContentAnalysis\Keywords;

use App\Domain\Common\Entities\Keywords\Keyword;

/**
 * Class WPAdditionalKeywords
 */
class WPAdditionalKeywords extends WPKeywords
{
    /**
     * @var Keyword[] $elements
     */
    public array $elements = [];
}