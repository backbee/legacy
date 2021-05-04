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

use BackBee\ClassContent\AbstractContent;
use BackBee\Console\AbstractCommand;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Upgrade stored descriminators on content to use short classname
 *
 * @category    BackBee
 * 
 * @copyright   Lp digital system
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class UpgradeToShortClassnameCommand extends AbstractCommand
{

    /**
     * Output interface
     * @var \Symfony\Component\Console\Output\OutputInterface 
     */
    private $output;

    /**
     * The current entity manager
     * @var \Doctrine\ORM\EntityManager
     */
    private $em;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('migration:upgradeToShortClassname')
                ->addOption('memory-limit', 'm', InputOption::VALUE_OPTIONAL, 'The memory limit to set.')
                ->setDescription('Upgrade stored descriminators on content to use short classname')
                ->setHelp(<<<EOF
This command upgrades stored descriminators on content to use short classname:

   <info>php %command.name%</info>
EOF
                );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $startTime = microtime();

        $this->em = $this->getContainer()->get('em');
        $this->output = $output;

        if (null !== $input->getOption('memory-limit')) {
            ini_set('memory_limit', $input->getOption('memory-limit'));
        }

        $this->updateContentTable()
                ->updateRevisionTable()
                ->updateOptContentModifiedTable();

        $this->output->writeln(sprintf('<info>UPGRADE DONE IN %d s.</info>', ceil((microtime() - $startTime) / 1000)));
    }

    /**
     * Updates the `content` table.
     * 
     * @return UpgradeToShortClassnameCommand
     */
    private function updateContentTable()
    {
        $metadata = $this->em->getClassMetadata('BackBee\ClassContent\AbstractClassContent');
        $table = $metadata->getTableName();
        $field = $metadata->discriminatorColumn['name'];

        return $this->executeUpdate($table, $field);
    }

    /**
     * Updates the `revision` table.
     * 
     * @return UpgradeToShortClassnameCommand
     */
    private function updateRevisionTable()
    {
        $metadata = $this->em->getClassMetadata('BackBee\ClassContent\Revision');
        $table = $metadata->getTableName();
        $field = $metadata->getColumnName('_classname');

        return $this->executeUpdate($table, $field);
    }

    /**
     * Updates the `opt_content_modified` table.
     * 
     * @return UpgradeToShortClassnameCommand
     */
    private function updateOptContentModifiedTable()
    {
        $metadata = $this->em->getClassMetadata('BackBee\ClassContent\Indexes\OptContentByModified');
        $table = $metadata->getTableName();
        $field = $metadata->getColumnName('_classname');

        return $this->executeUpdate($table, $field);
    }

    /**
     * Executes an update query and output the number of affected rows.
     * 
     * @param  string   $table      The table to update
     * @param  string   $field      The field to update
     * 
     * @return UpgradeToShortClassnameCommand
     */
    private function executeUpdate($table, $field)
    {
        $result = $this->em->getConnection()->executeUpdate($this->getReplaceQuery($table, $field));        
        $this->output->writeln(sprintf(' - %d rows updated in `%s`.', $result, $table));

        return $this;
    }

    /**
     * Computes the SQL query to search/replace on $field in $table
     * 
     * @param  string   $table      The table to update
     * @param  string   $field      The field to update
     * 
     * @return string               The SQL query
     */
    private function getReplaceQuery($table, $field)
    {
        return sprintf(
            'UPDATE %s SET %s = REPLACE(%s, "%s", "%s") WHERE 1=1', 
            $table, 
            $field,
            $field,
            str_replace('\\', '\\\\', AbstractContent::CLASSCONTENT_BASE_NAMESPACE),
            ''
        );
    }

}
