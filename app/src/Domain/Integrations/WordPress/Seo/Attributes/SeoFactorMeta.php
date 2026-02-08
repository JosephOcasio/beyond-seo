<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Attributes;

use Attribute;
use DDD\Infrastructure\Validation\Constraints\Choice;

/**
 * Attribute to mark a class as a SEO factor meta
 *
 * @package App\Domain\Integrations\WordPress\Seo\Attributes
 */
#[Attribute(Attribute::TARGET_CLASS)]
class SeoFactorMeta
{

    public const AVAILABLE_FREE = 'free';
    public const AVAILABLE_PRO = 'pro';
    public const AVAILABLE_NOT = 'not';

    public const AVAILABLE = [
        self::AVAILABLE_FREE,
        self::AVAILABLE_PRO
    ];

    /**
     * The availability of the factor
     */
    #[Choice(options: [self::AVAILABLE_FREE, self::AVAILABLE_PRO])]
    public string $availability;

    /**
     * SeoFactor short description
     */
    public ?string $description = null;


    /**
     * SeoFactorMeta constructor.
     *
     * @param string $availability
     * @param string|null $description
     */
    public function __construct(
        string $availability = self::AVAILABLE_FREE,
        ?string $description = null
    ){
        $this->description = $description;
        $this->availability = $availability;
    }

    /**
     * SeoFactorMeta constructor.
     *
     * @return string
     */
    public function getShortDescription(): string
    {
        return $this->description;
    }
}