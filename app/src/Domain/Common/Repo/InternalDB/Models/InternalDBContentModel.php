<?php
declare(strict_types=1);

namespace App\Domain\Common\Repo\InternalDB\Models;

use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineModel;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represents a LegacyDBContentModel
 */
#[ORM\Entity]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
#[ORM\Table(name: 'posts')]
class InternalDBContentModel extends DoctrineModel
{
    public const MODEL_ALIAS = 'posts';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: '`ID`', type: 'bigint')]
    public int $ID;

    #[ORM\Column(name: '`post_author`', type: 'bigint')]
    public int $post_author;

    #[ORM\Column(name: '`post_date`', type: 'string')]
    public string $post_date;

    #[ORM\Column(name: '`post_date_gmt`', type: 'string')]
    public string $post_date_gmt;

    #[ORM\Column(name: '`post_content`', type: 'longtext')]
    public string $post_content;

    #[ORM\Column(name: '`post_title`', type: 'text')]
    public string $post_title;

    #[ORM\Column(name: '`post_excerpt`', type: 'text')]
    public string $post_excerpt;

    #[ORM\Column(name: '`post_status`', type: 'string')]
    public string $post_status;

    #[ORM\Column(name: '`comment_status`', type: 'string')]
    public string $comment_status;

    #[ORM\Column(name: '`ping_status`', type: 'string')]
    public string $ping_status;

    #[ORM\Column(name: '`post_password`', type: 'string')]
    public string $post_password;

    #[ORM\Column(name: '`post_name`', type: 'string')]
    public string $post_name;

    #[ORM\Column(name: '`to_ping`', type: 'text')]
    public string $to_ping;

    #[ORM\Column(name: '`pinged`', type: 'text')]
    public string $pinged;

    #[ORM\Column(name: '`post_modified`', type: 'string')]
    public string $post_modified;

    #[ORM\Column(name: '`post_modified_gmt`', type: 'string')]
    public string $post_modified_gmt;

    #[ORM\Column(name: '`post_content_filtered`', type: 'longtext')]
    public string $post_content_filtered;

    #[ORM\Column(name: '`post_parent`', type: 'bigint')]
    public int $post_parent;

    #[ORM\Column(name: '`guid`', type: 'string')]
    public string $guid;

    #[ORM\Column(name: '`menu_order`', type: 'integer')]
    public int $menu_order;

    #[ORM\Column(name: '`post_type`', type: 'string')]
    public string $post_type;

    #[ORM\Column(name: '`post_mime_type`', type: 'string')]
    public string $post_mime_type;

    #[ORM\Column(name: '`comment_count`', type: 'bigint')]
    public int $comment_count;

    #[ORM\ManyToOne(targetEntity: InternalDBUserModel::class)]
    #[ORM\JoinColumn(name: 'post_author', referencedColumnName: 'ID')]
    public ?InternalDBUserModel $author;
}
