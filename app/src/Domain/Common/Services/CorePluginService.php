<?php
declare(strict_types=1);

namespace App\Domain\Common\Services;

/**
 * Class CorePluginService
 * @package App\Domain\Common\Services
 */
class CorePluginService
{

    /**
     * Get the path of the plugin
     *
     * @param string $pluginName
     * @return string
     */
    public static function getPluginPath(string $pluginName = 'beyond-seo'): string
    {
        return WP_PLUGIN_DIR . '/' . $pluginName;
    }
}
