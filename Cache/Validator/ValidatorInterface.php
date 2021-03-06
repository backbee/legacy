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
 * Every cache validator must implements this interface and its methods; every validator must belong to
 * one group atleast which ease user call to cache validator by providing group name to check a set of
 * requirements.
 *
 * @category    BackBee
 *
 *
 * @author      e.chau <eric.chau@lp-digital.fr>
 */
interface ValidatorInterface
{
    /**
     * Defines if object is candidate for cache processing or not.
     *
     * @param mixed $object represents the content we want to apply cache process, can be null
     *
     * @return boolean return true if this object is candidate for cache process, else false
     */
    public function isValid($object = null);

    /**
     * Returns every group name this validator is associated with.
     *
     * @return array
     */
    public function getGroups();
}
