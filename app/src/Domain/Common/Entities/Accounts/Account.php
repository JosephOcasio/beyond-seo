<?php

declare(strict_types=1);

namespace App\Domain\Common\Entities\Accounts;

use App\Domain\Common\Entities\ContactInfos\ContactInfos;
use App\Domain\Common\Entities\Persons\Person;
use App\Domain\Common\Repo\InternalDB\Accounts\InternalDBAccount;
use App\Domain\Common\Services\AccountsService;
use App\Infrastructure\Services\AppService;
use DDD\Domain\Base\Entities\Attributes\NoRecursiveUpdate;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoad;
use DDD\Domain\Base\Entities\QueryOptions\QueryOptions;
use DDD\Domain\Base\Entities\QueryOptions\QueryOptionsTrait;
use DDD\Domain\Common\Entities\Roles\Role;
use DDD\Domain\Common\Entities\Roles\Roles;
use DDD\Infrastructure\Traits\Serializer\Attributes\HideProperty;
use DDD\Infrastructure\Validation\Constraints\Choice;

/**
 * @method static AccountsService getService()
 * @method static InternalDBAccount getRepoClassInstance(string $repoType = null)
 */
#[QueryOptions]
#[NoRecursiveUpdate]
class Account extends \DDD\Domain\Common\Entities\Accounts\Account
{
    use QueryOptionsTrait;

    /** @var string Agency account that can only view reports without rights to operate or change the location */
    public const ACCOUNT_TYPE_AGENCY_VIEWER = 'agency_viewer';

    /** @var string Resellers main account that connection to api account and can manage all his reseller accounts */
    public const ACCOUNT_TYPE_RESELLER = 'reseller';

    /** @var string Standard account */
    public const ACCOUNT_TYPE_STANDARD = 'standard';

    /** @var string ACCOUNT_TYPE_ADMIN Represents a type of user that has full access to the WordPress site. */
    public const ACCOUNT_TYPE_ADMIN = 'admin';

    /** @var string ACCOUNT_TYPE_EDITOR Represents a type of user that can publish and manage posts and pages. */
    public const ACCOUNT_TYPE_EDITOR = 'editor';

    /** @var string ACCOUNT_TYPE_AUTHOR Represents a type of user that can publish and manage their own posts. */
    public const ACCOUNT_TYPE_AUTHOR = 'author';

    /** @var string ACCOUNT_TYPE_CONTRIBUTOR Represents a type of user that can write and manage their own posts but cannot publish them. */
    public const ACCOUNT_TYPE_CONTRIBUTOR = 'contributor';

    /** @var string ACCOUNT_TYPE_SUBSCRIBER Represents a type of user that can only manage their profile. */
    public const ACCOUNT_TYPE_SUBSCRIBER = 'subscriber';

    /** @var string */
    public const ACCOUNT_STATUS_NEW = 'new';

    /** @var string */
    public const ACCOUNT_STATUS_ACTIVE = 'active';

    /** @var string */
    public const ACCOUNT_STATUS_INACTIVE = 'inactive';

    /** @var string */
    public const ACCOUNT_STATUS_CANCELLED = 'cancelled';

    /** @var string */
    public const ACCOUNT_STATUS_LOCKED = 'locked';

    /** @var string */
    public const ACCOUNT_STATUS_EXPIRED = 'expired';

    /** @var string */
    public const ACCOUNT_STATUS_GDPR_DELETED = 'gdpr_deleted';

    /** @var string[] */
    public const ROLE_ASSOCIATIONS
        = [
            Role::LOGIN => 'ROLE_USER',
            Role::ADMIN => 'ROLE_ADMIN',
            Role::SUPERADMIN => 'ROLE_SUPER_ADMIN',
        ];

