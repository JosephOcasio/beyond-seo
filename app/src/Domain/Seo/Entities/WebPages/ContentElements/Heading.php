<?php

declare(strict_types=1);

namespace App\Domain\Seo\Entities\WebPages\ContentElements;

use DDD\Infrastructure\Validation\Constraints\Choice;

/**
 * @method Headings getParent()
 * @property Headings $parent
 */
class Heading extends WebPageContentElement
{
    /** @var int The default optimal lengths for this content element */
    public const OPTIMAL_CONTENT_LENGTH = 70;

    /** @var string H1 heading */
    public const H1 = 'H1';

    /** @var string H1 heading */
    public const H2 = 'H2';

    /** @var string H1 heading */
    public const H3 = 'H3';

    /** @var string H1 heading */
    public const H4 = 'H4';

    /** @var string H1 heading */
    public const H5 = 'H5';

    /** @var string H1 heading */
    public const H6 = 'H6';

    /** @var int Position in content for sorting */
    public int $positionInDom;

    /**
     * @var string The type of the Heading
     */
    #[Choice([self::H1, self::H2, self::H3, self::H4, self::H5, self::H6])]
    public string $headingType;
}
