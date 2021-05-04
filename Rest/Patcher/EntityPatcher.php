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

use BackBee\Rest\Patcher\Exception\UnauthorizedPatchOperationException;

/**
 * EntityPatcher helps you to apply patch operations on your entity/object according to
 * a list of rights.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      e.chau <eric.chau@lp-digital.fr>
 */
class EntityPatcher implements PatcherInterface
{
    /**
     * the right manager which decide if a patch operation is valid or not.
     *
     * @var BackBee\Rest\Patcher\RightManager
     */
    private $rightManager;

    /**
     * EntityPatcher's constructor.
     *
     * @param BackBee\Rest\Patcher\RightManager $rightManager the right manager which decide if it's a valid
     *                                                        patch operation or not
     */
    public function __construct(RightManager $manager)
    {
        $this->setRightManager($manager);
    }

    /**
     * @see BackBee\Rest\Patcher\PatcherInterface::setRights
     */
    public function setRightManager(RightManager $manager)
    {
        $this->rightManager = $manager;

        return $this;
    }

    /**
     * @see BackBee\Rest\Patcher\PatcherInterface::setRights
     */
    public function getRightManager()
    {
        return $this->rightManager;
    }

    /**
     * @see BackBee\Rest\Patcher\PatcherInterface::patch
     */
    public function patch($entity, array $operations, $on_invalid_operation = self::EXCEPTION_ON_INVALID_OPERATION)
    {
        foreach ($operations as $operation) {
            $this->applyPatch($entity, $operation);
        }
    }

    /**
     * [applyPatch description].
     *
     * @param [type] $entity    [description]
     * @param array  $operation [description]
     */
    private function applyPatch($entity, array $operation)
    {
        if (false === $this->rightManager->authorized($entity, $operation['path'], $operation['op'])) {
            throw new UnauthorizedPatchOperationException($entity, $operation['path'], $operation['op']);
        }

        if (PatcherInterface::REPLACE_OPERATION === $operation['op']) {
            $method = $this->buildMethodName($operation['path'], 'set');
            $entity->$method($operation['value']);
        }
    }

    /**
     * [buildMethodName description].
     *
     * @param [type] $path   [description]
     * @param [type] $prefix [description]
     *
     * @return [type] [description]
     */
    private function buildMethodName($path, $prefix = '')
    {
        $method = $prefix;
        foreach (explode('_', str_replace('/', '', $path)) as $word) {
            $method .= ucfirst($word);
        }

        return $method;
    }
}
