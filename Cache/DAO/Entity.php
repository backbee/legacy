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

use Doctrine\ORM\Mapping as ORM;

/**
 * Entity for DAO stored cache data.
 *
 * @category    BackBee
 *
 *
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 * @ORM\Entity
 * @ORM\Table(name="cache",indexes={
 *     @ORM\Index(name="IDX_EXPIRE", columns={"expire"}),
 *     @ORM\Index(name="IDX_TAG", columns={"tag"})})
 */
class Entity
{
    /**
     * The cache id.
     *
     * @var string
     * @ORM\Id
     * @ORM\Column(type="string", name="uid")
     */
    protected $_uid;

    /**
     * A tag associated to the cache.
     *
     * @var string
     * @ORM\Column(type="string", name="tag", nullable=true)
     */
    protected $_tag;

    /**
     * The data stored.
     *
     * @var string
     * @ORM\Column(type="text", name="data")
     */
    protected $_data;

    /**
     * The expire date time for the stored data.
     *
     * @var \DateTime
     * @ORM\Column(type="datetime", name="expire", nullable=true)
     */
    protected $_expire;

    /**
     * The creation date time.
     *
     * @var \DateTime
     * @ORM\Column(type="datetime", name="created")
     */
    protected $_created;

    /**
     * Class constructor.
     *
     * @param string $uid Optional, the cache id
     * @codeCoverageIgnore
     */
    public function __construct($uid = null)
    {
        $this->_uid = $uid;
        $this->_created = new \DateTime();
    }

    /**
     * Sets the cache id.
     *
     * @param string $uid
     *
     * @return \BackBee\Cache\DAO\Entity
     * @codeCoverageIgnore
     */
    public function setUid($uid)
    {
        $this->_uid = $uid;

        return $this;
    }

    /**
     * Sets the data to store.
     *
     * @param string $data
     *
     * @return \BackBee\Cache\DAO\Entity
     * @codeCoverageIgnore
     */
    public function setData($data)
    {
        $this->_data = $data;

        return $this;
    }

    /**
     * Sets the expire date time.
     *
     * @param \DateTime $expire
     *
     * @return \BackBee\Cache\DAO\Entity
     * @codeCoverageIgnore
     */
    public function setExpire(\DateTime $expire = null)
    {
        $this->_expire = ($expire) ? $expire : null;

        return $this;
    }

    /**
     * Set the associated tag.
     *
     * @param string $tag
     *
     * @return \BackBee\Cache\DAO\Entity
     * @codeCoverageIgnore
     */
    public function setTag($tag = null)
    {
        $this->_tag = $tag;

        return $this;
    }

    /**
     * Returns the cache id.
     *
     * @return string
     * @codeCoverageIgnore
     */
    public function getId()
    {
        return $this->_uid;
    }

    /**
     * Returns the stored data.
     *
     * @return string
     * @codeCoverageIgnore
     */
    public function getData()
    {
        return $this->_data;
    }

    /**
     * Returns the data time expiration.
     *
     * @return \DateTime
     * @codeCoverageIgnore
     */
    public function getExpire()
    {
        return $this->_expire;
    }
}
