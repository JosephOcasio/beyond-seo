<?php

namespace App\Domain\Common\Repo\InternalDB\Roles;

use App\Domain\Common\Entities\Accounts\Account;
use App\Domain\Common\Entities\Roles\Roles;
use App\Domain\Common\Repo\InternalDB\InternalDBEntitySet;
use App\Domain\Common\Repo\InternalDB\Models\InternalDBRolesUserModel;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoad;
use DDD\Domain\Base\Repo\DB\Attributes\EntityCache;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineQueryBuilder;
use DDD\Domain\Base\Repo\DB\Doctrine\EntityManagerFactory;
use DDD\Domain\Common\Entities\CacheScopes\CacheScope;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use Psr\Cache\InvalidArgumentException;
use ReflectionException;

/**
 * @method Roles find(DoctrineQueryBuilder $queryBuilder = null, $useEntityRegistryCache = true)
 */
#[EntityCache(useExtendedRegistryCache: true, ttl: 300, cacheScopes:[CacheScope::ACCOUNT_ROLES])]
class InternalDBRoles extends InternalDBEntitySet
{
    public const BASE_REPO_CLASS = InternalDBRole::class;
    public const BASE_ENTITY_SET_CLASS = Roles::class;

    /**
     * @param Account $account
     * @param LazyLoad $lazyloadPropertyInstance
     * @return Roles|null
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     */
    public function lazyload(Account &$account, LazyLoad &$lazyloadPropertyInstance): ?Roles
    {
        $em = EntityManagerFactory::getInstance();
        $expr = $em->getExpressionBuilder();
        $queryBuilder = self::createQueryBuilder()
            ->where(
                $expr->in(
                    'role.id',
                    self::createQueryBuilder()
                        ->select('RolesUser.role_id')
                        ->from(InternalDBRolesUserModel::class, 'RolesUser')
                        ->where('RolesUser.user_id = :user_id')->getDQL()
                )
            )->setParameter('user_id', $account->id);
        // an alternative is:
        //->where('role.id in(Select RolesUser.role_id from '.RolesUserModel::class.' RolesUser where RolesUser.user_id = :user_id)')
        return $this->find($queryBuilder, $lazyloadPropertyInstance->useCache);
    }
}