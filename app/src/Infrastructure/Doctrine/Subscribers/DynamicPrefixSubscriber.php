<?php

namespace App\Infrastructure\Doctrine\Subscribers;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use wpdb;

class DynamicPrefixSubscriber
{
    private string $prefix;

    public function __construct(wpdb $wpdb)
    {
        $this->prefix = $wpdb->prefix;
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        global $wpdb;
        $currentPrefix = $wpdb->prefix ?? $this->prefix;

        // Detect change in runtime (multisite switch, provisioning, etc.)
        if ($currentPrefix !== $this->prefix) {
            // Optional: log or revert depending on your policy
            // error_log("Prefix changed from {$this->prefix} to {$currentPrefix}");
            $this->prefix = $currentPrefix;
        }

        $classMetadata = $eventArgs->getClassMetadata();

        if (!$classMetadata->isInheritanceTypeSingleTable() || $classMetadata->getName() === $classMetadata->rootEntityName) {
            $classMetadata->setPrimaryTable([
                'name' => $this->prefix . $classMetadata->getTableName()
            ]);
        }

        foreach ($classMetadata->getAssociationMappings() as $fieldName => $mapping) {
            if ($mapping['type'] == ClassMetadata::MANY_TO_MANY && $mapping['isOwningSide']) {
                $mappedTableName = $mapping['joinTable']['name'];
                $classMetadata->associationMappings[$fieldName]['joinTable']['name'] = $this->prefix . $mappedTableName;
            }
        }
    }
}