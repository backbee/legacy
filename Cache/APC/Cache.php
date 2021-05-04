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

namespace BackBee\Cache\APC;

use Psr\Log\LoggerInterface;

use BackBee\Cache\AbstractExtendedCache;
use BackBee\Cache\Exception\CacheException;

/**
 * APC cache adapter
 * It supports tag and expire features.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      Cédric Bouillot <cedric.bouillot@lp-digital.fr>
 */
class Cache extends AbstractExtendedCache
{
    /**
     * Hashmap id prefix.
     */
    const HASHMAP_PREFIX = 'HaShMaP';

    /**
     * Hashmap ttl (default infinite).
     */
    const HASHMAP_TTL = 0;

    /**
     * The hashmap id for current site.
     *
     * @var string
     */
    private $_hashmapId = null;

    /**
     * The hashmap for current site.
     *
     * @var mixed
     */
    private $_hashmap = array();

    /**
     * hashmapId getter.
     *
     * @return string
     */
    public function getHashmapId()
    {
        return $this->_hashmapId;
    }

    /**
     * hashmap getter.
     *
     * @return mixed
     */
    public function getHashmap()
    {
        return $this->_hashmap;
    }

    /**
     * Class constructor.
     *
     * @param array                    $options Initial options for the cache adapter: none to be defined
     * @param string                   $context An optional cache context
     * @param \Psr\Log\LoggerInterface $logger  An optional logger
     *
     * @throws \BackBee\Cache\Exception\CacheException Occurs if the entity manager for this cache adaptor cannot be created
     */
    public function __construct(array $options = array(), $context = null, LoggerInterface $logger = null)
    {
        if (false === extension_loaded('apc')) {
            throw new CacheException('APC extension not loaded');
        }

        parent::__construct($options, $context, $logger);

        $this->_hashmapId = self::HASHMAP_PREFIX.'_'.md5($this->getContext());
        $this->_hashmap = $this->loadHashmap();
    }

    /**
     * Returns the available cache for the given id if found returns false else.
     *
     * @param string    $id          Cache id
     * @param boolean   $bypassCheck Allow to find cache without test it before
     * @param \DateTime $expire      Optionnal, the expiration time (now by default)
     *
     * @return string|FALSE
     */
    public function load($id, $bypassCheck = false, \DateTime $expire = null)
    {
        try {
            return \apc_fetch($id);
        } catch (\Exception $e) {
            $this->log('warning', \sprintf('Unable to load cache for id %s : %s', $id, $e->getMessage()));

            return false;
        }

        return false;
    }

    /**
     * Tests if a cache is available or not for provided id.
     *
     * @param string $id Cache id
     *
     * @return int|FALSE the last modified timestamp of the available cache record
     */
    public function test($id)
    {
        try {
            if (!\apc_fetch($id)) {
                return false;
            }
            foreach ($this->_hashmap as $tag) {
                foreach ($tag as $key => $item) {
                    if ($id == $key) {
                        return $item['time'] + $item['ttl'];
                    }
                }
            }
        } catch (\Exception $e) {
            $this->log('warning', \sprintf('Unable to test cache for id %s : %s', $id, $e->getMessage()));

            return false;
        }

        return false;
    }

    /**
     * Store provided data into cache.
     *
     * @param string $id       Cache id
     * @param mixed  $data     Datas to cache
     * @param int    $lifetime Optional, the specific lifetime for this record (by default null, infinite lifetime)
     * @param string $tag      Optional, an associated tag to the stored data
     *
     * @return boolean TRUE if cache is stored FALSE otherwise
     */
    public function save($id, $data, $lifetime = null, $tag = null)
    {
        try {
            if (\apc_store($id, $data, $lifetime)) {
                $this->_hashmap[$tag][$id] = array("time" => time(), "ttl" => $lifetime);

                return $this->saveHashmap();
            } else {
                return false;
            }
        } catch (\Exception $e) {
            $this->log('warning', \sprintf('Unable to save cache for id %s : %s', $id, $e->getMessage()));

            return false;
        }

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
        try {
            if (\apc_delete($id)) {
                $this->removeFromHashmapById($id);

                return true;
            }
        } catch (\Exception $e) {
            $this->log('warning', \sprintf('Unable to delete cache for id %s : %s', $id, $e->getMessage()));

            return false;
        }
    }

