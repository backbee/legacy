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

namespace BackBee\Cache\File;

use Psr\Log\LoggerInterface;

use BackBee\Cache\AbstractCache;
use BackBee\Cache\Exception\CacheException;
use BackBee\Util\StringUtils;

/**
 * Filesystem cache adapter.
 *
 * A simple cache system storing data in files, it does not provide tag or expire features
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class Cache extends AbstractCache
{
    /**
     * The cache directory.
     *
     * @var string
     */
    private $cachedir;

    /**
     * Memcached adapter options.
     *
     * @var array
     */
    protected $_instance_options = array(
        'cachedir'          => null,
        'cacheautogenerate' => true,
    );

    /**
     * Class constructor.
     *
     * @param array                    $options Initial options for the cache adapter:
     *                                          - cachedir string The cache directory
     * @param string                   $context An optional cache context
     * @param \Psr\Log\LoggerInterface $logger  An optional logger
     *
     * @throws \BackBee\Cache\Exception\CacheException Occurs if the cache directory doesn't exist, can not
     *                                                 be created or is not writable.
     */
    public function __construct(array $options = array(), $context = null, LoggerInterface $logger = null)
    {
        parent::__construct($options, $context, $logger);

        $this->cachedir = $this->_instance_options['cachedir'];

        if (null !== $this->getContext()) {
            $this->cachedir .= DIRECTORY_SEPARATOR.StringUtils::toPath($this->getContext());
        }

        if (true === $this->_instance_options['cacheautogenerate'] && false === is_dir($this->cachedir)
                && false === @mkdir($this->cachedir, 0755, true)) {
            throw new CacheException(sprintf('Unable to create the cache directory `%s`.', $this->cachedir));
        }

        if (true === $this->_instance_options['cacheautogenerate'] && false === is_writable($this->cachedir)) {
            throw new CacheException(sprintf('Unable to write in the cache directory `%s`.', $this->cachedir));
        }

        $this->log('info', sprintf('File cache system initialized with directory set to `%s`.', $this->cachedir));
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
        if (null === $expire) {
            $expire = new \DateTime();
        }

        $last_timestamp = $this->test($id);
        if (
            true === $bypassCheck
            || 0 === $last_timestamp
            || $expire->getTimestamp() <= $last_timestamp
        ) {
            if (false !== $data = @file_get_contents($this->getCacheFile($id))) {
                $this->log('debug', sprintf('Reading file cache data for id `%s`.', $id));

                return $data;
            }

            $this->log('warning', sprintf('Enable to read data in file cache for id `%s`.', $id));
        } else {
            $this->log('debug', sprintf('None available file cache found for id `%s` newer than %s.', $id, $expire->format('d/m/Y H:i')));
        }

        return false;
    }

    /**
     * Tests if a cache is available or not (for the given id).
     *
     * @param string $id Cache id
     *
     * @return int|FALSE the last modified timestamp of the available cache record
     */
    public function test($id)
    {
        $result = false;
        $cache_file = $this->getCacheFile($id);
        if (is_readable($cache_file)) {
            $result = stat($cache_file);
            $result = $result['mtime'];
        }

        return $result;
    }

    /**
     * Save some string datas into a cache record.
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
        if (null !== $lifetime || null !== $tag) {
            $this->log('warning', sprintf('Lifetime and tag features are not available for cache adapter `%s`.', get_class($this)));
        }

        if (true === $result = @file_put_contents($this->getCacheFile($id), $data)) {
            $this->log('debug', sprintf('Storing data in cache for id `%s`.', $id));
        } else {
            $this->log('warning', sprintf('Unable to save data to file cache for id `%s` in directory `%s`.', $id, $this->cachedir));
        }

        return (false !== $result);
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
        if (true === $result = @unlink($this->getCacheFile($id))) {
            $this->log('debug', sprintf('Cache data removed for id `%s`.', $id));
        }

        return $result;
    }

    /**
     * Clears all cache records.
     *
     * @return boolean TRUE if cache is cleared FALSE otherwise
     */
    public function clear()
    {
        $result = false;
        if (false !== $files = @scandir($this->cachedir)) {
            foreach ($files as $file) {
                $file = $this->cachedir.DIRECTORY_SEPARATOR.$file;
                if (false === is_dir($file)) {
                    if (false === @unlink($file)) {
                        $this->log('warning', sprintf('Enable to remove cache file `%s`.', $file));
                    } else {
                        $result = true;
                    }
                }
            }
        }

        if (true === $result) {
            $this->log('debug', sprintf('File system cache cleared in `%s`.', $this->cachedir));
        }

        return $result;
    }

    /**
     * Returns the cache file if a cache is available for the given id.
     *
     * @param string $id Cache id
     *
     * @return string The file path of the available cache record
     * @codeCoverageIgnore
     */
    private function getCacheFile($id)
    {
        $cachefile = $this->cachedir.DIRECTORY_SEPARATOR.$id;

        return $cachefile;
    }
}
