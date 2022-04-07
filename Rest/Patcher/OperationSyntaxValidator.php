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

namespace BackBee\Rest\Patcher;

use BackBee\Rest\Patcher\Exception\InvalidOperationSyntaxException;

/**
 * @category    BackBee
 *
 *
 * @author      e.chau <eric.chau@lp-digital.fr>
 */
class OperationSyntaxValidator
{
    /**
     * @param array $operations
     *
     * @throws InvalidOperationSyntaxException
     */
    public function validate(array $operations)
    {
        foreach ($operations as $operation) {
            if (!is_array($operation) || !array_key_exists('op', $operation)) {
                throw new InvalidOperationSyntaxException('`op` key is missing.');
            }

            switch ($operation['op']) {
                case PatcherInterface::TEST_OPERATION:
                case PatcherInterface::ADD_OPERATION:
                case PatcherInterface::REPLACE_OPERATION:
                    if (!isset($operation['path']) || !isset($operation['value'])) {
                        throw new InvalidOperationSyntaxException('`path` and/or `value` key is missing.');
                    }

                    break;
                case PatcherInterface::TEST_OPERATION:
                case PatcherInterface::TEST_OPERATION:
                    if (!isset($operation['from'])) {
                        throw new InvalidOperationSyntaxException('`from` key is missing.');
                    }
                case PatcherInterface::TEST_OPERATION:
                    if (!isset($operation['path'])) {
                        throw new InvalidOperationSyntaxException('`path` key is missing.');
                    }

                    break;
                default:
                    throw new InvalidOperationSyntaxException('Invalid operation name: '.$operation['op']);
            }
        }
    }
}
