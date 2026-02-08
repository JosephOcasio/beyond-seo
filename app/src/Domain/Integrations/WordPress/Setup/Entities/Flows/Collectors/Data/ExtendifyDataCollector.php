<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Setup\Entities\Flows\Collectors\Data;

use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Collectors\WPFlowCollector;
use App\Domain\Integrations\WordPress\Setup\Entities\Flows\WPFlowRequirements;

/**
 * Class ExtendifyDataCollector
 */
class ExtendifyDataCollector extends WPFlowCollector
{

    public string $collector = WPFlowRequirements::SETUP_COLLECTOR_EXTENDIFY;

    public const WP_OPTIONS_KEY = 'extendify_user_selections';

    public function __construct(?int $id = null, array $settings = [])
    {
        parent::__construct($id, array_merge($settings, get_option(self::WP_OPTIONS_KEY, [])));
    }

    /**
     * @return string|null
     */
    public function businessName(): ?string
    {
        return $this->getSetting('state.siteInformation.title', null);
    }

    /**
     * @return string|null
     */
    public function businessDescription(): ?string
    {
        return $this->getSetting('state.businessInformation.description', null);
    }
}
