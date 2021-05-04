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
use BackBee\Console\AbstractCommand;
use BackBee\Exception\BBException;
use BackBee\Event\Listener\PageListener;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Upgrade data structure adding section support
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      Nicolas Dufreche <nicolas.dufreche@lp-digital.fr>
 */
class UpgradeToSectionHasChildrenCommand extends AbstractCommand
{

    /**
     * Skip the updating of the existing nested pages data
     * @var boolean
     */
    private $skipNodesUpdate;

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
     * The section repository
     * @var \BackBee\NestedNode\Repository\SectionRepository
     */
    private $repo;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('migration:upgradeToSectionHasChildren')
            ->addOption('memory-limit', 'm', InputOption::VALUE_OPTIONAL, 'The memory limit to set.')
            ->setDescription('Upgrade BackBee Section table')
            ->setHelp(<<<EOF
This command introduce section has_children feature and updates data storage of pages:

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
        $this->repo = $this->em->getRepository('BackBee\NestedNode\Section');
        $this->output = $output;

        if (null !== $input->getOption('memory-limit')) {
            ini_set('memory_limit', $input->getOption('memory-limit'));
        }

        $this->checksBackBeeVersion()
                ->checksSectionTable()
                ->updateSections();

        $this->output->writeln(sprintf('<info>UPGRADE DONE IN %d s.</info>', ceil((microtime() - $startTime) / 1000)));
    }

    /**
     * Checks for BackBee version, at least 1.1.0 is required
     *
     * @return \BackBee\Console\Command\UpgradeToPageSectionCommand
     * @throws BBException                                              Raises if version is previous to 1.1.0
     */
    private function checksBackBeeVersion()
    {
        $this->output->writeln('<info>Checking BackBee instance</info>');
        $this->output->write(sprintf(' - BackBee version: %s - ', BBApplication::VERSION));

        if (0 > version_compare(BBApplication::VERSION, '1.1')) {
            $this->output->writeln("<error>Failed</error>");
            throw new BBException(sprintf('This command needs at least BackBee v1.1.0 installed, gets BackBee v%s.%sPlease upgrade your distribution.', BBApplication::VERSION, PHP_EOL));
        }

        $this->output->writeln('<info>OK</info>');
        return $this;
    }

    /**
     * Checks for existing `has_children` attribut in table `section`, throw exception if `has_children` doesn't exists
     *
     * @return \BackBee\Console\Command\UpgradeToPageSectionCommand
     * @throws BBException  Raises if `has_children` doesn't exists
     */
    private function checksSectionTable()
    {
        $qb = $this->repo->createQueryBuilder('s');
        $this->output->write(' - BackBee database version:  - ');
        try {
            $qb->where('s._has_children = :true')
                ->orWhere('s._has_children = :false')
                ->setMaxResults(1)
                ->setParameters([':true' => true, ':false' => false])
                ->getQuery()
                ->execute();
        } catch (\Exception $e) {
            $this->output->writeln("<error>Failed</error>");
            throw new BBException('Please run "backbee bbapp:update" before this command');
        }
        $this->output->writeln('<info>OK</info>');

        return $this;
    }

    /**
     * Updates `has_section` data from existing section
     *
     *
     * @return \BackBee\Console\Command\UpgradeToPageSectionCommand
     */
    private function updateSections()
    {
        $sections = $this->repo->findAll();
        foreach ($sections as $section) {
            PageListener::setSectionHasChildren($this->em, $section);
        }
        $this->em->flush();

        return $this;
    }
}
