<?php
declare(strict_types=1);

namespace App\Domain\Common\Repo\InternalDB\Models;

use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineModel;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class InternalDBSeoContextsModel
 */
#[ORM\Entity]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
#[ORM\Table(name: 'rankingcoach_seo_contexts')]
#[ORM\UniqueConstraint(name: 'uq_analysis_context', columns: ['analysisId', 'contextKey'])]
class InternalDBSeoContextsModel extends DoctrineModel
{
    public const MODEL_ALIAS = 'seo_context';
    public const TABLE_NAME = 'rankingcoach_seo_contexts';

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'bigint', options: ['unsigned' => true])]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    public int $id;

    #[ORM\Column(name: 'analysisId', type: 'bigint', options: ['unsigned' => true])]
    public int $analysisId;

    #[ORM\Column(name: 'contextKey', type: 'string', length: 50)]
    public string $contextKey;

    #[ORM\Column(name: 'contextName', type: 'string', length: 255)]
    public string $contextName;

    #[ORM\Column(name: 'weight', type: 'decimal', precision: 3, scale: 2)]
    public string $weight;

    #[ORM\Column(name: 'score', type: 'decimal', precision: 5, scale: 2)]
    public string $score;
}
