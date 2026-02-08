<?php

declare(strict_types=1);

namespace App\Domain\Base\Repo\RC\Traits;

use App\Domain\Base\Repo\RC\Attributes\RCLoad;
use App\Domain\Base\Repo\RC\Enums\RCApiOperationType;
use App\Domain\Base\Repo\RC\RCEntity;
use App\Domain\Base\Repo\RC\RCSettings;
use App\Domain\Base\Repo\RC\Utils\RCApiCacheOperation;
use App\Domain\Base\Repo\RC\Utils\RCApiCacheOperations;
use App\Domain\Base\Repo\RC\Utils\RCApiOperation;
use App\Domain\Base\Repo\RC\Utils\RCApiOperations;
use App\Domain\Base\Repo\RC\Utils\RCCache;
use App\Domain\Base\Repo\RC\Utils\RCLoadingParameters;
use DDD\Domain\Base\Entities\DefaultObject;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;
use DDD\Domain\Base\Entities\ParentChildrenTrait;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use DDD\Infrastructure\Exceptions\NotFoundException;
use DDD\Infrastructure\Exceptions\UnauthorizedException;
use DDD\Infrastructure\Traits\AfterConstruct\Attributes\AfterConstruct;
use Doctrine\ORM\NonUniqueResultException;
use JsonException;
use Psr\Cache\InvalidArgumentException;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * encapsules all RC Loading operations
 */
trait RCLoadTrait
{
    use ParentChildrenTrait;

    /** @var RCSettings|null an instance of the RCSettings attribute holding all loading relevant information and procedures related to RCloading */
    protected ?RCSettings $rcSettings;

    /**
     * @var array Properties set here are loaded always on any load operation
     * as the values are static, we store these static associated to the class where setPropertiesToLoadAlways is called
     */
    protected static array $propertiesToLoadAlways = [];

    /**
     * creates RCLoad attribute instance
     * @return void
     */
    #[AfterConstruct]
    public function initRCLoad()
    {
        $this->getRCSettings();
    }

    public function getRCSettings(): RCSettings
    {
        if ($this->rcSettings ?? null) {
            return $this->rcSettings;
        }
        /** @var RCLoad $rcLoadInstance */
        $rcLoadInstance = self::getAttributeInstance(RCLoad::class);
        if ($rcLoadInstance) {
            $this->rcSettings = new RCSettings($rcLoadInstance);
        }
        else {
            $this->rcSettings = new RCSettings(new RCLoad());
        }
        return $this->rcSettings;
    }

    /**
     * Initiates loading for current object and children
     * @param bool $useRCEntityCache if true, RC entity cached is used (lifetime and cachetype is defined on RCLoad attribute)
     * @param bool $useApiACallCache if true, a simple cache on call level is used, that simply caches the result of a call using a hash of the call data as cache key
     * @param bool $displayCall if true, the loading calls and parameters are outputed by echo
     * @param bool $displayResponse if true, the loading response is outputed by echo
     * @param string|null $jobLabel job label for logging purposes
     * @param bool $autoloadCurrentObject if true, current object is set to be loaded by default, otherwise the method will not set the current object to be loaded by default
     * @param int|null $apiCallCacheLifetime the lifetime of the simple API call cache
     * @return void
     * @throws InternalErrorException
     */
    public function rcLoad(
        bool $useRCEntityCache = true,
        bool $useApiACallCache = true,
        bool $displayCall = false,
        bool $displayResponse = false,
        bool $autoloadCurrentObject = true,
        int  $apiCallCacheLifetime = null,
    ): void {
        if (RCLoad::$deactivateRCCache) {
            $useRCEntityCache = false;
            $useApiACallCache = false;
        }
        if (isset(static::$propertiesToLoadAlways[static::class])) {
            $this->setPropertiesToLoad(...static::$propertiesToLoadAlways[static::class]);
        }
        $this->rcSettings->loadingPrepared = false;
        $this->rcSettings->isLoaded = false;
        $this->rcSettings->resetOperations();
        $this->rcPrepareLoad($useRCEntityCache, $autoloadCurrentObject);
        if ($this->rcSettings->apiOperations) {
            try {
                $this->rcSettings->apiOperations->execute(
                    $displayCall,
                    $displayResponse,
                    $useApiACallCache,
                    true,
                    RCApiOperationType::LOAD,
                    $this->rcSettings?->timeout ?? 0,
                );
            } catch (InternalErrorException $e) {
                //if execution failes, clean static properties and then throw the error further
                $this->rcSettings->resetOperations();
                throw $e;
            }
        }
        $this->rcSettings->resetOperations();
        // in special cases, like Domain, no basis properties have to be loaded, just children. in this case we set it as
        // loaded and call the callback function, otherwise e.g. db caching would not be executed
        if (!$this->rcSettings->loadedFromCache && $this->rcSettings->isToBeLoaded(
            ) && !$this->rcSettings->getLoadEndpoint()) {
            $this->handleLoadResponse();
        }
        $this->rcSettings->resetOperations();
        return;
    }

