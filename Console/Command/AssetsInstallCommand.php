<?php

/*
 * Copyright (c) 2022 Obione
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

use BackBee\Console\AbstractCommand;
use BackBee\Util\File\Dir;

/**
 * Install BBApp assets.
 *
 * @category    BackBee
 *
 *
 * @author      k.golovin
 */
class AssetsInstallCommand extends AbstractCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('assets:install')
            ->setDescription('Updated bbapp')
            ->setHelp(<<<EOF
The <info>%command.name%</info> install app assets:

   <info>php assets:install</info>
EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $bbapp = $this->getContainer()->get('bbapp');

        $publicResourcesDir = $bbapp->getBaseDir().'/public/ressources';

        $bbappResourcesDir = $bbapp->getBBDir().'/Resources';
        $repoResourcesDir = $bbapp->getBaseRepository().'/Ressources';

        Dir::copy($repoResourcesDir, $publicResourcesDir, 0755);
        Dir::copy($bbappResourcesDir, $publicResourcesDir, 0755);

        foreach ($bbapp->getBundles() as $bundle) {
            Dir::copy($bundle->getResourcesDir(), $publicResourcesDir, 0755);
        }
    }
}
