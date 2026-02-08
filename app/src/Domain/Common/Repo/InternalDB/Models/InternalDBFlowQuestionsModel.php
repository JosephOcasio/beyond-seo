<?php
declare(strict_types=1);

namespace App\Domain\Common\Repo\InternalDB\Models;

use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineModel;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represents a question in the WordPress setup flow
 */
#[ORM\Entity]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
#[ORM\Table(name: 'rankingcoach_setup_questions')]
class InternalDBFlowQuestionsModel extends DoctrineModel
{
    public const MODEL_ALIAS = 'flow_question';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer', options: ['unsigned' => true])]
    public int $id;

    #[ORM\Column(name: 'parentId', type: 'integer', nullable: true)]
    public ?int $parentId = null;

    #[ORM\Column(name: 'stepId', type: 'integer')]
    public int $stepId;

    #[ORM\Column(name: 'question', type: 'text')]
    public string $question;

    #[ORM\Column(name: 'sequence', type: 'integer')]
    public int $sequence = 1;

    #[ORM\Column(name: 'aiContext', type: 'text', nullable: true)]
    public ?string $aiContext = null;

    #[ORM\Column(name: 'isAiGenerated', type: 'boolean')]
    public bool $isAiGenerated = false;

    #[ORM\ManyToOne(targetEntity: InternalDBFlowStepsModel::class)]
    #[ORM\JoinColumn(name: 'stepId', referencedColumnName: 'id')]
    public ?InternalDBFlowStepsModel $step;
}