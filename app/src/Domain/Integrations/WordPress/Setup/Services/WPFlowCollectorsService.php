<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Setup\Services;

use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Collectors\WPFlowCollector;
use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Collectors\WPFlowCollectors;
use App\Domain\Integrations\WordPress\Setup\Repo\InternalDB\Flows\Collectors\InternalDBWPFlowCollector;
use App\Domain\Integrations\WordPress\Setup\Repo\InternalDB\Flows\Collectors\InternalDBWPFlowCollectors;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use DDD\Infrastructure\Services\Service;
use Doctrine\ORM\Mapping\MappingException;
use Psr\Cache\InvalidArgumentException;
use ReflectionException;

/**
 * Class WPFlowCollectorsService
 */
class WPFlowCollectorsService extends Service
{
    public const DEFAULT_ENTITY_CLASS = WPFlowCollector::class;

    /**
     * Get all collectors
     *
     * @return WPFlowCollectors|null
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws MappingException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     */
    public function getCollectors(): ?WPFlowCollectors
    {
        $collectorsRepo = new InternalDBWPFlowCollectors();
        return $collectorsRepo->getAllCollectors();
    }

    /**
     * Get collector by id
     *
     * @param int $id
     * @return WPFlowCollector|null
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     */
    public function getCollectorById(int $id): ?WPFlowCollector
    {
        $collectorsRepo = new InternalDBWPFlowCollector();
        return $collectorsRepo->getCollectorById($id);
    }
}