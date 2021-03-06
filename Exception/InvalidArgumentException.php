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

namespace BackBee\Exception;

/**
 * Class InvalidArgumentException
 *
 * Exception thrown if an invalid argument is provided.
 *
 * @package BackBee\Exception
 *
 * @author c.rouillon <charles.rouillon@lp-digital.fr>
 * @author Mickaël Andrieu <mickael.andrieu@lp-digital.fr>
 */
class InvalidArgumentException extends BBException
{
    /**
     * The default error code.
     *
     * @var int
     */
    protected $_code = self::INVALID_ARGUMENT;

    /**
     * InvalidArgumentException constructor.
     *
     * @param string $message
     */
    public function __construct($message = "")
    {
        $message = empty($message) ? 'Invalid argument exception.' : $message;

        parent::__construct($message, 0, null);
    }
}
