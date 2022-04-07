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

namespace BackBee\AutoLoader\Exception;

/**
 * Exception thrown if the syntax of the class name is invalid.
 *
 * @category    BackBee
 *
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class InvalidClassnameException extends AutoloaderException
{
    /**
     * The default error code.
     *
     * @var int
     */
    protected $_code = self::INVALID_CLASSNAME;
}
