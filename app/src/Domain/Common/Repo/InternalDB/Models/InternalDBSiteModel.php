<?php
declare(strict_types=1);

namespace App\Domain\Common\Repo\InternalDB\Models;

use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineModel;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represents a LegacyDBSiteModel
 */
#[ORM\Entity]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
#[ORM\Table(name: 'options')]
class InternalDBSiteModel  extends DoctrineModel
{
    public const MODEL_ALIAS = 'site_options';

    #[ORM\Column(name: '`site_url`', type: 'string')]
    public string $site_url;

    #[ORM\Column(name: '`home_url`', type: 'string')]
    public string $home_url;

    #[ORM\Column(name: '`blog_name`', type: 'string')]
    public string $blog_name;

    #[ORM\Column(name: '`blog_description`', type: 'text')]
    public string $blog_description;

    #[ORM\Column(name: '`admin_email`', type: 'string')]
    public string $admin_email;

    #[ORM\Column(name: '`site_language`', type: 'string')]
    public string $site_language;

    #[ORM\Column(name: '`is_multisite`', type: 'boolean')]
    public bool $is_multisite;

    #[ORM\Column(name: '`active_plugins`', type: 'string')]
    public string $active_plugins;

    #[ORM\Column(name: '`template`', type: 'string')]
    public string $template;

    #[ORM\Column(name: '`stylesheet`', type: 'string')]
    public string $stylesheet;

    #[ORM\Column(name: '`wp_version`', type: 'string')]
    public string $wp_version;

    #[ORM\Column(name: '`theme`', type: 'string')]
    public string $theme;

    #[ORM\Column(name: '`theme_version`', type: 'string')]
    public string $theme_version;

    #[ORM\Column(name: '`theme_author`', type: 'string')]
    public string $theme_author;

    #[ORM\Column(name: '`permalink_structure`', type: 'string')]
    public string $permalink_structure;
}