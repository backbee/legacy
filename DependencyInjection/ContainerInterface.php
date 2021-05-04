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

namespace BackBee\DependencyInjection;

use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\TaggedContainerInterface;

/**
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      e.chau <eric.chau@lp-digital.fr>
 */
interface ContainerInterface extends TaggedContainerInterface
{
    const DUMPABLE_SERVICE_TAG = 'dumpable';

    /**
     * @see Symfony\Component\DependencyInjection\ContainerBuilder::setDefinition
     */
    public function setDefinition($id, Definition $definition);

    /**
     * @see Symfony\Component\DependencyInjection\ContainerBuilder::getDefinition
     */
    public function getDefinition($id);

    /**
     * @see Symfony\Component\DependencyInjection\ContainerBuilder::hasDefinition
     */
    public function hasDefinition($id);

    /**
     * @see Symfony\Component\DependencyInjection\ContainerBuilder::getDefinitions
     */
    public function getDefinitions();
}
