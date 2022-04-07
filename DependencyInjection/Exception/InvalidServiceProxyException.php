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

namespace BackBee\DependencyInjection\Exception;

/**
 * @category    BackBee
 *
 * 
 * @author      e.chau <eric.chau@lp-digital.fr>
 */
class InvalidServiceProxyException extends \BackBee\Exception\BBException
{
    /**
     * InvalidServiceProxyException's constructor.
     *
     * @param string $classname the classname of the service which must implements DumpableServiceProxyInterface
     */
    public function __construct($classname)
    {
        $interface = 'BackBee\DependencyInjection\Dumper\DumpableServiceProxyInterface';

        parent::__construct("$classname must implements $interface to be a valid service proxy.");
    }
}
