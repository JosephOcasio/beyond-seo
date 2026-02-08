<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Plugin\Dtos;

use RankingCoach\Inc\Core\Plugin\RankingCoachPlugin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * DTO for plugin update check response.
 * - current_version: string
 * - latest_version: string
 * - is_update_available: bool
 */
class PluginUpdateCheckResponseDto
{
    /** @var string Current version of the plugin */
    public string $current_version;

    /** @var string Latest available version of the plugin */
    public string $latest_version;

    /** @var bool Whether an update is available */
    public bool $is_update_available;

    /** @var string URL to download the update */
    public string $update_url;

    /** @var string Response message regarding the update check */
    public string $update_response;

    /**
     * Constructor
     *
     * @param string $current Current version of the plugin
     * @param string $latest Latest available version of the plugin
     * @param bool $available Whether an update is available
     */
    public function __construct(
        string $current, string $latest, bool $available,
        string $update_url = '', string $update_response = ''
    )
    {
        $this->current_version = $current;
        $this->latest_version = $latest;
        $this->is_update_available = $available;
        $this->update_url = $update_url;
        $this->update_response = $update_response;
    }

    /**
     * Convert to array
     *
     * @return array
     */
    public function toArray(): array
    {
        $return = [
            'current_version' => $this->current_version,
            'latest_version' => $this->latest_version,
            'is_update_available' => $this->is_update_available,
        ];
        if(!RankingCoachPlugin::isProductionMode()) {
            $return['update_url'] = $this->update_url;
            $return['update_response'] = $this->update_response;
        }
        return $return;
    }
}