    /**
     * Removes all cache records associated to provided tag(s).
     *
     * @param mixed $tag
     *
     * @return boolean TRUE if cache is removed FALSE otherwise
     */
    public function removeByTag($tag)
    {
        $tags = (is_array($tag) ? $tag : array($tag));
        try {
            foreach ($tags as $tag) {
                $this->removeTag($tag);
            }
        } catch (\Exception $e) {
            $this->log('warning', \sprintf('Unable to delete cache for tag(s) %s : %s', \implode(',', $tags), $e->getMessage()));

            return false;
        }

        return true;
    }

    /**
     * @todo
     *
     * @param type $tag
     * @param type $lifetime
     *
     * @return type
     */
    public function getMinExpireByTag($tag, $lifetime = 0)
    {
        return $lifetime;
    }

    /**
     * Updates TTL for all cache records associated to provided tag(s).
     *
     * @param mixed $tag
     * @param int   $lifetime
     *
     * @return boolean TRUE if cache is removed FALSE otherwise
     */
    public function updateExpireByTag($tag, $lifetime = null)
    {
        $tags = (is_array($tag) ? $tag : array($tag));
        try {
            foreach ($tags as $tag) {
                $this->updateExpireTag($tag, $lifetime);
            }
        } catch (\Exception $e) {
            $this->log('warning', \sprintf('Unable to delete cache for tag(s) %s : %s', \implode(',', $tags), $e->getMessage()));

            return false;
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
        try {
            if (\apc_clear_cache('user')) {
                $this->_hashmap = array();

                return $this->saveHashmap();
            }
        } catch (\Exception $e) {
            $this->log('warning', sprintf('Unable to clear cache : %s', $e->getMessage()));
        }

        return false;
    }

    /**
     * Removes all cache records associated to provided tag.
     *
     * @param string $tag
     *
     * @return boolean TRUE if cache is removed FALSE otherwise
     */
    private function removeTag($tag)
    {
        try {
            if (isset($this->_hashmap[$tag])) {
                foreach (array_keys($this->_hashmap[$tag]) as $key) {
                    if (\apc_delete($key)) {
                        unset($this->_hashmap[$tag][$key]);
                    }
                }

                return $this->saveHashmap();
            }
        } catch (\Exception $e) {
            $this->log('warning', \sprintf('Unable to delete cache for tag %s : %s', $tag, $e->getMessage()));

            return false;
        }

        return true;
    }

    /**
     * Updates the TTL for all cache records associated to provided tag.
     *
     * @param string $tag
     * @param int    $lifetime
     *
     * @return boolean TRUE if cache is removed FALSE otherwise
     */
    private function updateExpireTag($tag, $lifetime = null)
    {
        try {
            if (isset($this->_hashmap[$tag])) {
                foreach (array_keys($this->_hashmap[$tag]) as $key) {
                    $value = $this->load($key);
                    if ($value) {
                        $this->save($key, $value, $lifetime, $tag);
                    }
                }

                return true;
            }
        } catch (\Exception $e) {
            $this->log('warning', \sprintf('Unable to update cache ttl for tag %s : %s', $tag, $e->getMessage()));

            return false;
        }

        return false;
    }

    /**
     * Remove provided id from hashmap.
     *
     * @return boolean
     */
    private function removeFromHashmapById($id)
    {
        try {
            foreach ($this->_hashmap as $tag => $vars) {
                foreach (array_keys($vars) as $key) {
                    if ($key == $id) {
                        unset($this->_hashmap[$tag][$key]);

                        return $this->saveHashmap();
                    }
                }
            }
        } catch (\Exception $e) {
            $this->log('warning', \sprintf('Unable to remove id from hashmap %s : %s', $id, $e->getMessage()));

            return false;
        }

        return false;
    }

    /**
     * Store hasmap for current hashmap id.
     *
     * @return boolean
     */
    private function saveHashmap()
    {
        try {
            return \apc_store($this->_hashmapId, $this->_hashmap, self::HASHMAP_TTL);
        } catch (\Exception $e) {
            $this->log('warning', \sprintf('Unable to store hashmap %s : %s', $this->_hashmapId, $e->getMessage()));

            return false;
        }
    }

    /**
     * Retrieve hasmap according current hashmap id.
     *
     * @return boolean
     */
    public function loadHashmap()
    {
        try {
            if ($this->_hashmap = \apc_fetch($this->_hashmapId)) {
                return $this->_hashmap;
            } else {
                return array();
            }
        } catch (\Exception $e) {
            $this->log('warning', \sprintf('Unable to load hashmap %s : %s', $this->_hashmapId, $e->getMessage()));

            return false;
        }
    }
}
