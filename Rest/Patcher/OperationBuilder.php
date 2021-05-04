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

namespace BackBee\Rest\Patcher;

/**
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      k.golovin
 */
class OperationBuilder
{
    protected $operations = [];

    /**
     * @param string $path
     * @param mixed  $value
     *
     * @return \BackBee\Rest\Patcher\OperationBuilder
     */
    public function test($path, $value)
    {
        $this->operations[] = [
            "op" => PatcherInterface::TEST_OPERATION,
            "path" => $path,
            "value" => $value,
        ];

        return $this;
    }

    /**
     * @param string $path
     *
     * @return \BackBee\Rest\Patcher\OperationBuilder
     */
    public function remove($path)
    {
        $this->operations[] = [
            "op" => PatcherInterface::REMOVE_OPERATION,
            "path" => $path,
        ];

        return $this;
    }

    /**
     * @param string $path
     * @param mixed  $value
     *
     * @return \BackBee\Rest\Patcher\OperationBuilder
     */
    public function add($path, $value)
    {
        $this->operations[] = [
            "op" => PatcherInterface::ADD_OPERATION,
            "path" => $path,
            "value" => $value,
        ];

        return $this;
    }

    /**
     * @param string $path
     * @param mixed  $value
     *
     * @return \BackBee\Rest\Patcher\OperationBuilder
     */
    public function replace($path, $value)
    {
        $this->operations[] = [
            "op" => PatcherInterface::REPLACE_OPERATION,
            "path" => $path,
            "value" => $value,
        ];

        return $this;
    }

    /**
     * @param string $fromPath
     * @param mixed  $toPath
     *
     * @return \BackBee\Rest\Patcher\OperationBuilder
     */
    public function move($fromPath, $toPath)
    {
        $this->operations[] = [
            "op" => PatcherInterface::MOVE_OPERATION,
            "from" => $fromPath,
            "path" => $toPath,
        ];

        return $this;
    }

    /**
     * @param string $fromPath
     * @param mixed  $toPath
     *
     * @return \BackBee\Rest\Patcher\OperationBuilder
     */
    public function copy($fromPath, $toPath)
    {
        $this->operations[] = [
            "op" => PatcherInterface::COPY_OPEARATION,
            "from" => $fromPath,
            "path" => $toPath,
        ];

        return $this;
    }

    /**
     * @return array
     */
    public function getOperations()
    {
        return $this->operations;
    }
}
