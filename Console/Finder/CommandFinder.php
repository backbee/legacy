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

namespace BackBee\Console\Finder;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Finder\Finder;

class CommandFinder extends Finder implements ContainerAwareInterface
{

    private $container;

    /**
     * Returns an Iterator for the current Finder configuration.
     *
     * This method implements the IteratorAggregate interface.
     *
     * @return \Iterator An iterator
     *
     * @throws \LogicException if the setContainer() method has not been called
     */
    public function getIterator($ns = 'BackBee\\Console\\Command')
    {
        if (null === $this->container) {
            throw new \LogicException('You must call setContainer() before iterating over this finder.');
        }
        $iterator = parent::getIterator();

        $result = new \ArrayIterator();
        foreach ($iterator as $file) {
            if ($relativePath = $file->getRelativePath()) {
                $ns .= '\\'.strtr($relativePath, '/', '\\');
            }

            $r = new \ReflectionClass($ns.'\\'.$file->getBasename('.php'));
            if (
                $r->isSubclassOf('BackBee\\Console\\AbstractCommand')
                && !$r->isAbstract()
                && !$r->getConstructor()->getNumberOfRequiredParameters()
            ) {
                $result->append($r->newInstance());
            }
        }

        return $result;
    }

    /**
     * Sets the Container.
     *
     * @param ContainerInterface|null $container A ContainerInterface instance or null
     *
     * @api
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

}
