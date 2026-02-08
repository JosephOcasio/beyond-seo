<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Domain\Common\Repo\InternalDB\InternalDBEntity;
use DDD\Domain\Base\Repo\DB\DBEntity;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineEntityRegistry;
use DDD\Domain\Base\Repo\Virtual\VirtualEntityRegistry;
use DDD\Infrastructure\Libs\ClassFinder;
use DDD\Infrastructure\Services\DDDService;

/**
 * @property static AppService $instance
 */
class AppService extends DDDService
{
    protected static $cacheStates = [
        'doctrineRegistry' => true,
        'virtualRegistry' => true,
        'app' => true,
        'classFinder' => true
    ];

    protected static $entityRightsRestrictionStates = [
        InternalDBEntity::class => true,
        DBEntity::class => true
    ];


    /**
     * @return void Creates snapshot of current application related caches state
     */
    public function createCachesSnapshot(): void
    {
        self::$cacheStates = [
            'doctrineRegistry' => !DoctrineEntityRegistry::$clearCache,
            'virtualRegistry' => !VirtualEntityRegistry::$clearCache,
            'app' => !AppService::$noCache,
            'classFinder' => !ClassFinder::$clearCache
        ];
        self::$cachesSnapshotSet = true;
    }

    /**
     * @return void Restores snapshot of application related caches state
     */
    public function restoreCachesSnapshot(): void
    {
        if (!self::$cachesSnapshotSet) {
            return;
        }
        DoctrineEntityRegistry::$clearCache = !self::$cacheStates['doctrineRegistry'];
        VirtualEntityRegistry::$clearCache = !self::$cacheStates['virtualRegistry'];
        AppService::$noCache = !self::$cacheStates['app'];
        ClassFinder::$clearCache = !self::$cacheStates['classFinder'];
        self::$cachesSnapshotSet = false;
    }

    /**
     * @return void Deactivates all application related caches
     */
    public function deactivateCaches(): void
    {
        $this->createCachesSnapshot();
        DoctrineEntityRegistry::$clearCache = true;
        VirtualEntityRegistry::$clearCache = true;
        ClassFinder::$clearCache = true;
        AppService::$noCache = true;
    }

    /**
     * @return void Activates all application related caches
     */
    public function activateCaches(): void
    {
        $this->createCachesSnapshot();
        DoctrineEntityRegistry::$clearCache = false;
        VirtualEntityRegistry::$clearCache = false;
        AppService::$noCache = false;
        ClassFinder::$clearCache = false;
    }

    /**
     * @return void Creates snapshot of current Entity rights restriction states
     */
    public function createEntityRightsRestrictionsStateSnapshot(): void
    {
        self::$entityRightsRestrictionStates = [
            DBEntity::class => DBEntity::getApplyRightsRestrictions(),
            InternalDBEntity::class => InternalDBEntity::getApplyRightsRestrictions()
        ];
        self::$entityRightsRestrictionSnapshotSet = true;
    }

    /**
     * @return void Restores snapshot of current Entity rights restriction states
     */
    public function restoreEntityRightsRestrictionsStateSnapshot(): void
    {
        if (!self::$entityRightsRestrictionSnapshotSet) {
            return;
        }
        DBEntity::setApplyRightsRestrictions(self::$entityRightsRestrictionStates[DBEntity::class]);
        InternalDBEntity::setApplyRightsRestrictions(self::$entityRightsRestrictionStates[InternalDBEntity::class]);
        self::$entityRightsRestrictionSnapshotSet = false;
    }

    /**
     * @return void Deactivates all application related caches
     */
    public function deactivateEntityRightsRestrictions(): void
    {
        $this->createEntityRightsRestrictionsStateSnapshot();
        DBEntity::setApplyRightsRestrictions(false);
        InternalDBEntity::setApplyRightsRestrictions(false);
    }

}
