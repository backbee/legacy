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

namespace BackBee\Installer\Annotation;

/**
 * @Annotation
 */
class Fixture
{
    private $_values;

    public function __construct(array $options = array())
    {
        $this->_values = (object) $options;
    }

    public function getFixture()
    {
        $fixture = '$this->faker->'.$this->_values->type;
        if (property_exists($this->_values, 'value')) {
            $fixture .= '('.$this->_values->value.')';
        }

        return $fixture.';';
    }

    public function getType()
    {
        return function () use ($generator) { return $generator->{$this->_values->type}; };
    }

    public function __get($name)
    {
        if (property_exists($this->_values, $name)) {
            return $this->_values->{$name};
        }

        return false;
    }

    public function __isset($name)
    {
        return property_exists($this->_values, $name);
    }
}
