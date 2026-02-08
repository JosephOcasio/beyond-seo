<?php
declare(strict_types=1);

namespace App\Domain\Common\Repo\InternalDB\Models;

use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineModel;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represents the RankingCoach App Account Model
 */
#[ORM\Entity]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
#[ORM\Table(name: 'rankingcoach_app_account')]
class InternalDBAccountModel extends DoctrineModel
{
    public const MODEL_ALIAS = 'app_account';

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'bigint', options: ['unsigned' => true])]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    public int $id;

    #[ORM\Column(name: 'externalId', type: 'string', length: 255)]
    public string $externalId;

    #[ORM\Column(name: 'status', type: 'string', length: 50)]
    public string $status;

    #[ORM\Column(name: 'type', type: 'string', length: 50)]
    public string $type;

    #[ORM\Column(name: 'email', type: 'string', length: 255)]
    public string $email;

    #[ORM\Column(name: 'languageCode', type: 'string', length: 10)]
    public string $languageCode;

    #[ORM\Column(name: 'resellerId', type: 'bigint', nullable: true, options: ['unsigned' => true])]
    public ?int $resellerId;

    #[ORM\Column(name: 'owner', type: 'text', nullable: true)]
    public ?string $owner;

    #[ORM\Column(name: 'isSandboxAccount', type: 'boolean', options: ['default' => 0])]
    public bool $isSandboxAccount;

    #[ORM\Column(name: 'isSpecialAccount', type: 'boolean', options: ['default' => 0])]
    public bool $isSpecialAccount;

    #[ORM\Column(name: 'contactInfos', type: 'text', nullable: true)]
    public ?string $contactInfos;

    #[ORM\Column(name: 'settings', type: 'text', nullable: true)]
    public ?string $settings;

    #[ORM\Column(name: 'totalNumberOfActiveProjects', type: 'integer', nullable: true, options: ['default' => 0])]
    public ?int $totalNumberOfActiveProjects;

    #[ORM\Column(name: 'objectType', type: 'string', length: 255, nullable: true)]
    public ?string $objectType;

    // Define any relationships, getters, setters, or utility methods below
}
