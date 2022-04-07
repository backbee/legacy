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

namespace BackBee\Cache\DAO;

use Doctrine\ORM\EntityManager;
use Psr\Log\LoggerInterface;

use BackBee\Cache\AbstractExtendedCache;
use BackBee\Cache\Exception\CacheException;
use BackBee\Exception\InvalidArgumentException;
use BackBee\Util\Doctrine\EntityManagerCreator;

/**
 * Database cache adapter.
 *
 * It supports tag and expire features
 *
 * @category    BackBee
 *
 *
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class Cache extends AbstractExtendedCache
{
    /**
     * The cache entity class name.
     *
     * @var string
     */
    const ENTITY_CLASSNAME = 'BackBee\Cache\DAO\Entity';

    /**
     * The Doctrine entity manager to use.
     *
     * @var \Doctrine\ORM\EntityManager
     */
    private $_em;

    /**
     * The entity repository.
     *
     * @var \Doctrine\ORM\EntityRepository
     */
    private $_repository;

    /**
     * An entity for a store cache.
     *
     * @var \BackBee\Cache\DAO\Entity
     */
    private $_entity;

    /**
     * The prefix key for cache items.
     *
     * @var type
     */
    private $_prefix_key = '';

    /**
     * Cache adapter options.
     *
     * @var array
     */
    protected $_instance_options = array(
        'em' => null,
        'dbal' => array(),
    );

    /**
     * Class constructor.
     *
     * @param array                    $options Initial options for the cache adapter:
     *                                          - em \Doctrine\ORM\EntityManager  Optional, an already defined EntityManager (simply returns it)
     *                                          - dbal array Optional, an array of Doctrine connection options among:
     *                                          - connection  \Doctrine\DBAL\Connection  Optional, an already initialized database connection
     *                                          - proxy_dir   string                     The proxy directory
     *                                          - proxy_ns    string                     The namespace for Doctrine proxy
     *                                          - charset     string                     Optional, the charset to use
     *                                          - collation   string                     Optional, the collation to use
     *                                          - ...         mixed                      All the required parameter to open a new connection
     * @param string                   $context An optional cache context
     * @param \Psr\Log\LoggerInterface $logger  An optional logger
     *
     * @throws \BackBee\Cache\Exception\CacheException Occurs if the entity manager for this cache adaptor cannot be created
     */
    public function __construct(array $options = array(), $context = null, LoggerInterface $logger = null)
    {
        parent::__construct($options, $context, $logger);

        $this->setEntityManager();
        $this->setEntityRepository();
        $this->setPrefixKey();
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
        if (null === $this->getCacheEntity($id)) {
            return false;
        }

        if (null === $expire) {
            $expire = new \DateTime();
        }

        $last_timestamp = $this->test($id);
        if (true === $bypassCheck || 0 === $last_timestamp || $expire->getTimestamp() <= $last_timestamp) {
            return $this->getCacheEntity($id)->getData();
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
        if (null === $this->getCacheEntity($id)) {
            return false;
        }

        if (null !== $this->getCacheEntity($id)->getExpire()) {
            $t = $this->getCacheEntity($id)->getExpire()->getTimestamp();

            return (time() > $t) ? false : $t;
        }

        return 0;
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
    public function save($id, $data, $lifetime = null, $tag = null, $bypass_control = false)
    {
        try {
            $params = array(
                'uid' => $this->getContextualId($id),
                'tag' => $this->getContextualId($tag),
                'data' => $data,
                'expire' => $this->getExpireTime($lifetime, $bypass_control),
                'created' => new \DateTime(),
            );

            $types = array(
                'string',
                'string',
                'string',
                'datetime',
                'datetime',
            );

            if (null === $this->getCacheEntity($id)) {
                $this->_em->getConnection()
                        ->insert('cache', $params, $types);
            } else {
                $identifier = array('uid' => array_shift($params));
                $type = array_shift($types);
                $types[] = $type;

                $this->_em->getConnection()
                        ->update('cache', $params, $identifier, $types);
            }

            $this->resetCacheEntity();
        } catch (\Exception $e) {
            $this->log('warning', sprintf('Enable to load cache for id %s : %s', $id, $e->getMessage()));

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
            $this->_repository
                    ->createQueryBuilder('c')
                    ->delete()
                    ->where('c._uid = :uid')
                    ->setParameters(array('uid' => $this->getContextualId($id)))
                    ->getQuery()
                    ->execute();
            $this->resetCacheEntity();
        } catch (\Exception $e) {
            $this->log('warning', sprintf('Enable to remove cache for id %s : %s', $id, $e->getMessage()));

            return false;
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

        if (0 == count($tags)) {
            return false;
        }

        try {
            $this->_repository
                    ->createQueryBuilder('c')
                    ->delete()
                    ->where('c._tag IN (:tags)')
                    ->setParameters(array('tags' => $this->getContextualTags($tags)))
                    ->getQuery()
                    ->execute();
            $this->resetCacheEntity();
        } catch (\Exception $e) {
            $this->log('warning', sprintf('Enable to remove cache for tags (%s) : %s', implode(',', $tags), $e->getMessage()));

            return false;
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
    public function updateExpireByTag($tag, $lifetime = null, $bypass_control = false)
    {
        $tags = (array) $tag;

        if (0 == count($tags)) {
            return false;
        }

        $expire = $this->getExpireTime($lifetime, $bypass_control);

        try {
            $this->_repository
                    ->createQueryBuilder('c')
                    ->update()
                    ->set('c._expire', ':expire')
                    ->where('c._tag IN (:tags)')
                    ->setParameters(array(
                        'expire' => $expire,
                        'tags' => $this->getContextualTags($tags), ))
                    ->getQuery()
                    ->execute();
            $this->resetCacheEntity();
        } catch (\Exception $e) {
            $this->log('warning', sprintf('Enable to update cache for tags (%s) : %s', implode(',', $tags), $e->getMessage()));

            return false;
        }

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
        $tags = (array) $tag;

        if (0 == count($tags)) {
            return $lifetime;
        }

        $now = new \DateTime();
        $expire = $this->getExpireTime($lifetime);

        try {
            $min = $this->_repository
                    ->createQueryBuilder('c')
                    ->select('MIN(c._expire)')
                    ->where('c._tag IN (:tags)')
                    ->andWhere('c._expire IS NOT NULL')
                    ->setParameters(array(
                        'tags' => $this->getContextualTags($tags), ))
                    ->getQuery()
                    ->execute(null, \Doctrine\ORM\Query::HYDRATE_SINGLE_SCALAR);

            if (null !== $min) {
                $min = new \DateTime($min);
                $lifetime = (null === $min) ? $lifetime : (null === $expire ? $min->getTimestamp() : min(array($expire->getTimestamp(), $min->getTimestamp()))) - $now->getTimestamp();
            }
        } catch (\Exception $e) {
            $this->log('warning', sprintf('Enable to get expire time for tags (%s) : %s', implode(',', $tags), $e->getMessage()));
        }

        return $lifetime;
    }

    /**
     * Clears all cache records.
     *
     * @return boolean TRUE if cache is cleared FALSE otherwise
     */
    public function clear()
    {
        try {
            $this->_repository
                    ->createQueryBuilder('c')
                    ->delete()
                    ->where('1 = 1')
                    ->getQuery()
                    ->execute();
            $this->resetCacheEntity();
        } catch (\Exception $e) {
            $this->log('warning', sprintf('Enable to clear cache : %s', $e->getMessage()));

            return false;
        }

        return true;
    }

    /**
     * Return the contextual id, according to the defined prefix key.
     *
     * @param string $id
     *
     * @return string
     * @codeCoverageIgnore
     */
    private function getContextualId($id)
    {
        return ($this->_prefix_key) ? md5($this->_prefix_key.$id) : $id;
    }

    /**
     * Return an array of contextual tags, according to the defined prefix key.
     *
     * @param array $tags
     *
     * @return array
     * @codeCoverageIgnore
     */
    private function getContextualTags(array $tags)
    {
        foreach ($tags as &$tag) {
            $tag = $this->getContextualId($tag);
        }
        unset($tag);

        return $tags;
    }

    /**
     * Returns the store entity for provided cache id.
     *
     * @param string $id Cache id
     *
     * @return \BackBee\Cache\DAO\Entity The cache entity or NULL
     * @codeCoverageIgnore
     */
    private function getCacheEntity($id)
    {
        $contextual_id = $this->getContextualId($id);

        if (null === $this->_entity || $this->_entity->getId() !== $contextual_id) {
            $this->_entity = $this->_repository->find($contextual_id);
        }

        return $this->_entity;
    }

    /**
     * Resets the last stored entity.
     *
     * @codeCoverageIgnore
     */
    private function resetCacheEntity()
    {
        if (null !== $this->_entity) {
            $this->_em->detach($this->_entity);
            $this->_entity = null;
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
        $expire = parent::getExpireTime($lifetime, $bypass_control);

        return (0 === $expire) ? null : date_timestamp_set(new \DateTime(), $expire);
    }

    /**
     * Sets the cache prefix key according to the context.
     *
     * @return \BackBee\Cache\DAO\Cache
     * @codeCoverageIgnore
     */
    private function setPrefixKey()
    {
        if (null !== $this->getContext()) {
            $this->_prefix_key = md5($this->getContext());
        }

        return $this;
    }

    /**
     * Sets the entity repository.
     *
     * @return \BackBee\Cache\DAO\Cache
     *
     * @throws \BackBee\Cache\Exception\CacheException Occurs if the entity repository cannot be found
     * @codeCoverageIgnore
     */
    private function setEntityRepository()
    {
        try {
            $this->_repository = $this->_em->getRepository(self::ENTITY_CLASSNAME);
        } catch (\Exception $e) {
            throw new CacheException('Enable to load the cache entity repository', null, $e);
        }

        return $this;
    }

    /**
     * Sets the entity manager.
     *
     * @return \BackBee\Cache\DAO\Cache
     *
     * @throws \BackBee\Cache\Exception\CacheException Occurs if if enable to create a database connection.
     */
    private function setEntityManager()
    {
        try {
            if ($this->_instance_options['em'] instanceof EntityManager) {
                $this->_em = $this->_instance_options['em'];
            } else {
                $this->_em = EntityManagerCreator::create($this->_instance_options['dbal'], $this->getLogger());
            }
        } catch (InvalidArgumentException $e) {
            throw new CacheException('DAO cache: enable to create a database conneciton', CacheException::INVALID_DB_CONNECTION, $e);
        }

        return $this;
    }
}
