<?php
declare(strict_types=1);

namespace App\Domain\Common\Repo\InternalDB\Models;

use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineModel;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represents a LegacyDBOptionModel
 */
#[ORM\Entity]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
#[ORM\Table(name: 'options')]
class InternalDBOptionModel extends DoctrineModel
{
    public const MODEL_ALIAS = 'options';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: '`option_id`', type: 'bigint')]
    public int $option_id;

    #[ORM\Column(name: '`option_name`', type: 'string', length: 191)]
    public string $option_name;

    #[ORM\Column(name: '`option_value`', type: 'longtext')]
    public string $option_value;

    #[ORM\Column(name: '`autoload`', type: 'string', length: 20)]
    public string $autoload;
}