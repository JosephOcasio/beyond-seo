<?php
declare( strict_types=1 );

namespace App\Domain\Common\Repo\InternalDB\Models;

use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineModel;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represents a LegacyDBContentMetaModel
 */
#[ORM\Entity]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
#[ORM\Table(name: 'postmeta')]
class InternalDBContentMetaModel  extends DoctrineModel
{
	public const MODEL_ALIAS = 'postmeta';

	#[ORM\Id]
	#[ORM\Column(name: 'meta_id', type: 'bigint', options: ['unsigned' => true])]
	#[ORM\GeneratedValue(strategy: 'AUTO')]
	public int $meta_id;

	#[ORM\Column(name: 'post_id', type: 'bigint', options: ['unsigned' => true])]
	public int $post_id;

	#[ORM\Column(name: 'meta_key', type: 'string', length: 255)]
	public string $meta_key;

	#[ORM\Column(name: 'meta_value', type: 'longtext')]
	public string $meta_value;

	#[ORM\ManyToOne(targetEntity: InternalDBContentModel::class)]
	#[ORM\JoinColumn(name: 'post_id', referencedColumnName: 'ID')]
	public ?InternalDBContentModel $content;
}