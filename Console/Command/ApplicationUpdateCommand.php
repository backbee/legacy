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

namespace BackBee\Console\Command;

use BackBee\BBApplication;
use BackBee\Exception\BBException;
use BackBee\Util\Doctrine\EntityManagerCreator;
use BackBee\Exception\DatabaseConnectionException;
use Doctrine\DBAL\Event\SchemaAlterTableEventArgs;
use Doctrine\DBAL\Events;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

use BackBee\Console\AbstractCommand;

/**
 * Update BBApp database.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      k.golovin
 */
class ApplicationUpdateCommand extends AbstractCommand
{

    /**
     * The current entity manager
     * @var \Doctrine\ORM\EntityManager
     */
    private $em;

    /**
     * The current BackBee application
     * @var \BackBee\BBApplication
     */
    private $bbapp;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('bbapp:update')
            ->addOption('drop', null, InputOption::VALUE_NONE, 'Drop all tables found in DB')
            ->addOption('force', null, InputOption::VALUE_NONE, 'The update SQL will be executed against the DB')
            ->addOption('host', 'host', InputOption::VALUE_OPTIONAL, 'server host.')
            ->addOption('port', 'port', InputOption::VALUE_OPTIONAL, 'server port.')
            ->addOption('user', 'user', InputOption::VALUE_OPTIONAL, 'server user.')
            ->addOption('password', 'pwd', InputOption::VALUE_OPTIONAL, 'server password.')
            ->setDescription('Updated bbapp')
            ->setHelp(<<<EOF
The <info>%command.name%</info> updates app:

   <info>php bbapp:update</info>
EOF
            )
        ;
    }

    /**
     * Initiate doctrine connection for the Command on master database if its configure
     *
     * @param object       $input       The input option of command
     * @param object       $output      The output of command
     *
     * @throws \DatabaseConnectionException When Unable to connect to database
     */
    protected function initConnection($input, $output)
    {

        if (null !== $input->getOption('host')) {
            $connection['host'] = $input->getOption('host');
        }

        if (null !== $input->getOption('port')) {
            $connection['port'] = $input->getOption('port');
        }

        if (null !== $input->getOption('user')) {
            $connection['user'] = $input->getOption('user');
        }

        if (null !== $input->getOption('password')) {
            $connection['password'] = $input->getOption('password');
        }

        $doctrine_config = $this->bbapp->getConfig()->getDoctrineConfig();

        if(isset($connection['user']) && isset($connection['password'])) {
            if (isset($doctrine_config['dbal']['master'])) {
                $doctrine_config['dbal']['master'] = array_merge($doctrine_config['dbal']['master'], $connection);
            } else {
                $doctrine_config['dbal'] = array_merge($doctrine_config['dbal'], $connection);
            }
        }

        // DISABLE CACHE DOCTRINE
        unset($doctrine_config['dbal']['metadata_cache_driver']);
        unset($doctrine_config['dbal']['query_cache_driver']);

        if (!array_key_exists('proxy_ns', $doctrine_config['dbal'])) {
            $doctrine_config['dbal']['proxy_ns'] = 'Proxies';
        }

        if (!array_key_exists('proxy_dir', $doctrine_config['dbal'])) {
            $doctrine_config['dbal']['proxy_dir'] = $this->bbapp->getCacheDir() . '/' . 'Proxies';
        }

        try {
            $em = EntityManagerCreator::create($doctrine_config['dbal']);

            if (isset($doctrine_config['dbal']['master'])) {
                $em->getConnection()
                    ->connect('master');
            } else {
                $em->getConnection()
                    ->connect();
            }

        } catch (\Exception $e) {
            throw new DatabaseConnectionException(
                'Unable to connect to the database.', 0, $e);
        }

        return $em;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $force = $input->getOption('force');
        $drop = $input->getOption('drop');

        $this->bbapp = $this->getContainer()->get('bbapp');

        $this->em = $this->initConnection($input, $output);

        $this->checkBeforeUpdate();

        $this->em->getConfiguration()->getMetadataDriverImpl()->addPaths([
            $this->bbapp->getBBDir().DIRECTORY_SEPARATOR.'Bundle',
            $this->bbapp->getBBDir().DIRECTORY_SEPARATOR.'Cache'.DIRECTORY_SEPARATOR.'DAO',
            $this->bbapp->getBBDir().DIRECTORY_SEPARATOR.'ClassContent',
            $this->bbapp->getBBDir().DIRECTORY_SEPARATOR.'ClassContent'.DIRECTORY_SEPARATOR.'Indexes',
            $this->bbapp->getBBDir().DIRECTORY_SEPARATOR.'Logging',
            $this->bbapp->getBBDir().DIRECTORY_SEPARATOR.'NestedNode',
            $this->bbapp->getBBDir().DIRECTORY_SEPARATOR.'Security',
            $this->bbapp->getBBDir().DIRECTORY_SEPARATOR.'Site',
            $this->bbapp->getBBDir().DIRECTORY_SEPARATOR.'Stream'.DIRECTORY_SEPARATOR.'ClassWrapper',
            $this->bbapp->getBBDir().DIRECTORY_SEPARATOR.'Util'.DIRECTORY_SEPARATOR.'Sequence'.DIRECTORY_SEPARATOR.'Entity',
            $this->bbapp->getBBDir().DIRECTORY_SEPARATOR.'Workflow',
        ]);

        $this->em->getConfiguration()->getMetadataDriverImpl()->addExcludePaths([
            $this->bbapp->getBBDir().DIRECTORY_SEPARATOR.'ClassContent'.DIRECTORY_SEPARATOR.'Tests',
            $this->bbapp->getBBDir().DIRECTORY_SEPARATOR.'NestedNode'.DIRECTORY_SEPARATOR.'Tests',
            $this->bbapp->getBBDir().DIRECTORY_SEPARATOR.'Security'.DIRECTORY_SEPARATOR.'Tests',
            $this->bbapp->getBBDir().DIRECTORY_SEPARATOR.'Util'.DIRECTORY_SEPARATOR.'Tests',
            $this->bbapp->getBBDir().DIRECTORY_SEPARATOR.'Workflow'.DIRECTORY_SEPARATOR.'Tests',
        ]);

        if (is_dir($this->bbapp->getBBDir().DIRECTORY_SEPARATOR.'vendor')) {
            $this->em->getConfiguration()->getMetadataDriverImpl()->addExcludePaths([$this->bbapp->getBBDir().DIRECTORY_SEPARATOR.'vendor']);
        }

        $sqls = $this->getUpdateQueries();

        if ($force || $drop) {
            $metadata = $this->em->getMetadataFactory()->getAllMetadata();
            $schema = new SchemaTool($this->em);

            if ($drop) {
                $sqls = array_merge($schema->getDropDatabaseSQL(), $sqls);
            }

            if ($force) {
                $output->writeln('<info>Running drop/update</info>');

                $this->em->getConnection()->executeUpdate('SET FOREIGN_KEY_CHECKS=0');
                $drop ? $schema->dropDatabase() : '';
                $schema->updateSchema($metadata, true);
                $this->em->getConnection()->executeUpdate('SET FOREIGN_KEY_CHECKS=1');
            }
        }

        $output->writeln(
            ($force ? '<info>SQL executed: </info>' : '<info>SQL to be executed: </info>').PHP_EOL.implode(";".PHP_EOL, $sqls).''
        );
    }

    /**
     * Checks the db if section feature is already available for version > 1.1
     * @throws \BBException                 Raises if section features is not available
     */
    private function checkBeforeUpdate()
    {
        if (0 <= version_compare(BBApplication::VERSION, '1.1')) {
            $schemaManager = $this->em->getConnection()->getSchemaManager();
            $pageName = $this->em->getClassMetadata('BackBee\NestedNode\Page')->getTableName();
            $sectionName = $this->em->getClassMetadata('BackBee\NestedNode\Section')->getTableName();

            if (false === $schemaManager->tablesExist($sectionName) && true === $schemaManager->tablesExist($pageName)) {
                throw new BBException(sprintf('Table `%s` does not exist. Perhaps you should launch bbapp:upgradeToPageSection command before.', $sectionName));
            }
        }
    }

    /**
     * Get update queries.
     *
     * @return string[]
     */
    protected function getUpdateQueries()
    {
        $schema = new SchemaTool($this->em);

        $metadatas = $this->em->getMetadataFactory()->getAllMetadata();

        // Insure the name of altered tables are quoted according to the platform
        $this->em->getEventManager()->addEventListener(Events::onSchemaAlterTable, $this);

        $sqls = $schema->getUpdateSchemaSql($metadatas, true);

        return $sqls;
    }

    /**
     * Insures the name of the altered table is quoted according to the platform/
     * 
     * @param SchemaAlterTableEventArgs $args
     */
    public function onSchemaAlterTable(SchemaAlterTableEventArgs $args)
    {
        $tableDiff = $args->getTableDiff();
        $tableDiff->name = $tableDiff->fromTable->getQuotedName($args->getPlatform());
    }
}
