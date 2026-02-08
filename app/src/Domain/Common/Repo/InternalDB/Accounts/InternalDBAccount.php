<?php

declare(strict_types=1);

namespace App\Domain\Common\Repo\InternalDB\Accounts;

use App\Domain\Common\Entities\Accounts\Account;
use App\Domain\Common\Entities\ContactInfos\ContactInfos;
use App\Domain\Common\Entities\Persons\Person;
use App\Domain\Common\Entities\Persons\PersonGender;
use App\Domain\Common\Repo\InternalDB\InternalDBEntity;
use App\Domain\Common\Repo\InternalDB\Models\InternalDBAccountModel;
use App\Domain\Integrations\WordPress\Common\Entities\Accounts\WPAccount;
use DDD\Domain\Base\Entities\Entity;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineModel;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineQueryBuilder;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\Mapping\MappingException;
use Psr\Cache\InvalidArgumentException;
use ReflectionException;

/**
 * @method WPAccount find(DoctrineQueryBuilder|string|int $idOrQueryBuilder, bool $useEntityRegistryCache = true, ?DoctrineModel &$loadedOrmInstance = null, bool $deferredCaching = false)
 * @property InternalDBAccountModel $ormInstance
 */
class InternalDBAccount extends InternalDBEntity
{
    /** @var string */
    public const BASE_ENTITY_CLASS = WPAccount::class;

    /** @var string */
    public const BASE_ORM_MODEL = InternalDBAccountModel::class;

    /** @var int */
    public const DEFAULT_RESELLER_ID = 1000;

    /** @var array */
    public static array $accountTypeAllocation = [
        'viewer' => Account::ACCOUNT_TYPE_AGENCY_VIEWER,
        'standard' => Account::ACCOUNT_TYPE_STANDARD,
        'reseller' => Account::ACCOUNT_TYPE_RESELLER,
        'admin' => Account::ACCOUNT_TYPE_ADMIN,
        'author' => Account::ACCOUNT_TYPE_AUTHOR,
        'editor' => Account::ACCOUNT_TYPE_EDITOR,
        'contributor' => Account::ACCOUNT_TYPE_CONTRIBUTOR,
        'subscriber' => Account::ACCOUNT_TYPE_SUBSCRIBER,
    ];

    /** @var array */
    public static array $accountStatusAllocation = [
        'new' => Account::ACCOUNT_STATUS_NEW,
        'active' => Account::ACCOUNT_STATUS_ACTIVE,
        'canceled' => Account::ACCOUNT_STATUS_CANCELLED,
        'inactive' => Account::ACCOUNT_STATUS_INACTIVE,
        'locked' => Account::ACCOUNT_STATUS_LOCKED,
        'expired' => Account::ACCOUNT_STATUS_EXPIRED,
        'gdpr_deleted' => Account::ACCOUNT_STATUS_GDPR_DELETED,
    ];

    /**
     * Applies restrictions to account loading based on Auth::instance()->getAccount
     * @param DoctrineQueryBuilder $queryBuilder
     * @param bool $applyUpdateRights
     * @return bool
     */
    public static function applyReadRightsQuery(
        DoctrineQueryBuilder &$queryBuilder,
        bool $applyUpdateRights = false
    ): bool {

        // TODO by Romeo: Fix this
        return false;
    }

    /**
     * Applies update/delete restrictions based on Auth::instance()->getAccount
     * @param DoctrineQueryBuilder $queryBuilder
     * @return bool
     */
    public static function applyUpdateRightsQuery(DoctrineQueryBuilder &$queryBuilder): bool
    {
        return self::applyReadRightsQuery($queryBuilder, true);
    }

    /**
     * Finds account by email
     * @param string $email
     * @return Account|null
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws Exception
     * @throws MappingException
     */
    public function findByEmail(string $email): ?Account
    {
        $qb = static::createQueryBuilder();
        $qb->where('user.email = :email')->setParameter('email', $email);
        return $this->find($qb);
    }

    /**
     * Finds account by external id
     * @param string $externalId
     * @return Account|null
     * @throws BadRequestException
     * @throws Exception
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws MappingException
     * @throws ReflectionException
     */
    public function findByExternalId(string $externalId): ?WPAccount
    {
        $qb = static::createQueryBuilder();
        $qb->select('user');
        $qb->where('user.external_id = :account_external_id')->setParameter(
            'account_external_id',
            $externalId
        );
        return $this->find($qb);
    }

    /**
     * @param bool $useEntityRegistryCache
     * @return WPAccount
     * @throws ReflectionException
     */
    public function mapToEntity(bool $useEntityRegistryCache = true): WPAccount
    {
        /** @var WPAccount $account */
        $account = parent::mapToEntity($useEntityRegistryCache);
        $ormInstance = $this->ormInstance;

        $account->id = (int)$ormInstance->id;
        $account->externalId = $ormInstance->externalId;
        $account->status = $ormInstance->status;
        $account->type = $ormInstance->type;
        $account->email = $ormInstance->email;
        $account->languageCode = $ormInstance->languageCode;
        $account->resellerId = $ormInstance->resellerId;

        $owner = new Person();
        $ownerDb = json_decode((string)$ormInstance->owner);
        $account->owner = $owner;
        $account->owner->gender = new PersonGender();
        $account->owner->gender->gender = $ownerDb->gender->gender;
        $account->owner->firstName = $ownerDb->firstName;
        $account->owner->lastName = $ownerDb->lastName;
        $account->owner->jobTitle = $ownerDb->jobTitle;

        $account->isSandboxAccount = (bool)$ormInstance->isSandboxAccount;
        $account->isSpecialAccount = (bool)$ormInstance->isSpecialAccount;

        $contactInfos = new ContactInfos();
        $account->contactInfos = $contactInfos;
        $account->totalNumberOfActiveProjects = $ormInstance->totalNumberOfActiveProjects;

        $this->ormInstance = $ormInstance;
        return $account;
    }

    /**
     * @param Account $entity
     * @return bool
     * @throws ReflectionException
     */
    protected function mapToRepository(Entity &$entity): bool
    {
        parent::mapToRepository($entity);
        if(isset($this->ormInstance->ID)) {
            $this->ormInstance->ID = (int)$entity->id;
        }

        if (isset($entity->owner->firstName)) {
            $this->ormInstance->firstname = $entity->owner->firstName;
        }

        if (isset($entity->owner->lastName)) {
            $this->ormInstance->lastname = $entity->owner->lastName;
        }

        if (isset($entity->owner->jobTitle)) {
            $this->ormInstance->job_title = $entity->owner->jobTitle;
        }

        if (isset($entity->owner->academicTitle)) {
            $this->ormInstance->academic_title = $entity->owner->academicTitle;
        }

        if (isset($entity->owner->gender)) {
            $this->ormInstance->gender = (string)$entity->owner->gender;
        }

        if (isset($entity->password)) {
            $this->ormInstance->password = $entity->password;
        }
        if (isset($entity->status)) {
            $this->ormInstance->customer_status = array_flip(self::$accountStatusAllocation)[$entity->status];
        }

        return true;
    }
}
