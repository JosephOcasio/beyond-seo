<?php
declare(strict_types=1);

namespace App\Domain\Common\Repo\InternalDB\Models;

use DateTime;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineModel;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class InternalDBSeoOptimiserModel
 *
 */
#[ORM\Entity]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
#[ORM\Table(name: 'rankingcoach_seo_optimisers')]
class InternalDBSeoOptimisersModel  extends DoctrineModel
{
    public const MODEL_ALIAS = 'seo_optimiser';
    public const TABLE_NAME = 'rankingcoach_seo_optimisers';

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'bigint', options: ['unsigned' => true])]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    public int $id;

    #[ORM\Column(name: 'postId', type: 'integer', options: ['unsigned' => true])]
    public int $postId;

    #[ORM\Column(name: 'overallScore', type: 'decimal', precision: 5, scale: 2, options: ['unsigned' => true])]
    public string $overallScore;

    #[ORM\Column(name: 'analysisDate', type: 'datetime')]
    public DateTime $analysisDate;
}