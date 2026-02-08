<?php
declare(strict_types=1);

namespace App\Domain\Common\Repo\InternalDB\Models;

use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineModel;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class InternalDBAppKeywordModel
 */
#[ORM\Entity]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
#[ORM\Table(name: 'rankingcoach_app_keywords')]
class InternalDBAppKeywordModel extends DoctrineModel
{
    public const MODEL_ALIAS = 'app_keyword';

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'bigint', options: ['unsigned' => true])]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    public int $id;

    #[ORM\Column(name: 'name', type: 'string', length: 255)]
    public string $name;

    #[ORM\Column(name: 'alias', type: 'string', length: 255, nullable: true)]
    public ?string $alias = null;

    #[ORM\Column(name: 'hash', type: 'string', length: 255, nullable: true)]
    public ?string $hash = null;

    #[ORM\Column(name: 'externalId', type: 'bigint', nullable: true, options: ['unsigned' => true])]
    public ?int $externalId = null;

}
