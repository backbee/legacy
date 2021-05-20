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

use BackBee\Exception\BBException;

/**
 * Class MissingParametersContainerDumpException
 *
 * @package BackBee\DependencyInjection\Exception
 *
 * @author  e.chau <eric.chau@lp-digital.fr>
 */
class MissingParametersContainerDumpException extends BBException
{
    /**
     * MissingParametersContainerDumpException's constructor.
     */
    public function __construct(array $missing_parameters)
    {
        parent::__construct(
            sprintf(
                'The container dump array is missing parameters/services/aliases/services_dump/is_compiled key(s): %s.',
                implode(', ', $missing_parameters)
            )
        );
    }
}
