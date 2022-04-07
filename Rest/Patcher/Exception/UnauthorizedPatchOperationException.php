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

namespace BackBee\Rest\Patcher\Exception;

use BackBee\Exception\BBException;

/**
 * Exception thrown if an operation with invalid syntax is provided.
 *
 * @category    BackBee
 *
 * 
 * @author      e.chau <eric.chau@lp-digital.fr>
 */
class UnauthorizedPatchOperationException extends BBException
{
    public function __construct($entity, $path, $operation)
    {
        parent::__construct("You are not allowed to $operation `$path` for ".get_class($entity).'.');
    }
}
