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

namespace BackBee\DependencyInjection\Exception;

/**
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      e.chau <eric.chau@lp-digital.fr>
 */
class ServiceNotDumpableException extends \BackBee\Exception\BBException
{
    /**
     * ServiceNotDumpableException's constructor.
     *
     * @param string      $id    id of the service which is not dumapble
     * @param string|null $class class of the service which is not dumpable (can be null)
     */
    public function __construct($id, $class = null)
    {
        parent::__construct(sprintf(
            'You tagged %s as a dumpable service but it did not implement %s.',
            $id.(null !== $class ? " ($class)" : ''),
            'BackBee\DependencyInjection\Dumper\DumpableServiceInterface'
        ));
    }
}
