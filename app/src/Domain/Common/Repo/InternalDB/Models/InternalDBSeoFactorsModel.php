<?php
declare(strict_types=1);

namespace App\Domain\Common\Repo\InternalDB\Models;

use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineModel;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class InternalDBSeoFactorsModel
 */
#[ORM\Entity]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
#[ORM\Table(name: 'rankingcoach_seo_factors')]
#[ORM\UniqueConstraint(name: 'uq_context_factor', columns: ['contextId', 'factorKey'])]
class InternalDBSeoFactorsModel extends DoctrineModel
{
    public const MODEL_ALIAS = 'seo_factor';
    public const TABLE_NAME = 'rankingcoach_seo_factors';

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'bigint', options: ['unsigned' => true])]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    public int $id;

    #[ORM\Column(name: 'contextId', type: 'bigint', options: ['unsigned' => true])]
    public int $contextId;

    #[ORM\Column(name: 'factorKey', type: 'string', length: 50)]
    public string $factorKey;

    #[ORM\Column(name: 'factorName', type: 'string', length: 255)]
    public string $factorName;

    #[ORM\Column(name: 'description', type: 'text', nullable: true)]
    public ?string $description = null;

    #[ORM\Column(name: 'weight', type: 'decimal', precision: 3, scale: 2)]
    public string $weight;

    #[ORM\Column(name: 'score', type: 'decimal', precision: 5, scale: 2)]
    public string $score;

    #[ORM\Column(name: 'fetchedData', type: 'json', nullable: false)]
    public array $fetchedData = [];
}
