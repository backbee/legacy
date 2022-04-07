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

namespace BackBee\Routing;

use Symfony\Component\Routing\Route as sfRoute;

/**
 * @category    BackBee
 *
 *
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class Route extends sfRoute
{
    /**
     * Part of requirements related to headers.
     *
     * @var array
     */
    private $_headerRequirements;

    /**
     * Constructor.
     *
     * Available requirements:
     *  - HTTP_<<headername>> : HTTP header value required
     *
     * @param string $pattern      The pattern to match
     * @param array  $defaults     An array of default parameter values
     * @param array  $requirements An array of requirements for parameters (regexes)
     * @param array  $options      An array of options
     */
    public function __construct($pattern, array $defaults = array(), array $requirements = array(), array $options = array())
    {
        parent::__construct($pattern, $defaults, $requirements, $options);

        $this->_addHeaderRequirements();
    }

    /**
     * Extract header requirements.
     *
     * @return Route The current Route instance
     */
    private function _addHeaderRequirements()
    {
        $this->_headerRequirements = array();
        foreach ($this->getRequirements() as $key => $value) {
            if (0 === strpos($key, 'HTTP-')) {
                $this->_headerRequirements[substr($key, 5)] = $value;
            }
        }

        return $this;
    }

    /**
     * Adds requirements.
     *
     * This method implements a fluent interface.
     *
     * @param array $requirements The requirements
     *
     * @return Route The current Route instance
     */
    public function addRequirements(array $requirements)
    {
        parent::addRequirements($requirements);
        $this->_addHeaderRequirements();

        return $this;
    }

    /**
     * Returns the requirements.
     *
     * @return array The requirements
     */
    public function getRequirements($startingWith = null)
    {
        if (null === $startingWith) {
            return parent::getRequirements();
        }

        return ('HTTP-' == $startingWith) ? $this->_headerRequirements : array();
    }
}
