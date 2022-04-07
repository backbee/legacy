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
class ContainerDumpInvalidFormatException extends \BackBee\Exception\BBException
{
    /**
     * ContainerDumpInvalidFormatException's constructor.
     *
     * @param string $filepath the path to container dump file
     * @param string $type     the invalid type
     */
    public function __construct($filepath, $type)
    {
        parent::__construct("Content getted from $filepath is not a valid format: $type (array expected).");
    }
}
