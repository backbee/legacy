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

namespace BackBee\AutoLoader\Exception;

use BackBee\Exception\BBException;

/**
 * Autoloader exception thrown if a class can not be load.
 *
 * Error codes defined are :
 *
 * * CLASS_NOTFOUND : none file or wrapper found for the given class name
 * * INVALID_OPCODE : the included file or wrapper contains invalid code
 * * INVALID_NAMESPACE : the syntax of the namespace is invalid
 * * INVALID_CLASSNAME : the syntax of the class name is invalid
 * * UNREGISTERED_NAMESPACE : the namespace is not registered
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class AutoloaderException extends BBException
{
    /**
     * None file or wrapper found for the given class name.
     *
     * @var int
     */
    const CLASS_NOTFOUND = 2001;

    /**
     * The included file or wrapper contains invalid code.
     *
     * @var int
     */
    const INVALID_OPCODE = 2002;

    /**
     * The syntax of the given namespace is invalid.
     *
     * @var int
     */
    const INVALID_NAMESPACE = 2003;

    /**
     * The syntax of the given class name is invalid.
     *
     * @var int
     */
    const INVALID_CLASSNAME = 2004;

    /**
     * The given namespace is not registered.
     *
     * @var int
     */
    const UNREGISTERED_NAMESPACE = 2005;
}
