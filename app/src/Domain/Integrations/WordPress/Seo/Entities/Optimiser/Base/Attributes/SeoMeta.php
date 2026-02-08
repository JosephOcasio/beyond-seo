<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Attributes;

use Attribute;

/**
 * Class SeoMeta attribute
 *
 * This attribute class represents a SEO Meta with its name and weight.
 * Can be used for any number of factors, operations or contexts
 */
#[
    Attribute(Attribute::TARGET_CLASS)
]
class SeoMeta
{
    /**
     * SeoMeta constructor.
     *
     * @param string $name The name of the SEO operation.
     * @param float $weight The weight of the SEO operation.
     * @param array|null $features Optional features associated with the SEO operation.
     * @param string|null $description A description of the SEO operation.
     */
    public function __construct(
        public string $name,
        public float $weight,
        public ?array $features = null,
        public ?string $description = null,
    ) {
        // Constructor logic can be added here if needed
    }

    /**
     * Get the localized name of the SEO operation.
     *
     * @return string The translated name
     */
    public function getLocalizedName(): string
    {
        /* translators: This is a title of an SEO operation/factor/context. */
        // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
        return __($this->name, 'beyond-seo');
    }

    /**
     * Get the localized description of the SEO operation.
     *
     * @return string The translated description
     */
    public function getLocalizedDescription(): string
    {
        /* translators: This is a description of an SEO operation/factor/context. */
        // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
        return __($this->description, 'beyond-seo');
    }

    /**
     * Get the name of the SEO operation.
     *
     * @return array The name of the SEO operation.
     */
    public function getFeatures(): array
    {
        return $this->features ?? [];
    }

    /**
     * @param string|null $suffix
     * @return string
     */
    public function getKey(string $suffix = null): string
    {
        return $this->convertToSnakeCase($this->name . ($suffix ? ' ' . $suffix : ''));
    }

    /**
     * Convert a string to snake_case
     * @param string $string
     * @return string
     */
    private function convertToSnakeCase(string $string): string
    {
        // Normalize hyphens and multiple spaces to a single space
        $normalized = preg_replace('/[\s\-.]+/', ' ', trim($string));

        // Split by space, lowercase each word
        $words = explode(' ', $normalized);
        $words = array_map('strtolower', $words);

        return implode('_', $words);
    }

}
