<?php

/*
 * Copyright (c) 2011-2021 Lp Digital
 *
 * This file is part of BackBee Standalone.
 *
 * BackBee is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with BackBee Standalone. If not, see <https://www.gnu.org/licenses/>.
 */

namespace BackBee\Installer;

use BackBee\BBApplication;
use BackBee\Bundle\AbstractBundle;
use Doctrine\DBAL\Event\SchemaAlterTableEventArgs;
use Doctrine\DBAL\Events;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use ReflectionClass;

/**
 * @category    BackBee
 *
 * @copyright   Lp system
 * @author      nicolas dufreche <n.dufreche@lp-digital.fr>
 */
class Database
{
    /**
     * @var \Doctrine\ORM\EntityManager
     */
    private $_em;

    /**
     * @var BBApplication
     */
    private $_application;

    /**
     * @var SchemaTool
     */
    private $_schemaTool;

    /**
     * @var EntityFinder
     */
    private $_entityFinder;

    /**
     * @param \BackBee\BBApplication      $application
     * @param \Doctrine\ORM\EntityManager $em
     */
    public function __construct(BBApplication $application, EntityManager $em = null)
    {
        $this->_application = $application;
        if (null === $em) {
            $this->_em = $this->_application->getEntityManager();
        } else {
            $this->_em = $em;
        }

        $platform = $this->_em->getConnection()->getDatabasePlatform();
        $platform->registerDoctrineTypeMapping('enum', 'string');

        // Insure the name of altered tables are quoted according to the platform
        $this->_em->getEventManager()->addEventListener(Events::onSchemaAlterTable, $this);

        $this->_schemaTool = new SchemaTool($this->_em);
        $this->_entityFinder = new EntityFinder(dirname($this->_application->getBBDir()));
    }

    /**
     * Create the BackBee schema.
     */
    public function createBackBeeSchema()
    {
        $classes = $this->getBackBeeSchema();
        try {
            $this->_schemaTool->dropSchema($classes);
            $this->_schemaTool->createSchema($classes);
        } catch (\Exception $e) {
            echo $e->getMessage() . "\n";
        }
    }

    /**
     * create all bundles schema.
     */
    public function createBundlesSchema()
    {
        foreach ($this->_application->getBundles() as $bundle) {
            $this->createBundleSchema($bundle->getId());
        }
    }

    /**
     * Create the bundle schema specified in param.
     *
     * @param string $bundleName
     */
    public function createBundleSchema($bundleName)
    {
        if (null === $bundle = $this->_application->getBundle($bundleName)) {
            return;
        }

        try {
            $schemaTool = new SchemaTool($bundle->getEntityManager());
            $classes = $this->getBundleSchema($bundle);
            $schemaTool->dropSchema($classes);
            $schemaTool->createSchema($classes);
            unset($schemaTool);
        } catch (\Exception $e) {
            echo $e->getMessage() . "\n";
        }
    }

    /**
     * Update BackBee schema.
     */
    public function updateBackBeeSchema(): void
    {
        $this->_schemaTool->updateSchema($this->getBackBeeSchema(), true);
    }

    /**
     * Update all bundles schema.
     */
    public function updateBundlesSchema(): void
    {
        foreach ($this->_application->getBundles() as $bundle) {
            $this->updateBundleSchema($bundle->getId());
        }
    }

    /**
     * Update app schema.
     */
    public function updateAppSchema(): void
    {
        $this->_schemaTool->updateSchema($this->getAppSchema(), true);
    }

    /**
     * update the bundle schema specified in param.
     *
     * @param string $bundleName
     */
    public function updateBundleSchema($bundleName)
    {
        if (null === $bundle = $this->_application->getBundle($bundleName)) {
            return;
        }

        try {
            $schemaTool = new SchemaTool($bundle->getEntityManager());
            $classes = $this->getBundleSchema($bundle);
            $schemaTool->updateSchema($classes, true);
            unset($schemaTool);
        } catch (\Exception $e) {
        }
    }

