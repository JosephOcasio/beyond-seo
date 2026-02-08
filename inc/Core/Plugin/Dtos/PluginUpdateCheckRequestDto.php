<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Plugin\Dtos;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Empty DTO for plugin update check request.
 * The endpoint does not require any input payload.
 */
class PluginUpdateCheckRequestDto
{
    /**
     * Create DTO from array (no-op, kept for interface consistency)
     *
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data = []): self
    {
        return new self();
    }

    /**
     * Validate request (always valid as no payload is required)
     *
     * @return array Empty array when valid
     */
    public function validate(): array
    {
        return [];
    }

    /**
     * Convert to array (no payload)
     *
     * @return array
     */
    public function toArray(): array
    {
        return [];
    }
}