    /** @var string Account's status */
    #[Choice(choices: [
        self::ACCOUNT_STATUS_NEW,
        self::ACCOUNT_STATUS_ACTIVE,
        self::ACCOUNT_STATUS_INACTIVE,
        self::ACCOUNT_STATUS_CANCELLED,
        self::ACCOUNT_STATUS_LOCKED,
        self::ACCOUNT_STATUS_EXPIRED,
        self::ACCOUNT_STATUS_GDPR_DELETED,
    ])]
    public string $status;

    /** @var Person|null Account owner information */
    public ?Person $owner;

    /** @var int Account's invoice country */
    public int $invoiceCountryId;

    /** @var int|null Account's parentAccountId, if set, this is a subAccount */
    public ?int $parentAccountId;

    /** @var bool|null Whether account is using sandbox or not */
    public ?bool $isSandboxAccount;

    /** @var bool|null Whether account is special or not */
    public ?bool $isSpecialAccount;

    /** @var Account|null Account's parent Account */
    #[LazyLoad(addAsParent: true)]
    public ?Account $parentAccount;

    /** @var string|null Account's partner externalId */
    public ?string $externalId;

    /** @var string Account's type */
    #[Choice(choices: [
        self::ACCOUNT_TYPE_STANDARD,
        self::ACCOUNT_TYPE_AGENCY_VIEWER,
        self::ACCOUNT_TYPE_RESELLER,
        self::ACCOUNT_TYPE_ADMIN,
        self::ACCOUNT_TYPE_EDITOR,
        self::ACCOUNT_TYPE_AUTHOR,
        self::ACCOUNT_TYPE_CONTRIBUTOR,
        self::ACCOUNT_TYPE_SUBSCRIBER
    ])]
    public string $type = self::ACCOUNT_TYPE_STANDARD;

    /** @var string|null Account's password */
    #[HideProperty]
    public ?string $password;

    /** @var Roles|null  Account's roles, determines privileges of this account */
    public ?Roles $roles;

    /** @var ContactInfos|null Account's contact infos */
    public ?ContactInfos $contactInfos;

    /** @var string|null Account's language code */
    public ?string $languageCode;

    /** @var int|null Number of active Projects */
    public ?int $totalNumberOfActiveProjects;

    /**
     * @return string
     */
    public function getUserIdentifier(): string
    {
        return (string)$this->id;
    }

    /**
     * Return symfony conform security roles
     *
     * @return array|string[]
     */
    public function getRoles(): array
    {
        $roles = [];
        foreach ($this->roles->elements as $role) {
            if (isset(self::ROLE_ASSOCIATIONS[$role->name])) {
                $roles[] = self::ROLE_ASSOCIATIONS[$role->name];
            }
        }
        // reseller users sometimes have no roles
        if (isset($this->apiAccountId) && $this->status === self::ACCOUNT_STATUS_ACTIVE) {
            $roles[] = self::ROLE_ASSOCIATIONS[Role::LOGIN];
        }

        if (!in_array(
                self::ROLE_ASSOCIATIONS[Role::LOGIN],
                $roles,
                true,
            )
            && $this->isCancelledAccountWithActiveSubscriptions()
        ) {
            $roles[] = self::ROLE_ASSOCIATIONS[Role::LOGIN];
        }
        return $roles;
    }

    /**
     * !!! IMPORTANT !!! IT IS NECESSARY TO USE THIS METHOD TO CHECK IF THE ACCOUNT IS AN ADMIN WITH ACCESS TO ALL RESOURCES
     * ALL ROLES BESIDES THE LOGIN ROLE ARE ADMIN ROLES IN THE CONTEXT OF THE roles->isAdmin() METHOD
     *
     * Checks if the user is an admin with access to all resources.
     *
     * This method checks if the user has the role "SUPERADMIN" and returns true if so.
     * If the user has the role "ADMIN" and is also an admin, it returns true.
     * Otherwise, it returns false.
     *
     * @return bool Returns true if the user is an admin with access to all resources, false otherwise.
     */
    public function isAdminWithAccessToAllResources(): bool
    {
        if ($this->roles->hasRoles(Role::SUPERADMIN)) {
            return true;
        }

        if (!$this->roles->isAdmin()) {
            return false;
        }

        if ($this->roles->hasRoles(Role::ADMIN)) {
            return true;
        }

        return false;
    }

    /**
     * @param bool $checkForActiveSubscriptions
     *
     * @return bool
     */
    public function isActive(bool $checkForActiveSubscriptions = false): bool
    {
        $isActive = $this->status === self::ACCOUNT_STATUS_ACTIVE;
        // If the account is not active, we won't check for active subscriptions
        if (!$isActive) {
            return false;
        }

        if ($checkForActiveSubscriptions) {
            $isActive = $this->hasActiveSubscriptions();
        }

        return $isActive;
    }

    /**
     * @return bool
     */
    protected function isCancelledAccountWithActiveSubscriptions(): bool
    {
        if ($this->status !== self::ACCOUNT_STATUS_CANCELLED) {
            return false;
        }
        if (!$this->subscriptions || count($this->subscriptions->getElements()) === 0) {
            return false;
        }
        return true;
    }

    /**
     * @return bool
     */
    private function hasActiveSubscriptions(): bool
    {
        return $this->subscriptions && count($this->subscriptions->getElements()) > 0;
    }

    /**
     * @param $accountId
     * @param bool $returnAsCode
     * @return string
     */
    public function getAccountLanguage($accountId, bool $returnAsCode = false): string
    {
        // Get user locale from meta
        $userLocale = get_user_meta($accountId, 'locale', true);

        // If user has no locale set, fallback to site language
        if (!$userLocale) {
            $userLocale = get_locale(); // Get WordPress default locale
        }

        if ($returnAsCode) {
            // Convert locale to language code (e.g., en_US -> en)
            return $this->localeToLanguageCode($userLocale);
        }

        // Convert locale to readable language name
        return $this->localeToLanguageName($userLocale);
    }

    /**
     * Convert WordPress locale (e.g., en_US) to human-readable language name.
     */
    public function localeToLanguageName($locale): string
    {
        $languages = [
            'en_US' => __('English', 'beyond-seo'),
            'en_GB' => __('English (UK)', 'beyond-seo'),
            'fr_FR' => __('French', 'beyond-seo'),
            'es_ES' => __('Spanish', 'beyond-seo'),
            'de_DE' => __('German', 'beyond-seo'),
            'it_IT' => __('Italian', 'beyond-seo'),
            'nl_NL' => __('Dutch', 'beyond-seo'),
            'ru_RU' => __('Russian', 'beyond-seo'),
            'pt_PT' => __('Portuguese', 'beyond-seo'),
            'zh_CN' => __('Chinese (Simplified)', 'beyond-seo'),
            'zh_TW' => __('Chinese (Traditional)', 'beyond-seo'),
            'ja_JP' => __('Japanese', 'beyond-seo'),
            'ko_KR' => __('Korean', 'beyond-seo'),
        ];

        return $languages[$locale] ?? __('Unknown', 'beyond-seo');
    }

    /**
     * Convert WordPress locale (e.g., en_US) to language code (e.g., en).
     */
    public function localeToLanguageCode($locale): string
    {
        $locale_mapping = [
            'en_US' => 'en',
            'en_GB' => 'en',
            'fr_FR' => 'fr',
            'es_ES' => 'es',
            'de_DE' => 'de',
            'it_IT' => 'it',
            'nl_NL' => 'nl',
            'ru_RU' => 'ru',
            'pt_PT' => 'pt',
            'zh_CN' => 'zh',
            'zh_TW' => 'zh',
            'ja_JP' => 'ja',
            'ko_KR' => 'ko',
        ];

        // Extract language code (first two letters) if not mapped explicitly
        return $locale_mapping[$locale] ?? substr($locale, 0, 2);
    }

    /**
     * @param $userId
     * @return string
     */
    public function setAccountWebsiteIfMissing($userId): string
    {
        $userInfo = get_userdata($userId);
        $userWebsite = $userInfo->user_url;

        if (empty($userWebsite)) {
            $siteUrl = get_site_url();
            wp_update_user([
                'ID' => $userId,
                'user_url' => esc_url($siteUrl),
            ]);

            return esc_url($siteUrl);
        }

        return $userWebsite;
    }
}
