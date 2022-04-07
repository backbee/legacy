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

namespace BackBee\Cache\NoCache;

use BackBee\Cache\AbstractCache;

/**
 * Filesystem cache adapter.
 *
 * A simple cache system storing data in files, it does not provide tag or expire features
 *
 * @category    BackBee
 *
 * 
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class Cache extends AbstractCache
{
    /**
     * Returns the available cache for the given id if found returns FALSE else.
     *
     * @param string    $id          Cache id
     * @param boolean   $bypassCheck Allow to find cache without test it before
     * @param \DateTime $expire      Optionnal, the expiration time (now by default)
     *
     * @return string|FALSE
     */
    public function load($id, $bypassCheck = false, \DateTime $expire = null)
    {
        return false;
    }

    /**
     * Tests if a cache is available or not (for the given id).
     *
     * @param string $id Cache id
     *
     * @return int|FALSE the last modified timestamp of the available cache record (0 infinite expiration date)
     */
    public function test($id)
    {
        return false;
    }

    /**
     * Saves some string datas into a cache record.
     *
     * @param string $id       Cache id
     * @param string $data     Datas to cache
     * @param int    $lifetime Optional, the specific lifetime for this record
     *                         (by default null, infinite lifetime)
     * @param string $tag      Optional, an associated tag to the data stored
     *
     * @return boolean TRUE if cache is stored FALSE otherwise
     */
    public function save($id, $data, $lifetime = null, $tag = null)
    {
        return true;
    }

    /**
     * Removes a cache record.
     *
     * @param string $id Cache id
     *
     * @return boolean TRUE if cache is removed FALSE otherwise
     */
    public function remove($id)
    {
        return true;
    }

    /**
     * Clears all cache records.
     *
     * @return boolean TRUE if cache is cleared FALSE otherwise
     */
    public function clear()
    {
        return true;
    }
}
