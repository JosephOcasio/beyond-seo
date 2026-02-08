<?php
        declare(strict_types=1);

        namespace App\Domain\Common\Repo\InternalDB\Models;
        
        use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineModel;
        use Doctrine\ORM\Mapping as ORM;
        use Doctrine\ORM\PersistentCollection;

        #[ORM\Entity]
        #[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
        #[ORM\Table(name: 'roles')]
        class InternalDBRoleModel extends DoctrineModel
        {
            public const MODEL_ALIAS = 'role';
            
        #[ORM\Id]
                #[ORM\GeneratedValue]
                #[ORM\Column(type: 'integer', name:'`id`')]
            public int $id;
            
            #[ORM\Column(type: 'string', name:'`name`')]
            public ?string $name;
            
            #[ORM\Column(type: 'string', name:'`description`')]
            public ?string $description;
            
            #[ORM\Column(type: 'integer', name:'`isAdminRole`')]
            public ?int $isAdminRole;
            
            /** @var InternalDBUserModel[] */
            #[ORM\ManyToMany(targetEntity: InternalDBUserModel::class)]
                #[ORM\JoinTable(name: 'roles_users')]
                #[ORM\JoinColumn(name: '`role_id`', referencedColumnName: 'id')]
                #[ORM\InverseJoinColumn(name: '`user_id``', referencedColumnName: 'id')]
                public array|PersistentCollection $users;
            
            /** @var InternalDBRolesUserModel[] */
            #[ORM\OneToMany(targetEntity: InternalDBRolesUserModel::class, mappedBy: 'role')]
                public array|PersistentCollection $roles_users;
            
            
        }
