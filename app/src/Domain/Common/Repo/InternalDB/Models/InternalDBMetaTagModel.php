<?php
declare( strict_types=1 );

namespace App\Domain\Common\Repo\InternalDB\Models;

use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineModel;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class LegacyDBMetaTagModel
 */
#[ORM\Entity]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
#[ORM\Table(name: 'rankingcoach_metatags')]
class InternalDBMetaTagModel extends DoctrineModel
{
	public const MODEL_ALIAS = 'metaTags';

	#[ORM\Id]
	#[ORM\Column(name: 'id', type: 'bigint', options: ['unsigned' => true])]
	#[ORM\GeneratedValue(strategy: 'AUTO')]
	public int $id;

	#[ORM\Column(name: 'post_id', type: 'bigint', options: ['unsigned' => true])]
	public int $post_id;

	#[ORM\Column(name: 'type', type: 'string', length: 255)]
	public string $type;

	#[ORM\Column(name: 'content', type: 'text')]
	public string $content;

	#[ORM\Column(name: 'template', type: 'text')]
	public string $template;

	#[ORM\Column(name: 'auto_generated', type: 'boolean')]
	public bool $auto_generated;

	#[ORM\Column(name: 'variables', type: 'text')]
	public string $variables;

	#[ORM\Column(name: 'unique_key', type: 'string', length: 255)]
	public string $unique_key;

    #[ORM\ManyToOne(targetEntity: InternalDBContentModel::class)]
    #[ORM\JoinColumn(name: 'post_id', referencedColumnName: 'ID')]
    public ?InternalDBContentModel $post;
}