    /**
     * Initializes API operations, constructs api Cache operations and loads them instantly
     * Constructs API operations
     * @param bool $forceCacheRefresh
     * @return $this
     */
    public function rcPrepareLoad(bool $useRCEntityCache = true, bool $autoloadCurrentObject = true): void
    {
        if ($this->rcSettings->isLoaded || $this->rcSettings->loadingPrepared) {
            return;
        } //already loaded
        $this->rcSettings->initOperations();

        $this->rcSettings->toBeLoaded = $autoloadCurrentObject;
        $null = null;
        //echo json_encode($this->getObjectStructure());die();
        // construct and load cached operations
        $this->constructApiOperations([], $useRCEntityCache, $null, $this->rcSettings->cacheOperations, true);
        $this->rcSettings->cacheOperations->execute();
        // construct api operations
        $this->constructApiOperations([], $useRCEntityCache, $this->rcSettings->apiOperations, $null, false);
        $this->rcSettings->loadingPrepared = true;
    }

    /**
     * Constructs recursively api operations by checking current object if it is to be laoded
     * if yes, checks if object has all necessities to be loaded (loadEndpoint and getLoadPayload())
     * if all prequisites are satisfied an API operation (either a cache or a normal api operation) is created for this object
     * the same procedure is done recursively for the object's children
     * @param array $path
     * @param bool $useRCEntityCache
     * @param RCApiOperations|null $apiOperations
     * @param RCApiCacheOperations|null $cacheOperations
     * @param bool $executeForCachedElements
     * @return void
     */
    public function constructApiOperations(
        array                $path = [],
        bool                 $useRCEntityCache = true,
        RCApiOperations      &$apiOperations = null,
        RCApiCacheOperations &$cacheOperations = null,
        bool                 $executeForCachedElements = true
    ) {
        if (isset($path[spl_object_hash($this)])) {
            return;
        }
        $path[spl_object_hash($this)] = true;
        //echo 'constructApiOperations: ' . $this->cacheKey() . "<br />";
        if ($executeForCachedElements) {
            if ($this->rcSettings->isCachable() && $useRCEntityCache && $this->rcSettings->isToBeLoaded()) {
                // For Cachelevel Memory, loading is instant and we do not need to commission multiple elements at once
                if (($this->rcSettings->getCacheLevel(
                    ) == RCCache::CACHELEVEL_MEMORY || $this->rcSettings->getCacheLevel(
                    ) == RCCache::CACHELEVEL_MEMORY_AND_DB)) {
                    $cached = RCCache::get($this->cacheKey(), RCCache::CACHELEVEL_MEMORY);
                    //echo $this->cacheKey() . ' try load ' . $cached->loaded. '<br />';
                    //echo $this->cacheKey() . ' try to load from cache<br />';
                    if ($cached && $cached->loaded) {
                        if ($cached->validUntil < time() || !$cached->data) {
                            RCCache::delete($this->cacheKey());
                        } else {
                            // this must be set before, since in loadFromObject handleloadingCallback can be triggered already
                            $this->rcLoadFromCache($cached->data);
                            unset($cached);
                        }
                    }
                }
                // on CACHELEVEL_DB or CACHELEVEL_MEMORY_AND_DB (in our case Redis) we commission multiple cache keys in order to reduce
                // latency and we load all elements at once
                if (!$this->rcSettings->loadedFromCache && ($this->rcSettings->getCacheLevel(
                        ) == RCCache::CACHELEVEL_DB || $this->rcSettings->getCacheLevel(
                        ) == RCCache::CACHELEVEL_MEMORY_AND_DB)) {
                    $apiOperation = new RCApiCacheOperation($this);
                    $cacheOperations->addOperation($apiOperation);
                }
            }
        }
        //Recusrive construct operations for children
        if ($this->children && $this->children->count()) {
            foreach ($this->getChildren() as $child) {
                /** @var RCEntity $child */
                if (isset($child->rcSettings) && ($child->rcSettings->isToBeLoaded(
                        ) || $child->rcSettings->isLoaded) && !$executeForCachedElements) {
                    $this->rcSettings->childrenToLoad++;
                }
                if (method_exists($child, 'constructApiOperations')) {
                    $child->constructApiOperations(
                        $path,
                        $useRCEntityCache,
                        $apiOperations,
                        $cacheOperations,
                        $executeForCachedElements
                    );
                }
            }
        }
        // operations for object itself: loading of the object itself has to be completed after loading of the children
        if (!$executeForCachedElements && $this->rcSettings->isToBeLoaded(
            ) && ($loadEndpoint = $this->rcSettings->getLoadEndpoint()) && ($loadPayload = $this->getLoadPayload(
            ))) {
            //echo "NOT LOADED from cache: ". $this->cacheKey()."\n <br /
            $loadEndpoints = [];
            // load endpoints can either be a string or an array of endpoints
            $loadEndpoints = is_array($loadEndpoint) ? $loadEndpoint : [$loadEndpoint];
            foreach ($loadEndpoints as $loadEndpoint) {
                // if load endpoints are an array, they can contain either for endpoint an array e.g.
                //  #[RCLoad(loadEndpoint: [
                //    ['GET:/rc-gmb/performance/daily-metrics' => ['query' => ['metricType' => InsightsDateValueSequence::METRIC_BUSINESS_IMPRESSIONS_DESKTOP_MAPS]]],
                //    ['GET:/rc-gmb/performance/daily-metrics' => ['query' => ['metricType' => InsightsDateValueSequence::METRIC_BUSINESS_IMPRESSIONS_DESKTOP_SEARCH]]],
                // ])];
                // or simple strings e.g.
                // #[RCLoad(loadEndpoint: ['GET:/rc-gmb/attributes/get', 'GET:/rc-gmb/attributes/list'])
                // in case of each element beeing an array, we can have additional parameters passed there that are
                // merged with parameters form getLoadPayload
                $cacheKeyAppendix = '';
                $currentLoadPayload = $loadPayload;
                if (is_array($loadEndpoint)) {
                    [$loadEndpoint, $additionalLoadPayload] = $loadEndpoint;
                    $currentLoadPayload = array_merge_recursive($loadPayload, $additionalLoadPayload);
                    $loadEndpoint = RCApiOperation::getEndpointWithPathParametersApplied(
                        $loadEndpoint,
                        $currentLoadPayload
                    );
                    $cacheKeyAppendix = $loadEndpoint . '_' . md5(json_encode($additionalLoadPayload));
                } else {
                    $loadEndpoint = RCApiOperation::getEndpointWithPathParametersApplied(
                        $loadEndpoint,
                        $currentLoadPayload
                    );
                    $cacheKeyAppendix = $loadEndpoint;
                }

                $timeout = $this->rcSettings->timeout ?? 0;
                $apiOperation = new RCApiOperation(
                    $this,
                    $this->cacheKey() . $cacheKeyAppendix,
                    $loadEndpoint,
                    $currentLoadPayload,
                    $timeout
                );
                $apiOperations->addOperation($apiOperation);
            }
        }
        return;
    }

