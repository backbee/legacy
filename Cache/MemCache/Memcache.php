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

namespace BackBee\Cache\MemCache;

use Psr\Log\LoggerInterface;
use BackBee\Cache\Exception\CacheException;

/**
 * Memcache cache adapter.
 *
 * It supports tag and expire features
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class Memcache extends AbstractMemcache
{
    /**
     * Class constructor.
     *
     * @param array                    $options Initial options for the cache adapter:
     *                                          - persistent_id string Optional persistent key
     *                                          - servers array The Memcache servers to add
     *                                          - options array The Memcache options to set
     * @param string                   $context An optional cache context use as prefix key
     * @param \Psr\Log\LoggerInterface $logger  An optional logger
     *
     * @throws \BackBee\Cache\Exception\CacheException Occurs if Memcache extension is not available.
     */
    public function __construct(array $options = array(), $context = null, LoggerInterface $logger = null)
    {
        if (false === extension_loaded('Memcache')) {
            throw new CacheException('Memcache extension is not loaded');
        }

        $this->_cache = new \Memcache();

        parent::__construct($options, $context, $logger);

        if (true === $this->_instance_options['compression']) {
            $this->compression = MEMCACHE_COMPRESSED;
        }

        if (false === is_array($this->_instance_options['servers'])) {
            throw new CacheException('Memcache adapter: Memcache servers is not an array.');
        }

        $this->addServers($this->_instance_options['servers']);
    }

    /**
     * Adds a server to the server pool.
     *
     * @param string $host   The hostname of the memcache server
     * @param int    $port   The port on which memcache is running, 11211 by default
     * @param int    $weight The weight of the server
     *
     * @return boolean TRUE on success or FALSE on failure.
     *
     * @link http://php.net/manual/en/memcached.addserver.php
     */
    public function addServer($host, $port, $weight = 0)
    {
        if (true === $this->_hasServer($host, $port)) {
            return true;
        }

        if (false === $this->_cache->addServer($host, $port, true, $weight)) {
            return $this->_onError('setOption');
        }

        return true;
    }

    /**
     * Closes all the Memcache server connections if not persistent.
     *
     * @codeCoverageIgnore
     */
    public function __destruct()
    {
        $this->_cache->close();
    }

    public function getServerList()
    {
        return $this->serverList;
    }

    public function getResultCode()
    {
        return;
    }

    public function getResultMessage()
    {
        return;
    }
}
