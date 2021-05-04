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

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use BackBee\AutoLoader\Exception\ClassNotFoundException;
use BackBee\ClassContent\AbstractContent;
use BackBee\Console\AbstractCommand;

/**
 * This command allows us to clean every orphans contents and its subcontents.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      e.chau <eric.chau@lp-digital.fr>
 */
class CleanOrphanContentCommand extends AbstractCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('content:clean_orphan')
            ->setDescription('Remove orphans contents and its sub contents')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $em = $this->getContainer()->get('bbapp')->getEntityManager();

        $orphans = $em->getConnection()->executeQuery(
            'SELECT c.uid, c.classname FROM content c
             LEFT JOIN content_has_subcontent sc ON sc.content_uid = c.uid
             LEFT JOIN page p ON p.contentset = c.uid
             LEFT JOIN media m ON m.content_uid = c.uid
             WHERE sc.content_uid IS NULL AND p.contentset IS NULL AND m.content_uid IS NULL'
        )->fetchAll();

        $contents_count = $em->getConnection()->executeQuery('SELECT count(*) FROM content')->fetch(\PDO::FETCH_NUM);
        $before_contents_count = $contents_count[0];
        $output->writeln(
            "\nBefore cleaning, content table contains $before_contents_count row(s)"
            ." (including ".count($orphans)." potentials orphans).\n"
        );

        foreach ($orphans as $orphan) {
            try {
                $classname = AbstractContent::getFullClassname($orphan['classname']);
                $orphan_object = $em->find($classname, $orphan['uid']);
                $em->getRepository($classname)->deleteContent($orphan_object);
            } catch (ClassNotFoundException $e) {
                $uid = $orphan['uid'];
                $em->getConnection()->executeUpdate(
                    "DELETE FROM content_has_subcontent WHERE content_uid = '$uid' OR parent_uid = '$uid'"
                );
                $em->getConnection()->executeUpdate("DELETE FROM revision WHERE content_uid = '$uid'");
                $em->getConnection()->executeUpdate("DELETE FROM content WHERE uid = '$uid'");
            }
        }

        $em->flush();

        $contents_count = $em->getConnection()->executeQuery('SELECT count(*) FROM content')->fetch(\PDO::FETCH_NUM);
        $rows_saved = $before_contents_count - $contents_count[0];
        $output->writeln("After cleaning, content table contains $contents_count[0] row(s) ($rows_saved row(s) saved).\n");
    }
}
