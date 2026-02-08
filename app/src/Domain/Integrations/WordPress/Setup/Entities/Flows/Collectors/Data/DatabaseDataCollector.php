<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Setup\Entities\Flows\Collectors\Data;

use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Collectors\WPFlowCollector;
use App\Domain\Integrations\WordPress\Setup\Entities\Flows\WPFlowRequirements;
use App\Domain\Integrations\WordPress\Setup\Services\WPRequirementsService;
use App\Infrastructure\Services\AppService;

/**
 * Class DatabaseDataCollector
 */
class DatabaseDataCollector extends WPFlowCollector
{
    public string $collector = WPFlowRequirements::SETUP_COLLECTOR_DATABASE;

    /** @var bool if collected data gathered be saved */
    public bool $saveCollectedData = false;

    /**
     * @var WPRequirementsService|mixed
     */
    private WPRequirementsService $wpRequirementsService;

    /**
     * DatabaseDataCollector constructor.
     * @param int|null $id
     * @param mixed|null $settings
     */
    public function __construct(?int $id = null, array $settings = [])
    {
        $this->wpRequirementsService = AppService::instance()->getService(WPRequirementsService::class);
        $requirements = WPFlowRequirements::allRequirements();
        $this->createDynamicMethods($requirements);
        parent::__construct($id, $settings);
    }

    public function createDynamicMethods(array $requirements): void
    {
        foreach ($requirements as $requirement) {
            if (!method_exists($this, $requirement)) {
                $this->{$requirement} = function () use ($requirement) {
                    return $this->wpRequirementsService->getRequirement($requirement)?->value;
                };
            }
        }
    }

    public function __call($name, $arguments) {
        if (isset($this->{$name}) && is_callable($this->{$name})) {
            return call_user_func_array($this->{$name}, $arguments);
        }
        return null;
    }

}
