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

use Psr\Log\LoggerInterface;
use BackBee\Cache\Exception\CacheException;

/**
 * Abstract class for cache adapters.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
abstract class AbstractCache implements CacheInterface
{
    /**
     * Cache adapter options.
     *
     * @var array
     */
    protected $_instance_options = array();

    /**
     * Default cache apdater options.
     *
     * @var array
     */
    private $_default_instance_options = array(
        'min_lifetime'       => null,
        'max_lifetime'       => null,
    );

    /**
     * A logger.
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger = null;

    /**
     * A cache context.
     *
     * @var string
     */
    private $_context = null;

    /**
     * Class constructor.
     *
     * @param array                    $options An array of options allowing to construct the cache adapter
     * @param string                   $context An optional cache context
     * @param \Psr\Log\LoggerInterface $logger  An optional logger
     *
     * @throws \BackBee\Cache\Exception\CacheException Occurs if the cache adapter cannot be construct
     * @codeCoverageIgnore
     */
    public function __construct(array $options = array(), $context = null, LoggerInterface $logger = null)
    {
        $this->_instance_options = array_merge($this->_default_instance_options, $this->_instance_options);

        $this->setContext($context);
        $this->setLogger($logger);
        $this->setInstanceOptions($options);
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
    abstract public function load($id, $bypassCheck = false, \DateTime $expire = null);

    /**
     * Tests if a cache is available or not (for the given id).
     *
     * @param string $id Cache id
     *
     * @return int|FALSE the last modified timestamp of the available cache record (0 infinite expiration date)
     */
    abstract public function test($id);

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
    abstract public function save($id, $data, $lifetime = null, $tag = null);

    /**
     * Removes a cache record.
     *
     * @param string $id Cache id
     *
     * @return boolean TRUE if cache is removed FALSE otherwise
     */
    abstract public function remove($id);

    /**
     * Clears all cache records.
     *
     * @return boolean TRUE if cache is cleared FALSE otherwise
     */
    abstract public function clear();

    /**
     * Sets the cache logger.
     *
     * @param \Psr\Log\LoggerInterface $logger
     *
     * @return \BackBee\Cache\CacheAdapterInterface
     * @codeCoverageIgnore
     */
    public function setLogger(LoggerInterface $logger = null)
    {
        $this->_logger = $logger;

        return $this;
    }

    /**
     * Gets the cache logger.
     *
     * @return \Psr\Log\LoggerInterface $logger
     * @codeCoverageIgnore
     */
    public function getLogger()
    {
        return $this->_logger;
    }

    /**
     * Returns the cache context.
     *
     * @return string|NULL
     * @codeCoverageIgnore
     */
    public function getContext()
    {
        return $this->_context;
    }

    /**
     * Sets the cache context.
     *
     * @param string $context
     *
     * @return \BackBee\Cache\CacheAdapterInterface
     * @codeCoverageIgnore
     */
    public function setContext($context = null)
    {
        $this->_context = $context;

        return $this;
    }

    /**
     * Sets the cache adapter instance options.
     *
     * @param array $options
     *
     * @return \BackBee\Cache\CacheAdapterInterface
     * @throws \BackBee\Cache\Exception\CacheException Occurs if a provided option is unknown for this adapter.
     */
    private function setInstanceOptions(array $options = array())
    {
        foreach ($options as $key => $value) {
            if (true === array_key_exists($key, $this->_instance_options)) {
                $this->_instance_options[$key] = $value;
            } else {
                throw new CacheException(sprintf('Unknown option %s for cache adapter %s.', $key, get_class($this)));
            }
        }

        return $this;
    }

    /**
     * Logs a message on provided level if a logger is defined.
     *
     * @param string $level   The log level
     * @param string $message The message to log
     * @param array  $context The logging context
     * @codeCoverageIgnore
     */
    public function log($level, $message, array $context = array('cache'))
    {
        if (null !== $this->_logger) {
            $this->_logger->log($level, $message, $context);
        }
    }

    /**
     * Returns the expiration timestamp.
     *
     * @param int $lifetime
     *
     * @return int
     * @codeCoverageIgnore
     */
    public function getExpireTime($lifetime = null, $bypass_control = false)
    {
        $expire = 0;

        if (null !== $lifetime && 0 !== $lifetime) {
            $now = new \DateTime();

            if (0 < $lifetime) {
                $now->add(new \DateInterval('PT'.$lifetime.'S'));
            } else {
                $now->sub(new \DateInterval('PT'.(-1 * $lifetime).'S'));
            }

            $expire = $now->getTimestamp();
        }

        if (true === $bypass_control) {
            return $expire;
        }

        return $this->getControledExpireTime($expire);
    }

    /**
     * Control the lifetime against min and max lifetime if provided.
     *
     * @param int $lifetime
     *
     * @return int
     */
    public function getControledLifetime($lifetime)
    {
        if (
            null !== $this->_instance_options['min_lifetime']
            && $this->_instance_options['min_lifetime'] > $lifetime
        ) {
            $lifetime = $this->_instance_options['min_lifetime'];
        } elseif (
            null !== $this->_instance_options['max_lifetime']
            && $this->_instance_options['max_lifetime'] < $lifetime
        ) {
            $lifetime = $this->_instance_options['max_lifetime'];
        }

        return $lifetime;
    }

    /**
     * Control the expiration time against min and max lifetime if provided.
     *
     * @param int $expire
     *
     * @return int
     */
    private function getControledExpireTime($expire)
    {
        $lifetime = $this->getControledLifetime($expire - time());

        if (0 < $lifetime) {
            return time() + $lifetime;
        }

        return $expire;
    }
}
