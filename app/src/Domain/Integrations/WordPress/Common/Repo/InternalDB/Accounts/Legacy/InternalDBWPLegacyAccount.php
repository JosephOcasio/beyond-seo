<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Common\Repo\InternalDB\Accounts\Legacy;

use App\Domain\Common\Entities\Accounts\Account;
use App\Domain\Common\Entities\Capabilities\Capabilities;
use App\Domain\Common\Entities\ContactInfos\ContactInfos;
use App\Domain\Common\Entities\Persons\Person;
use App\Domain\Common\Entities\Persons\PersonGender;
use App\Domain\Common\Entities\Roles\Roles;
use App\Domain\Common\Repo\InternalDB\InternalDBEntity;
use App\Domain\Common\Repo\InternalDB\Models\InternalDBUserModel;
use App\Domain\Integrations\WordPress\Common\Entities\Accounts\Legacy\WPLegacyAccount;
use DDD\Domain\Base\Entities\DefaultObject;
use DDD\Domain\Base\Entities\Entity;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoad;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineModel;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineQueryBuilder;
use DDD\Domain\Common\Entities\ContactInfos\Email;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\Mapping\MappingException;
use Psr\Cache\InvalidArgumentException;
use RankingCoach\Inc\Core\TokensManager;
use ReflectionException;
use WP_User;

/**
 * @method WPLegacyAccount find(DoctrineQueryBuilder|string|int $idOrQueryBuilder, bool $useEntityRegistryCache = false, ?DoctrineModel &$loadedOrmInstance = null, bool $deferredCaching = false)
 * @property InternalDBUserModel $ormInstance
 */
class InternalDBWPLegacyAccount extends InternalDBEntity
{
    public const BASE_ENTITY_CLASS = WPLegacyAccount::class;
    public const BASE_ORM_MODEL = InternalDBUserModel::class;

    public static array $wpStatusMap = [
        0 => Account::ACCOUNT_STATUS_ACTIVE,
        1 => Account::ACCOUNT_STATUS_NEW,
        2 => Account::ACCOUNT_STATUS_INACTIVE,
        3 => Account::ACCOUNT_STATUS_CANCELLED
    ];

    /**
     * @param DefaultObject $initiatingEntity
     * @param LazyLoad $lazyloadAttributeInstance
     *
     * @return WPLegacyAccount
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     */
    public function lazyload(
        DefaultObject &$initiatingEntity,
        LazyLoad &$lazyloadAttributeInstance
    ): WPLegacyAccount {
        parent::lazyload($initiatingEntity, $lazyloadAttributeInstance);
        return $this->mapToEntity($lazyloadAttributeInstance->useCache);
    }

    /**
     * @param int|null $id
     * @param bool $useEntityRegistryCache
     * @return WPLegacyAccount
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws Exception
     * @throws MappingException
     */
    public function findById(?int $id = null, bool $useEntityRegistryCache = false): WPLegacyAccount
    {
        $ormInstance = $this->find($id, $useEntityRegistryCache);
        if (!$ormInstance) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            /* translators: %s is the user ID */
            throw new BadRequestException(sprintf(__('User with ID %s not found.', 'beyond-seo'), $id));
        }
        return $ormInstance;
    }


    /**
     * @param bool $useEntityRegistryCache
     * @return WPLegacyAccount
     * @throws ReflectionException
     */
    public function mapToEntity(bool $useEntityRegistryCache = false): WPLegacyAccount
    {
        /** @var WPLegacyAccount $account */
        $account = parent::mapToEntity($useEntityRegistryCache);
        $ormInstance = $this->ormInstance;

        $account->id = $ormInstance->ID;

        $account->userRoles = new Roles();
        $account->userCapabilities = new Capabilities();
        $account->owner = new Person();
        $account->owner->firstName = $ormInstance->user_nicename;
        $account->owner->lastName = $ormInstance->display_name;
        $account->owner->gender->setGender(get_user_meta($ormInstance->ID, 'gender', true) ?? PersonGender::GENDER_OTHER);
        $account->owner->academicTitle = get_user_meta($ormInstance->ID, 'academic_title', true);
        $account->owner->jobTitle = get_user_meta($ormInstance->ID, 'job_title', true);
        $account->status = self::$wpStatusMap[$ormInstance->user_status];
        $account->externalId = (string)$ormInstance->ID;
        $account->contactInfos = new ContactInfos();
        $account->addChildren($account->contactInfos);
        if ($language = $account->getAccountLanguage($ormInstance->ID, true)) {
            $account->languageCode = $language;
        }
        if (isset($ormInstance->user_email) && $ormInstance->user_email) {
            $emailBusiness = new Email();
            $emailBusiness->setScope(Email::SCOPE_EMAIL_BUSINESS);
            $emailBusiness->setValue($ormInstance->user_email);
            $account->contactInfos->add($emailBusiness);
        }
        $account->setAccountWebsiteIfMissing($ormInstance->ID);

        $account->userLogin = $ormInstance->user_login;
        $account->userNicename = $ormInstance->user_nicename;
        $account->userEmail = $ormInstance->user_email;
        $account->userUrl = $ormInstance->user_url;
        $account->userRegistered = $ormInstance->user_registered;
        $account->userActivationKey = $ormInstance->user_activation_key;
        $account->userStatus = $ormInstance->user_status; // 0 = active, 1 = pending, 2 = suspended, 3 = deleted
        $account->displayName = $ormInstance->display_name;
        $account->password = $ormInstance->user_pass;

        $userDb = new WP_User($ormInstance->ID);
        if($userDb->exists()) {
            if(!empty($userDb->roles)) {
                $account->setRoles($userDb->roles);
            }
            if(!empty($userDb->allcaps)) {
                $account->setCapabilities($userDb->allcaps);
            }
        }
        update_user_meta($ormInstance->ID, 'rankingcoach_external_id', $ormInstance->ID);

        /** @noinspection PhpExpressionResultUnusedInspection */
        $account->accountSettings;

        /** The access token for this account */
        $account->rcAccessToken = get_option(TokensManager::ACCESS_TOKEN);

        return $account;
    }

    /**
     * @param WPLegacyAccount|Entity $entity
     * @return bool
     * @throws ReflectionException
     */
    protected function mapToRepository(WPLegacyAccount|Entity &$entity): bool
    {
        parent::mapToRepository($entity);
        $model = $this->ormInstance;

//        $model->ID = (int)$entity->id;
//        $model->user_login = $entity->userLogin;
//        $model->user_nicename = $entity->userNicename;
//        $model->user_email = $entity->userEmail;
//        $model->user_url = $entity->userUrl;
//        $model->user_registered = $entity->userRegistered;
//        $model->user_activation_key = $entity->userActivationKey;
//        $model->user_status = $entity->userStatus;
//        $model->display_name = $entity->displayName;

        return true;
    }
}
