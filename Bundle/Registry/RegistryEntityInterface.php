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

namespace BackBee\Bundle\Registry;

/**
 * @category    BackBee
 *
 *
 * @author n.dufreche <nicolas.dufreche@lp-digital.fr>
 */
interface RegistryEntityInterface
{
    /**
     * Return all class properties.
     *
     * @return array(property_name => property_value)
     */
    public function getObjectProperties();

    /**
     * Set all class properties.
     *
     * @param string $property the property name
     * @param mixed  $value    the property value
     */
    public function setObjectProperty($property, $value);

    /**
     * Set class class identifier.
     *
     * @param sting|integer $property;
     */
    public function setObjectIdentifier($property);
}
