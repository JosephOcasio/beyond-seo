<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Common\Entities\Accounts\Legacy;

use App\Domain\Common\Entities\Accounts\Account;
use App\Domain\Common\Entities\Capabilities\Capabilities;
use App\Domain\Common\Entities\Capabilities\Capability;
use App\Domain\Common\Entities\Roles\Role;
use App\Domain\Common\Entities\Roles\Roles;
use App\Domain\Integrations\WordPress\Common\Entities\Accounts\Legacy\Settings\WPLegacyAccountSettings;
use App\Domain\Integrations\WordPress\Common\Repo\InternalDB\Accounts\Legacy\InternalDBWPLegacyAccount;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoad;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use DDD\Infrastructure\Traits\Serializer\Attributes\HideProperty;
use Doctrine\ORM\Mapping\MappingException;
use Exception;
use Psr\Cache\InvalidArgumentException;
use ReflectionException;

/**
 * WordPress Account
 */
#[LazyLoadRepo(repoType: LazyLoadRepo::INTERNAL_DB, repoClass: InternalDBWPLegacyAccount::class)]
class WPLegacyAccount extends Account
{
    #============================================
    # region Attributes
    #============================================
    /** @var int|null $id The ID of the user. */
    public ?int $id = null;

    /** @var string $displayName The display name of the user. */
    public string $displayName;

    /** @var string $userLogin The login name of the user. */
    public string $userLogin;

    /** @var string $userNicename The nice name of the user. */
    public string $userNicename;

    /** @var string $userEmail The email address of the user. */
    public string $userEmail;

    /** @var string $userUrl The URL of the user. */
    public string $userUrl;

    /** @var string $userRegistered The date the user registered on the site. */
    public string $userRegistered;

    /** @var string $userActivationKey The activation key for the user. */
    public string $userActivationKey = '';

    /** @var int $userStatus The status of the user. */
    public int $userStatus = 0;

    /** @var Roles|null $roles The roles of the user. */
    public ?Roles $userRoles;

    /** @var Capabilities|null $capabilities The capabilities of the user. */
    public ?Capabilities $userCapabilities;

    /** @var false|mixed|null $rcAccessToken The access token for the account. */
    #[HideProperty]
    public string $rcAccessToken;

    /** @var WPLegacyAccountSettings|null $accountSettings settings The meta-data of the user. */
    #[LazyLoad]
    public ?WPLegacyAccountSettings $accountSettings;

    /** @var string|null The unique key of the content */
    public ?string $uniqueKey = null;

    # endregion
    #============================================
    #============================================
    # region Constructor and Methods
    #============================================

    /**
     * WPAccount constructor.
     * @throws Exception
     */
    public function __construct(?int $id = null)
    {
        if($id) {
            $this->id = $id;
        }
        $this->accountSettings = new WPLegacyAccountSettings();
        $this->uniqueKey = $this->uniqueKey();
        parent::__construct();
    }

    /**
     * Retrieves a unique key for the task.
     * @return string
     * @throws Exception
     */
    public function uniqueKey(): string
    {
        try {
            $hash = $this->getContentUniqueHash();
            if(!$this->id && $hash) {
                return $hash;
            }
            elseif($this->id && $this->uniqueKey) {
                return $this->uniqueKey;
            }
            return self::uniqueKeyStatic(parent::uniqueKey() . '_' . spl_object_hash($this));
        } catch (Exception $e) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            /* translators: %s is the error message */
            throw new Exception(sprintf(__('Failed to generate unique key: %s', 'beyond-seo'), $e->getMessage()));
        }
    }

    /**
     * Retrieves the unique key of the content.
     * @param array $roles
     * @return Roles
     */
    public function setRoles(array $roles = []): Roles
    {
        $rolesSet = new Roles();
        if(!empty($roles)) {
            foreach ($roles as $roleName) {
                $role = new Role($roleName);
                $rolesSet->add($role);
            }
        }
        $this->userRoles = $rolesSet;
        return $rolesSet;
    }

    /**
     * Retrieves the unique key of the content.
     * @param array $capabilities
     * @return Capabilities
     */
    public function setCapabilities(array $capabilities = []): Capabilities
    {
        $capabilitiesSet = new Capabilities();
        if(!empty($capabilities)) {
            foreach ($capabilities as $capabilityName => $capabilityValue) {
                if($capabilityValue) {
                    $capability = new Capability($capabilityName);
                    $capabilitiesSet->add($capability);
                }
            }
        }
        $this->userCapabilities = $capabilitiesSet;
        return $capabilitiesSet;
    }

    /**
     * Retrieves the account by its ID.
     * @return WPLegacyAccount|null
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws \Doctrine\DBAL\Exception
     * @throws MappingException
     */
    public function getById(): ?WPLegacyAccount
    {
        $repo = new InternalDBWPLegacyAccount();
        return $repo->findById($this->id);
    }

    /**
     * Retrieves the hash of the essential content elements to create a unique key.
     * @param bool $addTime Whether to include the current timestamp in the hash
     * @return string
     */
    private function getContentUniqueHash(bool $addTime = false): string
    {
        return md5(json_encode([
            'login' => $this->userLogin ?? '',
            'email' => $this->userEmail ?? '',
            'nicename' => $this->userNicename ?? '',
            'url' => $this->userUrl ?? '',
            'registered' => $this->userRegistered ?? null,
            'displayName' => $this->displayName ?? '',
            'time' => $addTime ? microtime(true) : '',
        ]));
    }
    # endregion
    #============================================
}
