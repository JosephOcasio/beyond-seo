<?php

declare (strict_types=1);

namespace App\Domain\Base\Repo\RC\Utils;

use App\Domain\Base\Repo\RC\RCEntity;
use DDD\Domain\Base\Entities\Entity;
use DDD\Domain\Base\Entities\ValueObject;
use DDD\Infrastructure\Traits\Serializer\SerializerTrait;

/**
 * Encapsulates an RC API Operation call which can be cached
 */
class RCApiCacheOperation
{
    use SerializerTrait;

    public RCEntity|Entity|ValueObject|null $entity;
    public ?string $id;
    public ?string $function;
    public ?array $params;
    public ?array $generalParams;
    public ?array $results;
    public int $mergelimit = 1;

    public function __construct(RCEntity|Entity|ValueObject &$entity)
    {
        $this->entity = $entity;
    }

    public function handleResponse(&$results)
    {
        if (empty($results)) {
            return;
        }
        if ($rcLoad = $this->entity->getRCSettings()) {
            $this->entity->rcLoadFromCache($results);
        }
    }

    public function uniqueKey()
    {
        return $this->entity->cacheKey();
    }
}