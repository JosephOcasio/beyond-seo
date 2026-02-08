<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Setup\Entities\Flows\Collectors\Data;

use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Collectors\WPFlowCollector;
use App\Domain\Integrations\WordPress\Setup\Entities\Flows\WPFlowRequirements;

/**
 * Class RankingCoachDataCollector
 */
class RankingCoachDataCollector extends WPFlowCollector
{
    public string $collector = WPFlowRequirements::SETUP_COLLECTOR_RANKINGCOACH;

}
