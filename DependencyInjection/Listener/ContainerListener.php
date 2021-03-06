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

namespace BackBee\DependencyInjection\Listener;

use BackBee\DependencyInjection\ContainerProxy;
use BackBee\DependencyInjection\Dumper\PhpArrayDumper;
use BackBee\DependencyInjection\Exception\CannotCreateContainerDirectoryException;
use BackBee\DependencyInjection\Exception\ContainerDirectoryNotWritableException;
use BackBee\Event\Event;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;

/**
 * @category    BackBee
 *
 * 
 * @author      e.chau <eric.chau@lp-digital.fr>
 */
class ContainerListener
{
    /**
     * Occurs on event ``bbapplication.init`` to dump application container if debug mode is false.
     *
     * @param Event $event
     *
     * @throws \BackBee\DependencyInjection\Exception\CannotCreateContainerDirectoryException
     * @throws \BackBee\DependencyInjection\Exception\ContainerDirectoryNotWritableException
     */
    public static function onApplicationInit(Event $event): void
    {
        $application = $event->getTarget();
        $container = $application->getContainer();

        if ($application->isDebugMode() === false && $container->isRestored() === false) {
            $containerFilename = $container->getParameter('container.filename');
            $containerDir = $container->getParameter('container.dump_directory');

            if (is_dir($containerDir) === false && !mkdir($containerDir, 0755) && !is_dir($containerDir)) {
                throw new CannotCreateContainerDirectoryException($containerDir);
            }

            if (is_writable($containerDir) === false) {
                throw new ContainerDirectoryNotWritableException($containerDir);
            }

            $dumper = new PhpArrayDumper($container);

            $dump = $dumper->dump(['do_compile' => true]);

            $container_proxy = new ContainerProxy();
            $dump = unserialize($dump);
            $container_proxy->init($dump);
            $container_proxy->setParameter('services_dump', serialize($dump['services']));
            $container_proxy->setParameter('is_compiled', $dump['is_compiled']);

            file_put_contents(
                $containerDir . DIRECTORY_SEPARATOR . $containerFilename . '.php',
                (new PhpDumper($container_proxy))->dump([
                    'class' => $containerFilename,
                    'base_class' => ContainerProxy::class,
                ])
            );
        } elseif ($application->isDebugMode() === true && $container->isRestored() === false) {
            $container->compile();
        }
    }
}
