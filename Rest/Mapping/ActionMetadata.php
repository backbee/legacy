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

namespace BackBee\Rest\Mapping;

use Metadata\MethodMetadata;

/**
 * Stores controller action metadata.
 *
 * @Annotation
 *
 * @category    BackBee
 *
 *
 * @author      k.golovin
 */
class ActionMetadata extends MethodMetadata
{
    /**
     * @var array
     */
    public $queryParams = array();

    /**
     * @var array
     */
    public $requestParams = array();

    /**
     * @var integer
     */
    public $default_start;

    /**
     * @var integer
     */
    public $default_count;

    /**
     * @var integer
     */
    public $max_count;

    /**
     * @var integer
     */
    public $min_count;

    /**
     * @var array
     */
    public $param_converter_bag = array();

    /**
     * @var array
     */
    public $security = array();

    /**
     * serialize current object.
     *
     * @return string
     */
    public function serialize()
    {
        return \serialize([
            $this->class,
            $this->name,
            $this->queryParams,
            $this->requestParams,
            $this->default_start,
            $this->default_count,
            $this->max_count,
            $this->min_count,
            $this->param_converter_bag,
            $this->security,
        ]);
    }

    /**
     * unserialize.
     *
     * @param string $str
     */
    public function unserialize($str)
    {
        list(
            $this->class,
            $this->name,
            $this->queryParams,
            $this->requestParams,
            $this->default_start,
            $this->default_count,
            $this->max_count,
            $this->min_count,
            $this->param_converter_bag,
            $this->security
        ) = \unserialize($str);

        $this->reflection = new \ReflectionMethod($this->class, $this->name);
        $this->reflection->setAccessible(true);
    }
}
