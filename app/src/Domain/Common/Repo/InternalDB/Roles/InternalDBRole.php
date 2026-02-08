<?php

namespace App\Domain\Common\Repo\InternalDB\Roles;

use App\Domain\Common\Entities\Roles\Role;
use App\Domain\Common\Repo\InternalDB\InternalDBEntity;
use App\Domain\Common\Repo\InternalDB\Models\InternalDBRoleModel;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineModel;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineQueryBuilder;
use DDD\Infrastructure\Services\AuthService;

/**
 * @method Role find(DoctrineQueryBuilder|string|int $idOrQueryBuilder, bool $useEntityRegistryCache = true, ?DoctrineModel &$loadedOrmInstance = null, bool $deferredCaching = false)
 * @property InternalDBRoleModel $ormInstance
 */
class InternalDBRole extends InternalDBEntity
{
    public const BASE_ENTITY_CLASS = Role::class;
    public const BASE_ORM_MODEL = InternalDBRoleModel::class;

    protected ?string $name = null;
    protected ?string $description = null;

    /**
     * Applies update/delete restrictions based on Auth::instance()->getAccount
     * @param DoctrineQueryBuilder $queryBuilder
     * @return bool
     */
    public static function applyUpdateRightsQuery(DoctrineQueryBuilder &$queryBuilder): bool
    {
        $class = static::class;
        $roleAlias = static::getBaseModelAlias();

        if (!self::$applyRightsRestrictions) {
            return false;
        }

        $authAccount = AuthService::instance()->getAccount();
        if (!$authAccount) {
            $queryBuilder->andWhere("{$roleAlias}.id is null");
            return true;
        }

        if (!$authAccount->roles->hasRoles(...[Role::SUPERADMIN])) {
            $queryBuilder->andWhere("{$roleAlias}.id is null");
            return true;
        }

        return false;
    }

    public function mapToEntity(bool $useEntityRegistryCache = true): Role
    {
        /** @var Role $role */
        $role = parent::mapToEntity($useEntityRegistryCache);
        $ormInstance = $this->ormInstance;
        $role->id = $ormInstance->id;
        $role->setName($ormInstance->name);
        $role->description = $ormInstance->description;
        $role->isAdminRole = $ormInstance->isAdminRole ?? false;
        return $role;
    }

}