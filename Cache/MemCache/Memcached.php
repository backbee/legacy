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

namespace BackBee\Cache\MemCache;

use Psr\Log\LoggerInterface;
use BackBee\Cache\Exception\CacheException;

/**
 * MemCached cache adapter.
 *
 * It supports tag and expire features
 *
 * @category    BackBee
 *
 * 
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class Memcached extends AbstractMemcache
{
    /**
     * Class constructor.
     *
     * @param array                    $options Initial options for the cache adapter:
     *                                          - persistent_id string Optional persistent key
     *                                          - servers array The memcached servers to add
     *                                          - options array The memcached options to set
     * @param string                   $context An optional cache context use as prefix key
     * @param \Psr\Log\LoggerInterface $logger  An optional logger
     *
     * @throws \BackBee\Cache\Exception\CacheException Occurs if Memcached extension is not available.
     */
    public function __construct(array $options = array(), $context = null, LoggerInterface $logger = null)
    {
        if (false === extension_loaded('memcached')) {
            throw new CacheException('Memcached extension is not loaded');
        }

        if (true === array_key_exists('persistent_id', $options)) {
            $this->_cache = new \Memcached($options['persistent_id']);
        } else {
            $this->_cache = new \Memcached();
        }

        parent::__construct($options, $context, $logger);

        if (null !== $this->getContext()) {
            $this->setOption(\Memcached::OPT_PREFIX_KEY, md5($this->getContext()));
        }

        if (false === is_array($this->_instance_options['options'])) {
            throw new CacheException('Memcached adapter: memcached options is not an array.');
        }

        $this->_instance_options['options'] = array_merge($this->_cache_options, $this->_instance_options['options']);
        foreach ($this->_instance_options['options'] as $option => $value) {
            $this->setOption($option, $value);
        }

        if (null !== $this->_instance_options['compression']) {
            $this->setOption(\Memcached::OPT_COMPRESSION, $this->_instance_options['compression']);
        }

        if (false === is_array($this->_instance_options['servers'])) {
            throw new CacheException('Memcached adapter: memcached servers is not an array.');
        }

        $this->addServers($this->_instance_options['servers']);
    }

    /**
     * Closes all the memcached server connections if not persistent.
     *
     * @codeCoverageIgnore
     */
    public function __destruct()
    {
        if (null !== $this->_instance_options['persistent_id']) {
            $this->_cache->quit();
        }
    }

    /**
     * Sets memcached option.
     *
     * @param int   $option
     * @param mixed $value
     *
     * @return boolean
     *
     * @link http://php.net/manual/en/memcached.setoption.php
     */
    public function setOption($option, $value)
    {
        if (false === is_int($option)) {
            $this->log('warning', sprintf('Unknown memcached option: `%s`.', $option));

            return false;
        }

        if (false === $this->_cache->setOption($option, $value)) {
            return $this->_onError('setOption');
        }

        return true;
    }

    /**
     * Gets memcached option.
     *
     * @param mixed $option
     * @param mixed $value
     *
     * @return boolean
     *
     * @link http://php.net/manual/en/memcached.getoption.php
     */
    public function getOption($option)
    {
        if (false === is_int($option)) {
            $this->log('warning', sprintf('Unknown memcached option: `%s`.', $option));

            return false;
        }

        if (false === $this->_cache->getOption($option)) {
            return $this->_onError('getOption');
        }

        return true;
    }

    /**
     * Returns the list of available memcached servers.
     *
     * @return array
     *
     * @link http://php.net/manual/en/memcached.getserverlist.php
     * @codeCoverageIgnore
     */
    public function getServerList()
    {
        $serverList = $this->_cache->getServerList();
        //FIX FOR HHVM BEHAVIOUR WHICH RETURN NULL WHEN NO SERVERS
        return is_array($serverList) ? $serverList : array();
    }

    /**
     * Returns the result code of the last operation.
     *
     * @return int Result code of the last Memcached operation.
     *
     * @link http://php.net/manual/en/memcached.getresultcode.php
     * @codeCoverageIgnore
     */
    public function getResultCode()
    {
        return $this->_cache->getResultCode();
    }

    /**
     * Return the message describing the result of the last operation.
     *
     * @return string Message describing the result of the last Memcached operation.
     *
     * @link http://php.net/manual/en/memcached.getresultmessage.php
     * @codeCoverageIgnore
     */
    public function getResultMessage()
    {
        return $this->_cache->getResultMessage();
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
    public function save($id, $data, $lifetime = null, $tag = null, $bypass_control = false)
    {
        $lifetime = $this->getLifeTime($lifetime);

        if (false === $this->_cache->set($id, (is_array($data) ? $data : ''.$data), $lifetime)) {
            return $this->_onError('save');
        }

        if (null !== $tag) {
            $this->saveTag($id, $tag);
        }

        return true;
    }
}