    /**
     * Populates current object from cache load
     * @param $cacheObject
     * @param $setNotExistingProperties
     * @param $considerPropertyDocCommentRules
     * @return void
     */
    public function rcLoadFromCache(
        mixed &$cacheObject,
        bool $setNotExistingProperties = true,
        bool $considerPropertyDocCommentRules = false
    ): void {
        $this->rcSettings->loadedFromCache = true;
        if (!($this instanceof DefaultObject)) {
            return;
        }
        $unserialized = $this->rcSettings->getSerializationMethod() == RCLoad::SERIALIZATION_METHOD_TO_OBJECT ? json_decode(
            $cacheObject
        ) : unserialize(
            $cacheObject
        );
        if (!$unserialized) {
            return;
        }
        if (!is_object($unserialized)) {
            return;
        }
        //$this->setPropertiesFromObject($cacheObject);
        if ($this->rcSettings->getSerializationMethod() == RCLoad::SERIALIZATION_METHOD_TO_OBJECT) {
            $this->setPropertiesFromObject($unserialized);
        } else {
            $this->setPropertiesFromSerializedObject($unserialized);
        }
        $this->postProcessLoadResponse(successfull: true);
    }

    /**
     * @param bool $displayCall
     * @param bool $displayResponse
     * @return void
     * @throws ReflectionException
     */
    public function rcCreate(
        bool $displayCall = false,
        bool $displayResponse = false
    ): void {
        $this->executeRCApiOperation(
            RCApiOperationType::CREATE,
            $displayCall,
            $displayResponse,
            $this->rcSettings->getCreateEndpoint(),
            $this->getCreatePayload(),
            $this->rcSettings->timeout ?? 0
        );
    }

