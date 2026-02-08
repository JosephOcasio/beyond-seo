<?php
declare(strict_types=1);

namespace App\Domain\Common\Repo\InternalDB\Models;

use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineModel;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represents a LegacyDBUserMetaModel
 */
#[ORM\Entity]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
#[ORM\Table(name: 'usermeta')]
class InternalDBUserMetaModel extends DoctrineModel
{
    public const MODEL_ALIAS = 'usermeta';

    #[ORM\Id]
    #[ORM\Column(name: 'umeta_id', type: 'bigint', options: ['unsigned' => true])]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    public int $umeta_id;

    #[ORM\Column(name: 'user_id', type: 'bigint', options: ['unsigned' => true])]
    public int $user_id;

    #[ORM\Column(name: 'meta_key', type: 'string', length: 255)]
    public string $meta_key;

    #[ORM\Column(name: 'meta_value', type: 'longtext')]
    public string $meta_value;

    #[ORM\ManyToOne(targetEntity: InternalDBUserModel::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'ID')]
    public ?InternalDBUserModel $user;
}