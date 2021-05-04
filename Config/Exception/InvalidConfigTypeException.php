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

namespace BackBee\Config\Exception;

/**
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      e.chau <eric.chau@lp-digital.fr>
 */
class InvalidConfigTypeException extends \BackBee\Exception\BBException
{
    /**
     * InvalidConfigTypeException's constructor.
     *
     * @param string $method       method of Configurator which raise this exception
     * @param string $invalid_type the invalid type provided by user
     */
    public function __construct($method, $invalid_type)
    {
        parent::__construct(sprintf(
            'You provided invalid type (:%s) for Config\Configurator::%s(). Only %s and %s are supported.',
            $invalid_type,
            $method,
            '0 (=Config\Configurator::APPLICATION_CONFIG)',
            '1 (=Config\Configurator::BUNDLE_CONFIG)'
        ));
    }
}