    /**
     * @param bool $displayCall
     * @param bool $displayResponse
     * @return void
     * @throws InternalErrorException
     * @throws ReflectionException
     * @throws BadRequestException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws NonUniqueResultException
     * @throws JsonException
     * @throws InvalidArgumentException
     */
    public function rcUpdate(
        bool $displayCall = false,
        bool $displayResponse = false
    ): void {
        $this->executeRCApiOperation(
            RCApiOperationType::UPDATE,
            $displayCall,
            $displayResponse,
            $this->rcSettings->getUpdateEndpoint(),
            $this->getUpdatePayload(),
            $this->rcSettings->timeout ?? 0
        );
    }

    /**
     * @param bool $displayCall
     * @param bool $displayResponse
     * @return void
     * @throws ReflectionException
     */
    public function rcDelete(
        bool $displayCall = false,
        bool $displayResponse = false
    ): void {
        $this->executeRCApiOperation(
            RCApiOperationType::DELETE,
            $displayCall,
            $displayResponse,
            $this->rcSettings->getDeleteEndpoint(),
            $this->getDeletePayload(),
            $this->rcSettings->timeout ?? 0
        );
    }

    /**
     * @param bool $displayCall
     * @param bool $displayResponse
     * @return void
     * @throws ReflectionException
     */
    public function rcSynchronize(
        bool $displayCall = false,
        bool $displayResponse = false
    ): void {
        $this->executeRCApiOperation(
            RCApiOperationType::SYNCHRONIZE,
            $displayCall,
            $displayResponse,
            $this->rcSettings->getSynchronizeEndpoint(),
            $this->getSynchronizePayload(),
            $this->rcSettings->timeout ?? 0
        );
    }

    /**
     * Executes an RC related operation with the given parameters
     * @param string $rcApiOperationType
     * @param bool $displayCall
     * @param bool $displayResponse
     * @param string|array|null $endpoint
     * @param array|null $payload
     * @param float $timeout
     * @return void
     * @throws ReflectionException
     */
    private function executeRCApiOperation(
        string             $rcApiOperationType,
        bool               $displayCall = false,
        bool               $displayResponse = false,
        string|array|null  $endpoint = null,
        ?array             $payload = null,
        float              $timeout = 0
    ): void {
        if (!$payload || !$endpoint) {
            return;
        }
        $apiOperations = new RCApiOperations();
        $apiOperation = new RCApiOperation($this, $this->cacheKey(), $endpoint, $payload);
        $apiOperations->addOperation($apiOperation);
        $apiOperations->execute(
            $displayCall,
            $displayResponse,
            false,
            true,
            $rcApiOperationType,
            $timeout
        );
        $this->clearRCCache();
    }

    /**
     * Returns the payload for LOAD call
     */
    protected function getLoadPayload(): ?array
    {
        if (method_exists($this, 'getLoadPayloadInternal')) {
            return $this->getLoadPayloadInternal();
        }
        return null;
    }

    /**
     * Returns the payload for CREATE call
     */
    protected function getCreatePayload(): ?array
    {
        return null;
    }

    /**
     * Returns the payload for UPDATE call
     */
    protected function getUpdatePayload(): ?array
    {
        return null;
    }

    /**
     * Returns the payload for DELETE call
     */
    protected function getDeletePayload(): ?array
    {
        return null;
    }

