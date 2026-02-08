<?php
declare(strict_types=1);

namespace App\Domain\Common\Repo\InternalDB\Models;

use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineModel;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class InternalDBSeoOperationsModel
 */
#[ORM\Entity]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
#[ORM\Table(name: 'rankingcoach_seo_operations')]
#[ORM\UniqueConstraint(name: 'uq_factor_operation', columns: ['factorId', 'operationKey'])]
class InternalDBSeoOperationsModel extends DoctrineModel
{
    public const MODEL_ALIAS = 'seo_operation';
    public const TABLE_NAME = 'rankingcoach_seo_operations';

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'bigint', options: ['unsigned' => true])]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    public int $id;

    #[ORM\Column(name: 'factorId', type: 'bigint', options: ['unsigned' => true])]
    public int $factorId;

    #[ORM\Column(name: 'operationKey', type: 'string', length: 100)]
    public string $operationKey;

    #[ORM\Column(name: 'operationName', type: 'string', length: 255)]
    public string $operationName;

    #[ORM\Column(name: 'score', type: 'decimal', precision: 5, scale: 2)]
    public string $score;

    #[ORM\Column(name: 'weight', type: 'decimal', precision: 3, scale: 2)]
    public string $weight;

    #[ORM\Column(name: 'value', type: 'json', nullable: false)]
    public mixed $value = null;

    #[ORM\Column(name: 'suggestions', type: 'text', nullable: true)]
    public ?string $suggestions = null;
}
