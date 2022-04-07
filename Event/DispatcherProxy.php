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

namespace BackBee\Event;

use BackBee\DependencyInjection\ContainerInterface;
use BackBee\DependencyInjection\Dumper\DumpableServiceProxyInterface;

/**
 * This interface must be implemented if you want to use a proxy class instead of your service real class.
 *
 * @category    BackBee
 *
 *
 * @author      e.chau <eric.chau@lp-digital.fr>
 */
class DispatcherProxy extends Dispatcher implements DumpableServiceProxyInterface
{
    /**
     * DispatcherProxy's constructor.
     */
    public function __construct()
    {
        $this->_is_restored = false;
    }

    /**
     * Restore current service to the dump's state.
     *
     * @param array $dump the dump provided by DumpableServiceInterface::dump() from where we can
     *                    restore current service
     */
    public function restore(ContainerInterface $container, array $dump)
    {
        $this->_application = $container->get('bbapp');

        foreach ($dump['listeners'] as $event_name => $priorities) {
            foreach ($priorities as $priority => $listeners) {
                foreach ($listeners as $listener) {
                    $this->addListener($event_name, $listener, $priority);
                }
            }
        }

        if (true === $dump['has_application']) {
            $this->application = ($container->get('bbapp'));
        }

        if (true === $dump['has_container']) {
            $this->container = $container;
        }

        $this->_is_restored = true;
    }
}
