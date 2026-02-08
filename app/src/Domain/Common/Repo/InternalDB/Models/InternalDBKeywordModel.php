<?php
declare(strict_types=1);

namespace App\Domain\Common\Repo\InternalDB\Models;

use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineModel;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\PersistentCollection;

#[ORM\Entity]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
#[ORM\Table(name: 'keywords')]
class InternalDBKeywordModel extends DoctrineModel
{
    public const MODEL_ALIAS = 'keyword';

    public const TABLE_NAME = 'keywords';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer', name: '`id`')]
    public int $id;

    #[ORM\Column(type: 'string', name: '`keyword`')]
    public ?string $keyword;

    #[ORM\Column(type: 'string', name: '`alias`')]
    public ?string $alias;

    #[ORM\Column(type: 'string', name: '`positions_checked`')]
    public ?string $positions_checked;

    #[ORM\Column(type: 'string', name: '`is_local`')]
    public ?string $is_local;

    #[ORM\Column(type: 'integer', name: '`parent_id`')]
    public ?int $parent_id;

    #[ORM\Column(type: 'integer', name: '`modified`')]
    public ?int $modified;

    #[ORM\ManyToOne(targetEntity: InternalDBKeywordModel::class)]
    #[ORM\JoinColumn(name: '`parent_id`', referencedColumnName: 'id')]
    public ?InternalDBKeywordModel $parent;

    /** @var InternalDBSiteModel[] */
    #[ORM\ManyToMany(targetEntity: InternalDBSiteModel::class)]
    #[ORM\JoinTable(name: 'keyword_sites')]
    #[ORM\JoinColumn(name: '`keyword_id`', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: '`site_id``', referencedColumnName: 'id')]
    public array|PersistentCollection $sites;

    /** @var LegacyDBKeywordSiteModel[] */
    #[ORM\OneToMany(targetEntity: LegacyDBKeywordSiteModel::class, mappedBy: 'keyword')]
    public array|PersistentCollection $keyword_sites;


}
