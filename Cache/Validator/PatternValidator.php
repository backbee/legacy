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

namespace BackBee\Cache\Validator;

/**
 * PatternValidator will invalid string that match with pattern to exclude.
 *
 * @category    BackBee
 *
 * 
 * @author      e.chau <eric.chau@lp-digital.fr>
 */
class PatternValidator implements ValidatorInterface
{
    /**
     * List of url pattern to exclude from cache candidates.
     *
     * @var array
     */
    private $patterns_to_exclude;

    /**
     * list of group name this validator belong to.
     *
     * @var array
     */
    private $groups;

    /**
     * constructor.
     *
     * @param array $supported_methods list of supported methods
     * @param array $groups            list of groups this validator belongs to
     */
    public function __construct(array $patterns_to_exclude, $groups = array('page'))
    {
        $this->patterns_to_exclude = $patterns_to_exclude;
        $this->groups = (array) $groups;
    }

    /**
     * @see BackBee\Cache\Validator\ValidatorInterface::isValid
     */
    public function isValid($string = null)
    {
        $is_valid = true;
        if (true === is_string($string)) {
            foreach ($this->patterns_to_exclude as $pattern) {
                if (1 === preg_match("#$pattern#", $string)) {
                    $is_valid = false;
                    break;
                }
            }
        }

        return $is_valid;
    }

    /**
     * @see BackBee\Cache\Validator\ValidatorInterface::getGroups
     */
    public function getGroups()
    {
        return $this->groups;
    }
}
