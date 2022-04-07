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

namespace BackBee\Config;

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
class ConfigProxy extends Config implements DumpableServiceProxyInterface
{
    /**
     * ConfigProxy's constructor.
     *
     * @param array $dump
     */
    public function __construct()
    {
        $this->is_restored = false;
    }

    /**
     * Restore current service to the dump's state.
     *
     * @param array $dump the dump provided by DumpableServiceInterface::dump() from where we can
     *                    restore current service
     */
    public function restore(ContainerInterface $container, array $dump)
    {
        $this->basedir = $dump['basedir'];
        $this->raw_parameters = $dump['raw_parameters'];
        $this->environment = $dump['environment'];
        $this->debug = $dump['debug'];
        $this->yml_names_to_ignore = $dump['yml_names_to_ignore'];

        if (true === $dump['has_container']) {
            $this->setContainer($container);
        }

        if (true === $dump['has_cache']) {
            $this->setCache($container->get('cache.bootstrap'));
        }

        $this->is_restored = true;
    }
}
