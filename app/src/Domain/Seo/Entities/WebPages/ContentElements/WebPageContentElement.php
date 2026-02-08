<?php

declare(strict_types=1);

namespace App\Domain\Seo\Entities\WebPages\ContentElements;

use App\Domain\Seo\Entities\WebPages\WebPageContent;
use DDD\Domain\Base\Entities\ValueObject;
use DDD\Infrastructure\Traits\Serializer\Attributes\HideProperty;

/**
 * @method WebPageContent getParent()
 * @property WebPageContent $parent
 */
class WebPageContentElement extends ValueObject
{
    /** @var string Title page element */
    public const TITLE = 'TITLE';

    /** @var string Meta description element */
    public const META_DESCRIPTION = 'META_DESCRIPTION';

    /** @var string Headings elements, h1-h6 */
    public const HEADINGS = 'HEADINGS';

    /** @var string Main content element of the document */
    public const MAIN_CONTENT = 'MAIN_CONTENT';

    /** @var string Main content element of the document */
    public const LINKS = 'LINKS';

    /** @var string[] All available WebPageContentElement types */
    public const CONTENT_ELEMENTS = [self::TITLE, self::META_DESCRIPTION, self::HEADINGS, self::MAIN_CONTENT, self::LINKS];

    /** @var string The content of the element */
    public string $content;

    /** @var int The default maximum lengths for this content element */
    public const OPTIMAL_CONTENT_LENGTH = 70;

    /** @var int The default optimal lengths for this content element */
    #[HideProperty]
    public int $optimalContentLength;

    /** @var int The number of versions to generate with AI */
    #[HideProperty]
    public int $numberOfVersionsToGenerate = 3;

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
            if (isset($this->content)) {
                $key .= $this->content;
            }
        }
        return self::uniqueKeyStatic(md5($key));
    }
}