    /**
     * Returns the payload for SYNCHRONIZE call
     */
    protected function getSynchronizePayload(): ?array
    {
        return null;
    }

    /**
     * Sets parameters for controlling of loading, e.g. startTime and endTime for time series data.
     */
    public function setLoadingParameters(): void
    {
    }

    /**
     * Populates this object from RC Loading Response
     * @param mixed|null $callResponseData
     * @param RCApiOperation|null $apiOperation
     * @return void
     */
    public function handleLoadResponse(
        mixed &$callResponseData = null,
        RCApiOperation &$apiOperation = null
    ): void {
        if (method_exists($this, 'handleLoadResponseInternal')) {
            $this->handleLoadResponseInternal($callResponseData, $apiOperation);
        }
    }

    /**
     * Used for handling response of UPDATE calls on RC
     * Usually this method is overridden on the RC entity but in some cases we may use the default load behaviour
     * @param mixed $callResponseData
     * @param RCApiOperation|null $apiOperation
     * @return void
     */
    public function handleUpdateResponse(mixed &$callResponseData, RCApiOperation &$apiOperation = null): void
    {
    }

    /**
     * Used for handling response of DELETE calls on RC
     * @param mixed $callResponseData
     * @param RCApiOperation|null $apiOperation
     * @return void
     */
    public function handleDeleteResponse(mixed &$callResponseData, RCApiOperation &$apiOperation = null): void
    {
    }

    /**
     * Used for handling response of CREATE calls on RC
     * @param mixed $callResponseData
     * @param RCApiOperation|null $apiOperation
     * @return void
     */
    public function handleCreateResponse(mixed &$callResponseData, RCApiOperation &$apiOperation = null): void
    {
    }

    /**
     * Used for handling response of SYNCHRONIZE calls on RC
     * @param mixed $callResponseData
     * @param RCApiOperation|null $apiOperation
     * @return void
     */
    public function handleSynchronizeResponse(mixed &$callResponseData, RCApiOperation &$apiOperation = null): void
    {
    }

    /**
     * increments loaded children count and and triggers populateDataFromLoadingCallback if no loading function is present
     * and all children are loaded
     * @return void
     */
    public function incLoadedChildren(): void
    {
        $this->rcSettings->childrenLoaded++;
        if ($this->rcSettings->childrenToLoad == $this->rcSettings->childrenLoaded && !$this->rcSettings->getLoadEndpoint(
            )) {
            $this->handleLoadResponse();
        }
    }

    /**
     * Sets all properties to be loaded by recurisvely passing through currents objects children.
     * Accepts class names as string or RCLoadingParameters, in case of RCLoadingParameters passed, it tries to pass the parameters
     * to setElementsToBeLoaded function.
     * @param string|RCLoadingParameters ...$classNamesOrRCLoadingParameters
     * @return void
     */
    public function setPropertiesToLoad(string|RCLoadingParameters ...$classNamesOrRCLoadingParameters): void
    {
        /** @var RCLoadingParameters[] $parameters */
        $parameters = [];
        // normalize all to RCLoadingParameters
        foreach ($classNamesOrRCLoadingParameters as $classNameOrRCLoadingParameter) {
            $parameters[] = is_string($classNameOrRCLoadingParameter) ? RCLoadingParameters::create(
                $classNameOrRCLoadingParameter
            ) : $classNameOrRCLoadingParameter;
        }
        $this->setPropertiesToLoadInternal([], ...$parameters);
    }

    /**
     * Set Properties set that shall be loaded on any load operation
     * as the values are static, we store these static associated to the class where this method is called
     * @param string|RCLoadingParameters ...$classNamesOrRCLoadingParameters
     * @return void
     */
    public static function setPropertiesToLoadAlways(
        string|RCLoadingParameters ...$classNamesOrRCLoadingParameters
    ): void {
        self::$propertiesToLoadAlways[static::class] = $classNamesOrRCLoadingParameters;
    }

