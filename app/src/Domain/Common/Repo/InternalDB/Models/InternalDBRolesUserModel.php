<?php
        declare(strict_types=1);

        namespace App\Domain\Common\Repo\InternalDB\Models;
        
        use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineModel;
        use Doctrine\ORM\Mapping as ORM;

        #[ORM\Entity]
        #[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
        #[ORM\Table(name: 'roles_users')]
        class InternalDBRolesUserModel extends DoctrineModel
        {
            public const MODEL_ALIAS = 'roles_user';
            
        #[ORM\Id]
                #[ORM\Column(type: 'integer', name:'`user_id`')]
            public int $user_id;
            
            #[ORM\Id]
                #[ORM\Column(type: 'integer', name:'`role_id`')]
            public int $role_id;
            
            #[ORM\ManyToOne(targetEntity: InternalDBUserModel::class)]
            #[ORM\JoinColumn(name: '`user_id`', referencedColumnName: 'id')]
            public ?InternalDBUserModel $user;
            
            #[ORM\ManyToOne(targetEntity: InternalDBRoleModel::class)]
            #[ORM\JoinColumn(name: '`role_id`', referencedColumnName: 'id')]
            public ?InternalDBRoleModel $role;
            
            
        }
