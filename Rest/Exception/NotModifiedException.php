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

namespace BackBee\Rest\Exception;

use BackBee\Exception\BBException;

/**
 * Body listener/encoder.
 *
 * @category    BackBee
 *
 *
 * @author      k.golovin
 */
class NotModifiedException extends BBException
{
    const NOT_MODIFIED = 304;

    /**
     * @param ConstraintViolationList $violations
     */
    public function __construct($message = "Not Modified", $code = self::NOT_MODIFIED, \Exception $previous = null, $source = null, $seek = null)
    {
        parent::__construct($message, $code, $previous, $source, $seek);
    }
}
