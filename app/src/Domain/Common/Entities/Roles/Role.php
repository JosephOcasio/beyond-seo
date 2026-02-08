<?php

namespace App\Domain\Common\Entities\Roles;

use App\Domain\Common\Repo\InternalDB\Roles\InternalDBRole;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;
use DDD\Domain\Common\Entities\Roles\Role as BaseRole;

/**
 * Role entity
 */
#[LazyLoadRepo(LazyLoadRepo::INTERNAL_DB, InternalDBRole::class)]
class Role extends BaseRole
{

    public const ADMINISTRATOR = 'administrator';
    public const EDITOR = 'editor';
    public const AUTHOR = 'author';
    public const CONTRIBUTOR = 'contributor';
    public const SUBSCRIBER = 'subscriber';
    public const SUPER_ADMIN = 'super_admin'; // pentru multisite

    /**
     * @var string|null Role name.
     */
    public ?string $name = null;

    /**
     * Role constructor.
     */
    public function __construct(?string $name = null)
    {
        $this->name = $name;
        $this->isAdminRole = in_array($name, self::ADMIN_ROLES);
        parent::__construct();
    }

    /**
     * Get role name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get unique key.
     */
    public function uniqueKey(): string
    {
        if (!isset($this->id) && isset($this->name)) {
            return self::uniqueKeyStatic($this->name);
        }
        return parent::uniqueKey();
    }
}