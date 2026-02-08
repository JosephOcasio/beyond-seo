<?php
declare(strict_types=1);

namespace App\Domain\Common\Repo\InternalDB\Models;

use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineModel;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class InternalDBWPCategoriesModel
 */
#[ORM\Entity]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
#[ORM\Table(name: 'rankingcoach_setup_categories')]
class InternalDBWPCategoriesModel extends DoctrineModel
{
    public const MODEL_ALIAS = 'rankingcoach_setup_categories';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer', options: ['unsigned' => true])]
    public int $id;

    #[ORM\Column(name: 'categoryId', type: 'integer', options: ['unsigned' => true])]
    public int $categoryId;

    #[ORM\Column(name: 'name', type: 'text', nullable: false)]
    public string $name;

    #[ORM\Column(name: 'externalId', type: 'text', nullable: false)]
    public string $externalId;

}
