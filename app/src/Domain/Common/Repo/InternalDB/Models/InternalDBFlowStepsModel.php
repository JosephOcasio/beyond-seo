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
#[ORM\Table(name: 'rankingcoach_setup_steps')]
class InternalDBFlowStepsModel extends DoctrineModel
{
    public const MODEL_ALIAS = 'flow_step';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer', options: ['unsigned' => true])]
    public int $id;

    #[ORM\Column(name: 'step', type: 'string', length: 255)]
    public string $step;

    #[ORM\Column(name: 'requirements', type: 'string', length: 255)]
    public string $requirements;

    #[ORM\Column(name: 'priority', type: 'integer')]
    public int $priority;

    #[ORM\Column(name: 'isFinalStep', type: 'boolean')]
    public bool $isFinalStep;

    #[ORM\Column(name: 'active', type: 'boolean')]
    public bool $active;

    #[ORM\Column(name: 'completed', type: 'boolean')]
    public bool $completed;

    #[ORM\Column(name: 'userSaveCount', type: 'integer')]
    public int $userSaveCount = 0;
}