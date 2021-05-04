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

namespace BackBee\Cache;

/**
 * Interface for BackBee Cache Tag & Expire features
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      Mickaël Andrieu <mickael.andrieu@lp-digital.fr>
 */
interface CacheExtendedInterface extends CacheInterface
{
    /**
     * Removes all cache records associated to one of the tags.
     *
     * @param string|array $tag
     *
     * @return boolean true if cache is removed FALSE otherwise
     */
    public function removeByTag($tag);

    /**
     * Updates the expire date time for all cache records
     * associated to one of the provided tags.
     *
     * @param string|array $tag
     * @param int          $lifetime Optional, the specific lifetime for this record
     *                               (by default null, infinite lifetime)
     *
     * @return boolean true if cache is removed FALSE otherwise
     */
    public function updateExpireByTag($tag, $lifetime);

    /**
     * Returns the minimum expire date time for all cache records
     * associated to one of the provided tags.
     *
     * @param string|array $tag
     * @param int          $lifetime Optional, the specific lifetime for this record
     *                               (by default 0, infinite lifetime)
     *
     * @return int
     */
    public function getMinExpireByTag($tag, $lifetime);

    /**
     * Save the tag with the selected cache id
     *
     * @param string $id       Cache id
     * @param string $tag      Optional, an associated tag to the data stored
     *
     * @return void
     */
    public function saveTag($id, $tag);
}
