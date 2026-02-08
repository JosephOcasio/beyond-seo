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
#[ORM\Table(name: 'rankingcoach_setup_completions')]
class InternalDBFlowCompletionsModel extends DoctrineModel
{
    public const MODEL_ALIAS = 'flow_completion';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer', options: ['unsigned' => true])]
    public int $id;

    #[ORM\Column(name: 'stepId', type: 'integer')]
    public int $stepId;

    #[ORM\Column(name: 'collectorId', type: 'integer', nullable: true)]
    public ?int $collectorId = null;

    #[ORM\Column(name: 'questionId', type: 'integer', nullable: true)]
    public ?int $questionId = null;

    #[ORM\Column(name: 'answer', type: 'string', length: 255)]
    public string $answer;

    #[ORM\Column(name: 'data', type: 'text', nullable: true)]
    public ?string $data = null;

    #[ORM\Column(name: 'timeOfCompletion', type: 'integer', nullable: true)]
    public ?int $timeOfCompletion = null;

    #[ORM\Column(name: 'isCompleted', type: 'boolean')]
    public bool $isCompleted;

    #[ORM\ManyToOne(targetEntity: InternalDBFlowCollectorsModel::class)]
    #[ORM\JoinColumn(name: 'collectorId', referencedColumnName: 'id')]
    public InternalDBFlowCollectorsModel $collector;

    #[ORM\ManyToOne(targetEntity: InternalDBFlowStepsModel::class)]
    #[ORM\JoinColumn(name: 'stepId', referencedColumnName: 'id')]
    public ?InternalDBFlowStepsModel $step;
}
