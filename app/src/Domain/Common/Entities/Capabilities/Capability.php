<?php

namespace App\Domain\Common\Entities\Capabilities;

use DDD\Domain\Base\Entities\Entity;

/**
 * @method static Capability getByUniqueKey(string $uniqueKey)
 */
class Capability extends Entity
{
    public const MANAGE_OPTIONS = 'manage_options';
    public const INSTALL_PLUGINS = 'install_plugins';
    public const EDIT_USERS = 'edit_users';
    public const EDIT_OTHERS_POSTS = 'edit_others_posts';
    public const DELETE_POSTS = 'delete_posts';
    public const PUBLISH_POSTS = 'publish_posts';
    public const READ = 'read';

    /**
     * @var string|null Capability name.
     */
    public ?string $name = null;

    /**
     * Capability constructor.
     */
    public function __construct(?string $name = null)
    {
        $this->name = $name;
    }

    /**
     * Get capability name.
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