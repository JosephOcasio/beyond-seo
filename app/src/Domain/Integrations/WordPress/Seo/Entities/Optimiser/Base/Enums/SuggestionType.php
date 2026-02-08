<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Enums;

/**
 * Class SuggestionType
 *
 * This class defines various suggestion types that can be encountered during SEO analysis.
 */
class SuggestionType
{
    public const INFO = 'info';
    public const IMPLEMENTATION = 'implementation';
    public const OPTIMIZATION = 'optimization';
    public const WARNING = 'warning';
    public const ERROR = 'error';
    public const NOTICE = 'notice';

    /**
     * Get all available suggestion types
     *
     * @return array<string>
     */
    public static function getAll(): array
    {
        return [
            self::INFO,
            self::IMPLEMENTATION,
            self::OPTIMIZATION,
            self::WARNING,
            self::ERROR,
            self::NOTICE,
        ];
    }

    /**
     * Check if the given value is a valid suggestion type
     *
     * @param string $value
     * @return bool
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::getAll(), true);
    }

    /**
     * Get the description for a suggestion type
     *
     * @param string $type
     * @return string|null
     */
    public static function getDescription(string $type): ?string
    {
        return match ($type) {
            self::INFO => __('Informational suggestion', 'beyond-seo'),
            self::IMPLEMENTATION => __('Implementation suggestion', 'beyond-seo'),
            self::OPTIMIZATION => __('Optimization suggestion', 'beyond-seo'),
            self::WARNING => __('Warning suggestion', 'beyond-seo'),
            self::ERROR => __('Error suggestion', 'beyond-seo'),
            self::NOTICE => __('Notice suggestion', 'beyond-seo'),
            default => null,
        };
    }
}
