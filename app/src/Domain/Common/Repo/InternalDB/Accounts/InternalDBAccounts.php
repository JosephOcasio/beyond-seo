<?php
declare(strict_types=1);

namespace App\Domain\Common\Repo\InternalDB\Accounts;

use App\Domain\Common\Repo\InternalDB\InternalDBEntitySet;
use App\Domain\Integrations\WordPress\Common\Entities\Accounts\WPAccounts;
use DDD\Domain\Base\Entities\DefaultObject;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoad;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineQueryBuilder;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use Doctrine\ORM\Mapping\MappingException;
use Psr\Cache\InvalidArgumentException;
use ReflectionException;

/**
 * @method WPAccounts find(DoctrineQueryBuilder $queryBuilder = null, $useEntityRegistryCache = true)
 */
class InternalDBAccounts extends InternalDBEntitySet
{
    public const BASE_REPO_CLASS = InternalDBAccount::class;
    public const BASE_ENTITY_SET_CLASS = WPAccounts::class;

    /**
     * @param DefaultObject $initiatingEntity
     * @param LazyLoad $lazyloadPropertyInstance
     * @return WPAccounts|null
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws MappingException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     */
    public function lazyload(
        DefaultObject &$initiatingEntity,
        LazyLoad &$lazyloadPropertyInstance
    ): ?WPAccounts {
        return $this->getAll($lazyloadPropertyInstance->useCache);
    }

    /**
     * @param bool $useCache
     * @return WPAccounts
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws MappingException
     * @throws ReflectionException
     */
    public function getAll(bool $useCache = true): WPAccounts
    {
        $queryBuilder = static::createQueryBuilder();
        $queryBuilder
            ->select('app_account')
            ->orderBy('app_account.id', 'DESC');
        return $this->find($queryBuilder, $useCache);
    }

    /**
     * @param int $id
     * @return WPAccounts
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws MappingException
     * @throws ReflectionException
     */
    public function findById(int $id): WPAccounts
    {
        $queryBuilder = static::createQueryBuilder();
        $queryBuilder
            ->select('app_account')
            ->where('app_account.id = :id')
            ->setParameter('id', $id);
        return $this->find($queryBuilder);
    }

    /**
     * @param string $email
     * @return WPAccounts
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws MappingException
     * @throws ReflectionException
     */
    public function findByEmail(string $email): WPAccounts
    {
        $queryBuilder = static::createQueryBuilder();
        $queryBuilder
            ->select('app_account')
            ->where('app_account.email = :email')
            ->setParameter('email', $email);
        return $this->find($queryBuilder);
    }

    /**
     * @param string $externalId
     * @return WPAccounts
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws MappingException
     * @throws ReflectionException
     */
    public function findByExternalId(string $externalId): WPAccounts
    {
        $queryBuilder = static::createQueryBuilder();
        $queryBuilder
            ->select('app_account')
            ->where('app_account.externalId = :externalId')
            ->setParameter('externalId', $externalId);
        return $this->find($queryBuilder);
    }
}