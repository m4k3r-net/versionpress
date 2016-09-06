<?php

namespace VersionPress\Storages;

use VersionPress\Actions\ActionsInfoProvider;
use VersionPress\ChangeInfos\ChangeInfoFactory;
use VersionPress\Database\Database;
use VersionPress\Database\DbSchemaInfo;
use VersionPress\Database\EntityInfo;
use VersionPress\Utils\ReferenceUtils;

class StorageFactory
{

    private $vpdbDir;
    private $dbSchemaInfo;

    private $storages = [];
    /** @var Database */
    private $database;
    private $taxonomies;
    /** @var ChangeInfoFactory */
    private $changeInfoFactory;

    /**
     * @param string $vpdbDir Path to the `wp-content/vpdb` directory
     * @param DbSchemaInfo $dbSchemaInfo Passed to storages
     * @param Database $database
     * @param string[] $taxonomies List of taxonomies used on current site
     * @param ChangeInfoFactory $changeInfoFactory
     */
    public function __construct($vpdbDir, DbSchemaInfo $dbSchemaInfo, $database, $taxonomies, $changeInfoFactory)
    {
        $this->vpdbDir = $vpdbDir;
        $this->dbSchemaInfo = $dbSchemaInfo;
        $this->database = $database;
        $this->taxonomies = $taxonomies;
        $this->changeInfoFactory = $changeInfoFactory;
    }

    /**
     * Returns storage by given entity type
     *
     * @param string $entityName
     * @return Storage|null
     */
    public function getStorage($entityName)
    {
        if (isset($this->storages[$entityName])) {
            return $this->storages[$entityName];
        }

        if ($this->dbSchemaInfo->isEntity($entityName)) {
            return $this->resolveStorageForEntity($entityName);
        }

        $mnReferenceDetails = $this->dbSchemaInfo->getMnReferenceDetails($entityName);
        if ($mnReferenceDetails !== null) {
            return $this->resolveStorageForMnReference($mnReferenceDetails);
        }

        return null;
    }

    public function getAllSupportedStorages()
    {
        return $this->dbSchemaInfo->getAllEntityNames();
    }

    private function resolveStorageForEntity($entityName)
    {
        $entityInfo = $this->dbSchemaInfo->getEntityInfo($entityName);

        if ($this->dbSchemaInfo->isChildEntity($entityName)) {
            $parentEntity = $entityInfo->references[$entityInfo->parentReference];
            $parentStorage = $this->getStorage($parentEntity);

            return new MetaEntityStorage($parentStorage, $entityInfo, $this->database->prefix, $this->changeInfoFactory);
        }

        return new DirectoryStorage($this->vpdbDir . '/' . $entityInfo->tableName, $entityInfo, $this->database->prefix, $this->changeInfoFactory);
    }

    private function resolveStorageForMnReference($referenceDetails)
    {
        $parentStorage = $this->resolveStorageForEntity($referenceDetails['source-entity']);
        return new MnReferenceStorage($parentStorage, $referenceDetails);
    }
}