    /**
     * Sets all elements to be loaded by recurisvely passing through currents objects children.
     * Accepts RCLoadingParameters, it tries to pass the parameters contained
     * to setElementsToBeLoaded function.
     *
     * In order to avoid recursion it uses $path wich contains ids of all objects this function
     * has been called on directly before and returns if it is called again on th same object
     * @param array $path
     * @param string ...$loadingParameters
     * @return void
     */
    public function setPropertiesToLoadInternal(
        array $path = [],
        RCLoadingParameters &...$loadingParameters
    ) {
        if (isset($path[spl_object_hash($this)])) {
            return;
        }
        $path[spl_object_hash($this)] = true;

        if (!$loadingParameters) {
            $this->rcSettings->setToBeLoaded(false);
        }
        foreach ($loadingParameters as $loadingParameter) {
            if (static::class == $loadingParameter->classNameToBeLoaded) {
                $this->rcSettings->setToBeLoaded(true);
                if ($loadingParameter->loadingParameters) {
                    $this->setLoadingParameters(...$loadingParameter->loadingParameters);
                }
            }
        }
        // Lazy instance the children that are not instantiated yet
        // in the context of an RC Repo Entity Lazyload does not load but instead creates an instance of the property
        foreach ($this->getProperties(ReflectionProperty::IS_PUBLIC) as $reflectionProperty) {
            $propertyName = $reflectionProperty->getName();
            if (isset($this->$propertyName)) {
                continue;
            }
            $reflectionType = $reflectionProperty->getType();
            if ($reflectionType instanceof ReflectionNamedType && !$reflectionType->isBuiltin(
                ) && !isset($this->$propertyName)) {
                $typeName = $reflectionType->getName();
                if (!method_exists($typeName, 'getRepoClass')) {
                    continue;
                }
                $rcRepoClass = $this->getRepoClassForProperty(LazyLoadRepo::RC, $propertyName);
                //$typeName::getRepoClass(Repository::REPOTYPE_RC);
                if (!$rcRepoClass) {
                    continue;
                }
                foreach ($loadingParameters as $loadingParameter) {
                    if ($rcRepoClass == $loadingParameter->classNameToBeLoaded
                        // avoid recursive lazyload / lazy instance
                        && static::class != $rcRepoClass) {
                        // LazyLoad removed the property => we just touch it
                        $this->$propertyName = new $rcRepoClass();
                        $this->$propertyName->setParent($this);
                        $this->addChildren($this->$propertyName);
                    }
                }
            }
        }
        foreach ($this->getChildren() as $child) {
            /** @var RCLoadTrait $child */
            if ($child instanceof DefaultObject && method_exists($child, 'setPropertiesToLoadInternal')) {
                $child->setPropertiesToLoadInternal($path, ...$loadingParameters);
            }
        }
    }

    /**
     * Is to be called AFTER individual handleLoadResponse implementation
     * handles aspects as setting then object loaded and caching
     * @param mixed|null $callResponseData response data data
     * @param bool $successfull loading was successful
     * @return void
     */
    private function postProcessLoadResponse(
        mixed &$callResponseData = null,
        bool $successfull = true
    ) {
        if ($this->parent && method_exists($this->parent, 'incLoadedChildren')) {
            $this->parent->incLoadedChildren();
        }
        $this->rcSettings->isLoaded = true;
        $this->rcSettings->toBeLoaded = false;
        $this->rcSettings->isLoadedSuccessfully = $successfull;
        if ($this->rcSettings->isCachable() && !$this->rcSettings->loadedFromCache) {
            if (!$successfull) {
                RCCache::set(
                    $this->cacheKey(),
                    '',
                    $this->rcSettings->getCacheTTL(),
                    $this->rcSettings->getCacheLevel()
                );
            } else {
                $serialized = $this->rcSettings->getSerializationMethod(
                ) == RCLoad::SERIALIZATION_METHOD_TO_OBJECT ? $this->toJSON(
                    true
                ) : serialize($this);
                //echo $this->cacheKey() . ' Set<br />';
                RCCache::set(
                    $this->cacheKey(),
                    $serialized,
                    $this->rcSettings->getCacheTTL(),
                    $this->rcSettings->getCacheLevel()
                );
            }
        }
        $this->postProcessObjectAfterLoading();
    }

    /**
     * Override this function in order to manipulate the object after it is loaded either from cache or from RC,
     * e.g. usefully in order to do manipulations on parent objects or on this object that shall not be persisted in caching
     * @return void
     */
    protected function postProcessObjectAfterLoading(): void
    {
    }

    public function clearRCCache()
    {
        RCCache::delete($this->cacheKey());
    }
}
