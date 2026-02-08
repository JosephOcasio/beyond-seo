<?php

declare(strict_types=1);

namespace App\Domain\Seo\Entities\WebPages\ContentElements\AI;

use App\Domain\Seo\Entities\WebPages\ContentElements\WebPageContentElement;
use DDD\Domain\Base\Entities\ObjectSet;

/**
 * @method WebPageContentElement getParent()
 * @property WebPageContentElement $parent
 * @property WebPageContentElement[] $elements;
 * @method WebPageContentElement getByUniqueKey(string $uniqueKey)
 * @method WebPageContentElement[] getElements()
 */
class AIGeneratedContentElements extends ObjectSet
{
    /** @var int The aproximate number of chars used from mainContent as user content */
    public const MAIN_CONTENT_LENGTH_USED_IN_USER_CONTENT = 400;

    public function uniqueKey(): string
    {
        $key = '';
        if ($this->getParent()) {
            $key = $this->getParent()->uniqueKey();
        }
        return self::uniqueKeyStatic($key);
    }

    public function getConcatenatedContentElementsContents(): string
    {
        $concatenatedContentElements = '';
        foreach ($this->getElements() as $contentElement) {
            $concatenatedContentElements .= $contentElement->content . ', ';
        }

        return $concatenatedContentElements;
    }
}
