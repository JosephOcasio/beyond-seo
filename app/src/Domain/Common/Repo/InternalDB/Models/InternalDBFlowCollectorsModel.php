<?php
declare(strict_types=1);

namespace App\Domain\Common\Repo\InternalDB\Models;

use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineModel;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represents a InternalDBFlowCollectorsModel
 */
#[ORM\Entity]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
#[ORM\Table(name: 'rankingcoach_setup_collectors')]
class InternalDBFlowCollectorsModel extends DoctrineModel
{
    public const MODEL_ALIAS = 'flow_collector';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer', options: ['unsigned' => true])]
    public int $id;

    #[ORM\Column(name: 'collector', type: 'string', length: 255)]
    public string $collector;

    #[ORM\Column(name: 'settings', type: 'text', nullable: true)]
    public ?string $settings = null;

    #[ORM\Column(name: 'className', type: 'string', length: 255)]
    public string $className;

    #[ORM\Column(name: 'priority', type: 'integer')]
    public int $priority;

    #[ORM\Column(name: 'active', type: 'boolean')]
    public bool $active;
}
