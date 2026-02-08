<?php

declare(strict_types=1);

namespace App\Domain\Seo\Entities\WebPages\ContentElements;

use App\Domain\Seo\Entities\WebPages\WebPageContent;
use DDD\Domain\Base\Entities\ObjectSet;
use DDD\Infrastructure\Traits\Serializer\Attributes\HideProperty;

/**
 * @method WebPageContent getParent()
 * @property WebPageContent $parent
 * @property WebPageContentElement[] $elements;
 * @method WebPageContentElement getByUniqueKey(string $uniqueKey)
 * @method WebPageContentElement[] getElements()
 */
class WebPageContentElements extends ObjectSet
{

    /** @var int The default maximum lengths for this content element */
    public const OPTIMAL_CONTENT_LENGTH = 70;

    #[HideProperty]
    public int $optimalContentLength;

    /** @var int The number of versions to generate with AI */
    #[HideProperty]
    public int $numberOfVersionsToGenerate = 1;

    public function __construct()
    {
        parent::__construct();
        $this->optimalContentLength = static::OPTIMAL_CONTENT_LENGTH;
    }

    public function uniqueKey(): string
    {
        $key = '';
        if ($this->getParent()) {
            $key = $this->getParent()->uniqueKey();
            foreach ($this->getElements() as $element) {
                if (isset($element->content)) {
                    $key .= md5($element->content);
                }
            }
        }
        return self::uniqueKeyStatic($key);
    }
}
