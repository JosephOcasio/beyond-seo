<?php

declare (strict_types=1);

namespace App\Domain\Base\Repo\RC\Utils;

/**
 * Multiple RCCache Calls are combined into a single Call e.g. to Redis cluster
 * and by this latency is reduced.
 * All RCCache Calls are encapsulated into Atomic RCCache operations which are then sent at once
 *
 */
class RCApiCacheOperations
{
    /** @var RCApiCacheOperation[]|null */
    public ?array $operations;

    /** @var RCApiCacheOperation[]|null */
    public ?array $operationsByUniqueKey;

    public function __construct()
    {
        $this->operations = [];
        $this->operationsByUniqueKey = [];
    }

    /**
     * @param RCApiCacheOperation $operation
     */
    public function addOperation(RCApiCacheOperation &$operation)
    {
        $uniqueKey = $operation->uniqueKey();
        if (isset($this->operations_unique_key[$uniqueKey])) // keep sure, we are not adding duplicates
        {
            return;
        }
        $this->operationsByUniqueKey[$uniqueKey] = $operation;
        $this->operations[] = $operation;
    }

    /**
     * Loads all cache operation at once and sets results to RC Objects
     * @return void
     */
    public function execute(): void
    {
        if (!count($this->operations)) {
            return;
        }
        $keys = [];
        foreach ($this->operationsByUniqueKey as $key => $value) {
            $keys[] = $key;
        }

        $multiResult = RCCache::getMulti($keys);
        if (!$multiResult) {
            return;
        }
        foreach ($multiResult as $key => $result) {
            if (isset($this->operationsByUniqueKey[$key])) {
                $this->operationsByUniqueKey[$key]->handleResponse($result);
            }
        }
    }
}