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

namespace BackBee\Rest\Metadata\Cache;

use BackBee\Cache\AbstractCache;
use Metadata\Cache\CacheInterface;
use Metadata\ClassMetadata;

/**
 * Metadata cache in BackBee cache services.
 *
 * @category    BackBee
 *
 *
 * @author      Charles Rouillon <charles.rouillon@lp-digital.fr>
 */
class BackBeeCache implements CacheInterface
{
    const CACHE_PREFIX = '__rest_metadata__';

    /**
     * BackBee cache service
     *
     * @var AbstractCache
     */
    private $cache;

    /**
     * the application debug value.
     *
     * @var boolean
     */
    private $debug;

    /**
     * FileCache constructor.
     *
     * @param AbstractCache  $cache
     * @param boolean        $debug
     */
    public function __construct(AbstractCache $cache, $debug = false)
    {
        $this->cache = $cache;
        $this->debug = true === $debug;
    }

    /**
     * {@inheritDoc}
     */
    public function loadClassMetadataFromCache(\ReflectionClass $class)
    {
        if ($this->debug) {
            return;
        }

        $cacheId = md5(self::CACHE_PREFIX.$class->name);
        if (false !== $content = $this->cache->load($cacheId)) {
            return unserialize($content);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function putClassMetadataInCache(ClassMetadata $metadata)
    {
        if ($this->debug) {
            return;
        }

        $cacheId = md5(self::CACHE_PREFIX.$metadata->name);
        if (false === $this->cache->save($cacheId, serialize($metadata), true)) {
            throw new \RuntimeException(sprintf('Could not write new metadata cache for %s.', $metadata->name));
        }
    }

    /**
     * {@inheritDoc}
     */
    public function evictClassMetadataFromCache(\ReflectionClass $class)
    {
        $cacheId = md5(self::CACHE_PREFIX.$class->name);
        $this->cache->remove($cacheId);
    }
}