    /**
     * @return array
     */
    private function getBackBeeSchema(): array
    {
        $classes = [];

        foreach ($this->_entityFinder->getEntities($this->_application->getBBDir()) as $className) {
            $classes[] = $this->_em->getClassMetadata($className);
        }

        return $classes;
    }

    /**
     * Get app schema.
     *
     * @return array
     */
    private function getAppSchema(): array
    {
        $classes = [];

        foreach ($this->_entityFinder->getEntities($this->_application->getAppDir()) as $className) {
            $classes[] = $this->_em->getClassMetadata($className);
        }

        return $classes;
    }

    /**
     * @param \BackBee\Bundle\AbstractBundle $bundle
     *
     * @return array
     */
    private function getBundleSchema(AbstractBundle $bundle): array
    {
        $reflection = new ReflectionClass(get_class($bundle));
        $classes = [];

        foreach ($this->_entityFinder->getEntities(dirname($reflection->getFileName())) as $className) {
            $classes[] = $this->_em->getClassMetadata($className);
        }

        return $classes;
    }

    public function getSqlSchema()
    {
        $sql1 = $this->getBackBeeSqlSchema();
        $sql2 = $this->getBundleSqlSchema();

        $sql = array_merge($sql1, $sql2);

        $sql = implode(";\n", $sql);

        return $sql . ';';
    }

    private function getBackBeeSqlSchema()
    {
        $classes = $this->getBackBeeSchema();
        $sql = $this->_schemaTool->getCreateSchemaSql($classes);

        return $sql;
    }

    private function getBundleSqlSchema()
    {
        $sql = array();

        foreach ($this->_application->getBundles() as $bundle) {
            $classes = $this->getBundleSchema($bundle);

            $sql = array_merge($sql, $this->_schemaTool->getCreateSchemaSql($classes));
        }

        return $sql;
    }

    /**
     * @param int $type
     *
     * @return array
     */
    public function getUpdateSqlSchema($type = 3)
    {
        $sql1 = $sql2 = array();
        if ($type == 1 || $type & 3 == 3) {
            $sql1 = $this->getUpdateBackBeeSqlSchema();
        }
        if ($type == 2 || $type & 3 == 3) {
            $sql2 = $this->getUpdateBundleSqlSchema();
        }

        $sql = array_merge($sql1, $sql2);

        return $sql;
    }

    private function getUpdateBackBeeSqlSchema()
    {
        $classes = $this->getBackBeeSchema();

        $sql = $this->_schemaTool->getUpdateSchemaSql($classes, true);

        return $sql;
    }

    private function getUpdateBundleSqlSchema()
    {
        $sql = array();

        foreach ($this->_application->getBundles() as $bundle) {
            $classes = $this->getBundleSchema($bundle);

            $sql = array_merge($sql, $this->_schemaTool->getUpdateSchemaSql($classes, true));
        }

        return $sql;
    }

    public function getClassMetadata()
    {
        $classes1 = $this->getBackBeeClassMetadata();
        $classes2 = $this->getBundleClassMetadata();

        $classes = array_merge($classes1, $classes2);

        return $classes;
    }

    private function getBackBeeClassMetadata()
    {
        $classes = $this->getBackBeeSchema();

        return $classes;
    }

    private function getBundleClassMetadata()
    {
        $classes = array();

        foreach ($this->_application->getBundles() as $bundle) {
            $classes = array_merge($classes, $this->getBundleSchema($bundle));
        }

        return $classes;
    }

    /**
     * Insures the name of the altered table is quoted according to the platform.
     *
     * @param SchemaAlterTableEventArgs $args
     */
    public function onSchemaAlterTable(SchemaAlterTableEventArgs $args)
    {
        $tableDiff = $args->getTableDiff();
        $tableDiff->name = $tableDiff->fromTable->getQuotedName($args->getPlatform());
    }
}
