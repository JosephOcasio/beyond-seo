<?php

declare(strict_types=1);

namespace App\Domain\Base\Repo\RC\Enums;

final class RCApiOperationType
{
    public const LOAD = 'LOAD';
    public const UPDATE = 'UPDATE';
    public const DELETE = 'DELETE';
    public const CREATE = 'CREATE';
    public const PATCH = 'PATCH';
    public const SYNCHRONIZE = 'SYNCHRONIZE';

    /**
     * Get all available operation types
     *
     * @return array<string>
     */
    public static function getAll(): array
    {
        return [
            self::LOAD,
            self::UPDATE,
            self::DELETE,
            self::CREATE,
            self::PATCH,
            self::SYNCHRONIZE,
        ];
    }

    /**
     * Check if the given value is a valid operation type
     *
     * @param string $value
     * @return bool
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::getAll(), true);
    }
}
