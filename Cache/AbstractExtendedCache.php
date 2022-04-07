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

namespace BackBee\Cache;

/**
 * Abstract class for cache adapters with extended features
 * as tag and expire date time.
 *
 * @category    BackBee
 *
 * 
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
abstract class AbstractExtendedCache extends AbstractCache implements CacheExtendedInterface
{
    /**
     * @{inheritdoc}
     */
    abstract public function removeByTag($tag);

    /**
     * @{inheritdoc}
     */
    abstract public function updateExpireByTag($tag, $lifetime = null);

    /**
     * @{inheritdoc}
     */
    abstract public function getMinExpireByTag($tag, $lifetime = 0);

    /**
     * @{inheritdoc}
     */
    public function saveTag($id, $tag)
    {
        return null;
    }
}
