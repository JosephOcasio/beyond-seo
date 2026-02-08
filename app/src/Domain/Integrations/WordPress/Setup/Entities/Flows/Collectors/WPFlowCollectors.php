<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Setup\Entities\Flows\Collectors;

use App\Domain\Integrations\WordPress\Setup\Repo\InternalDB\Flows\Collectors\InternalDBWPFlowCollectors;
use App\Domain\Integrations\WordPress\Setup\Services\WPFlowCollectorsService;
use DDD\Domain\Base\Entities\EntitySet;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;

/**
 * @method WPFlowCollector[] getElements()
 * @method WPFlowCollector|null first()
 * @method WPFlowCollector|null getByUniqueKey(string $uniqueKey)
 * @property WPFlowCollector[] $elements
 */
#[LazyLoadRepo(repoType: LazyLoadRepo::INTERNAL_DB, repoClass: InternalDBWPFlowCollectors::class)]
class WPFlowCollectors extends EntitySet
{
    public const ENTITY_CLASS = WPFlowCollector::class;
    public const SERVICE_NAME = WPFlowCollectorsService::class;

    /**
     * Get all collectors
     *
     * @return WPFlowCollector[]
     */
    public function getAllCollectorsInstance(): array
    {
        $collectors = $this->getElements();
        $instances = [];

        foreach ($collectors as $collector) {
            $instances[] = new $collector->className($collector->id, $collector->settings);
        }

        return $instances;
    }
}