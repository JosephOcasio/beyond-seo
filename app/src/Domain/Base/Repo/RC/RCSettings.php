<?php

declare(strict_types=1);

namespace App\Domain\Base\Repo\RC;

use App\Domain\Base\Repo\RC\Attributes\RCLoad;
use App\Domain\Base\Repo\RC\Utils\RCApiCacheOperations;
use App\Domain\Base\Repo\RC\Utils\RCApiOperations;
use App\Domain\Base\Repo\RC\Utils\RCCache;
use DDD\Infrastructure\Traits\Serializer\SerializerTrait;

/**
 * Encapsulates all relevant properties for RC loading
 * used by Trait RCLoad
 */
class RCSettings
{
    use SerializerTrait;

    /** @var RCLoad ArgusLoad Attribute instance */
    public RCLoad $rcLoad;

    /** @var bool determines if the current object shall be loaded or not */
    public bool $toBeLoaded = false;

    /** @var bool shall this object be automatically loaded */
    public bool $autoload = false;

    /** @var bool loaded state */
    public bool $isLoaded = false;

    /** @var bool loaded state */
    public bool $isLoadedSuccessfully = false;

    /** @var bool has this object been loaded from cache */
    public bool $loadedFromCache = false;

    /** @var bool has loading been prepared */
    public bool $loadingPrepared = false;

    /** @var int the count of loaded child objects */
    public int $childrenLoaded = 0;

    /** @var int the total count of child that shall be loaded */
    public int $childrenToLoad = 0;

    /** @var float The time after the load times out */
    public float $timeout = 0;

    /** @var RCApiOperations|null Api operations object storing all RC call operations and loading logic */
    public ?RCApiOperations $apiOperations;

    /** @var RCApiCacheOperations|null Api cache operations object storing all RC cache call operations and loading logic */
    public ?RCApiCacheOperations $cacheOperations;

    public function __construct(
        RCLoad $rcLoad = null,
    ) {
        $this->rcLoad = $rcLoad;
    }

    /**
     * @return array|string|null Returns endpoint for LOAD operations
     */
    public function getLoadEndpoint(): array|string|null
    {
        return $this->rcLoad->loadEndpoint;
    }

    /**
     * @return array|string|null Returns endpoint for CREATE operations
     */
    public function getCreateEndpoint(): array|string|null
    {
        return $this->rcLoad->createEndpoint;
    }

    /**
     * @return array|string|null Returns endpoint for UPDATE operations
     */
    public function getUpdateEndpoint(): array|string|null
    {
        return $this->rcLoad->updateEndpoint;
    }

    /**
     * @return array|string|null Returns endpoint for DELETE operations
     */
    public function getDeleteEndpoint(): array|string|null
    {
        return $this->rcLoad->deleteEndpoint;
    }

    /**
     * @return string|array|null Returns endpoint(s) for SYNCHRONIZE operations
     */
    public function getSynchronizeEndpoint(): string|array|null
    {
        return $this->rcLoad->synchronizeEndpoint;
    }

    /**
     * @return int Returns Cache Level
     */
    public function getCacheLevel(): int
    {
        return $this->rcLoad->cacheLevel;
    }

    public function getSerializationMethod(): string
    {
        return $this->rcLoad->serializationMethod;
    }

    public function getCacheTTL(): int
    {
        return $this->rcLoad->cacheTtl;
    }

    /**
     * @return bool Returns if Entity is Cacheable
     */
    public function isCachable(): bool
    {
        return $this->rcLoad->cacheTtl > 0 && $this->rcLoad->cacheLevel != RCCache::CACHELEVEL_NONE;
    }

    public function isToBeLoaded(): bool
    {
        return ($this->toBeLoaded || $this->autoload) && !$this->isLoaded;
    }

    /**
     * initialize API Operations & RCCache Operations
     * @return void
     */
    public function initOperations(): void
    {
        if (!$this->apiOperations) {
            $this->apiOperations = new RCApiOperations();
        }
        if (!$this->cacheOperations) {
            $this->cacheOperations = new RCApiCacheOperations();
        }
    }

    /**
     * @return void
     */
    public function resetOperations()
    {
        $this->apiOperations = null;
        $this->cacheOperations = null;
    }

    public function setToBeLoaded(bool $toBeLoaded)
    {
        $this->toBeLoaded = $toBeLoaded;
        if (!$toBeLoaded) {
            $this->autoload = false;
        }
    }

}