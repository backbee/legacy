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

use BackBee\Cache\AbstractExtendedCache;
use BackBee\Cache\Exception\CacheException;

/**
 * Memcache cache adapter.
 *
 * It supports tag and expire features
 *
 * @category    BackBee
 *
 *
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
abstract class AbstractMemcache extends AbstractExtendedCache
{
    /**
     * Default Memcache host.
     *
     * @var string
     */
    const DEFAULT_HOST = '127.0.0.1';

    /**
     * Default Memcache server port.
     *
     * @var int
     */
    const DEFAULT_PORT = 11211;

    /**
     * Default server weight.
     *
     * @var int
     */
    const DEFAULT_WEIGHT = 1;

    /**
     * Tags hash prefix.
     *
     * @var string
     */
    const TAGS_PREFIX = '__tag__';

    /**
     * Expire hash prefix.
     *
     * @var string
     */
    const EXPIRE_PREFIX = '__expire__';

    /**
     * Memcache adapter options.
     *
     * @var array
     */
    protected $_instance_options = array(
        'type' => 'memcache',
        'persistent_id' => null,
        'compression' => false,
        'servers' => array(),
        'options' => array(),
    );

    /**
     * The Memcache object.
     *
     * @var \Memcache
     */
    protected $_cache;
    protected $compression = 0;
    protected $serverList = array();
    protected $result = null;

    /**
     * The default Memcache server connection information.
     *
     * @var array
     */
    protected $_default_server = array(
        'host' => self::DEFAULT_HOST,
        'port' => self::DEFAULT_PORT,
        'weight' => self::DEFAULT_WEIGHT,
    );

    /**
     * Override some default Memcache instance options.
     *
     * @var array
     */
    protected $_cache_options = array(
        2 => 1, // OPT_HASH = HASH_MD5
        9 => 1, // OPT_DISTRIBUTION = DISTRIBUTION_CONSISTENT
        16 => true, // OPT_LIBKETAMA_COMPATIBLE = true
    );

    public function __construct(array $options = array(), $context = null, LoggerInterface $logger = null)
    {
        parent::__construct($options, $context, $logger);
    }
    /**
     * Adds a set of servers to the memcache instance pool.
     *
     * @param array $servers
     *
     * @return boolean
     *
     * @throws \BackBee\Cache\Exception\CacheException Occurs if one of the server configurations is not an array
     *
     * @link http://php.net/manual/en/Memcache.addservers.php
     */
    public function addServers(array $servers = array())
    {
        $result = true;

        foreach ($servers as $server) {
            if (false === is_array($server)) {
                throw new CacheException('Memcache adapter: server configuration is not an array.');
            }

            $server = array_merge($this->_default_server, $server);
            $result = $result && $this->addServer($server['host'], $server['port'], true, $server['weight']);

            $this->setServerList($server);
        }

        return $result;
    }

    public function setServerList($server)
    {
        $this->serverList[] = $server;
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

        if (false === $this->_cache->addServer($host, $port, $weight)) {
            return $this->_onError('setOption');
        }

        return true;
    }

    /**
     * Returns the available cache for the given id if found returns FALSE else.
     *
     * @param string    $id          Cache id
     * @param boolean   $bypassCheck Allow to find cache without test it before
     * @param \DateTime $expire      Optionnal, the expiration time (now by default)
     *
     * @return string|FALSE
     */
    public function load($id, $bypassCheck = true, \DateTime $expire = null)
    {
        if (null === $expire) {
            $expire = new \DateTime();
        }

        $last_timestamp = $this->test($id);

        if (true === $bypassCheck) {
            $last_timestamp = 0;
        }

        if (true === $bypassCheck || 0 === $last_timestamp || $expire->getTimestamp() <= $last_timestamp) {
            if (false === $this->result) {
                return $this->_onError('load');
            }

            return $this->result;
        }

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
        if (false === $this->result = $this->_cache->get($id)) {
            return false;
        }

        return (int) $this->result;
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

        if (false === $this->_cache->set($id, (is_array($data) ? $data : ''.$data), $this->compression, $lifetime)) {
            return $this->_onError('save');
        }

        if (null !== $tag) {
            $this->saveTag($id, $tag);
        }

        return true;
    }

    public function saveTag($id, $tag)
    {
        if (false === $tagged = $this->load(self::TAGS_PREFIX.$tag)) {
            $tagged = array();
        }

        if (false === in_array($id, $tagged)) {
            $tagged[] = $id;
            $this->save(self::TAGS_PREFIX.$tag, $tagged);
        }
    }

    protected function getLifeTime($lifetime)
    {
        if (null === $lifetime) {
            $lifetime = 0;
        }

        $min_lifetime = $this->_instance_options['min_lifetime'];
        $max_lifetime = $this->_instance_options['max_lifetime'];

        if ($lifetime == 0 && false === empty($max_lifetime)) {
            $lifetime = $max_lifetime;
        } elseif (false === empty($min_lifetime) && $lifetime < $min_lifetime) {
            $lifetime = $min_lifetime;
        } elseif (false === empty($max_lifetime) && $lifetime > $max_lifetime) {
            $lifetime = $max_lifetime;
        }

        return $lifetime;
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
        if (false === $this->_cache->delete($id)) {
            return $this->_onError('remove');
        }

        return true;
    }

    /**
     * Clears all cache records.
     *
     * @return boolean TRUE if cache is cleared FALSE otherwise
     */
    public function clear()
    {
        if (false === $this->_cache->flush()) {
            return $this->_onError('clear');
        }

        return true;
    }

    /**
     * Removes all cache records associated to one of the tags.
     *
     * @param string|array $tag
     *
     * @return boolean TRUE if cache is removed FALSE otherwise
     */
    public function removeByTag($tag)
    {
        $tags = (array) $tag;
        if (0 === count($tags)) {
            return false;
        }

        foreach ($tags as $tag) {
            if (false !== $tagged = $this->load(self::TAGS_PREFIX.$tag)) {
                foreach ($tagged as $id) {
                    $this->remove($id);
                }
                $this->remove(self::TAGS_PREFIX.$tag);
            }
        }

        return true;
    }

    /**
     * Updates the expire date time for all cache records
     * associated to one of the provided tags.
     *
     * @param string|array $tag
     * @param int          $lifetime Optional, the specific lifetime for this record
     *                               (by default null, infinite lifetime)
     *
     * @return boolean TRUE if cache is removed FALSE otherwise
     */
    public function updateExpireByTag($tag, $lifetime = null)
    {
        return true;
    }

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
    public function getMinExpireByTag($tag, $lifetime = 0)
    {
        return 0;
    }

    /**
     * Returns TRUE if the server is already added to Memcached, FALSE otherwise.
     *
     * @param string $host
     * @param int    $port
     *
     * @return boolean
     *
     * @throws \BackBee\Cache\Exception\CacheException Occurs if none memcached object is not initialized
     * @codeCoverageIgnore
     */
    protected function _hasServer($host, $port)
    {
        $servers = $this->getServerList();
        foreach ($servers as $server) {
            if ($server['host'] === $host && $server['port'] === $port) {
                return true;
            }
        }

        return false;
    }

    /**
     * Logs error result code and message.
     *
     * @param string $method
     *
     * @return boolean
     * @codeCoverageIgnore
     */
    protected function _onError($method)
    {
        $this->log('notice', sprintf('Error occured on Memcached::%s(): [%s] %s.', $method, $this->getResultCode(), $this->getResultMessage()));

        return false;
    }
}
