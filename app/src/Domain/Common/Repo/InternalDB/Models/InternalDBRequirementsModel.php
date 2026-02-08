<?php
declare(strict_types=1);

namespace App\Domain\Common\Repo\InternalDB\Models;

use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineModel;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
#[ORM\Table(name: 'rankingcoach_setup')]
class InternalDBRequirementsModel extends DoctrineModel
{
    public const MODEL_ALIAS = 'rankingcoach_setup';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer', options: ['unsigned' => true])]
    public int $id;

    #[ORM\Column(name: 'setupRequirement', type: 'text', nullable: false)]
    public string $setupRequirement;

    #[ORM\Column(name: 'entityAlias', type: 'text', nullable: false)]
    public string $entityAlias;

    #[ORM\Column(name: 'value', type: 'text', nullable: true)]
    public ?string $value = null;

}
