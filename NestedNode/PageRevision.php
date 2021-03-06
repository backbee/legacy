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

namespace BackBee\NestedNode;

use Doctrine\ORM\Mapping as ORM;

/**
 * PageRevison object in
 *
 * A page revision is...
 *
 * @category    BackBee
 *
 *
 * @author      m.baptista <michel.baptista@lp-digital.fr>
 * @ORM\Entity(repositoryClass="BackBee\NestedNode\Repository\PageRevisionRepository")
 * @ORM\Table(name="page_revision")
 */
class PageRevision
{
    /**
     * Versions.
     */
    const VERSION_CURRENT = 0;
    const VERSION_DRAFT = 1;
    const VERSION_SUBMITED = 2;

    /**
     * Unique identifier of the revision.
     *
     * @var integer
     * @ORM\Id
     * @ORM\Column(type="integer", name="id")
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $_id;

    /**
     * The publication datetime.
     *
     * @var DateTime
     * @ORM\Column(type="datetime", name="date")
     */
    protected $_date;

    /**
     * The version.
     *
     * @var DateTime
     * @ORM\Column(type="integer", name="version")
     */
    protected $_version;

    /**
     * @ORM\ManyToOne(targetEntity="BackBee\Security\User", inversedBy="_revisions", fetch="EXTRA_LAZY")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     */
    protected $_user;

    /**
     * @ORM\ManyToOne(targetEntity="BackBee\NestedNode\Page", inversedBy="_revisions", fetch="EXTRA_LAZY")
     * @ORM\JoinColumn(name="page_uid", referencedColumnName="uid")
     */
    protected $_page;

    /**
     * @ORM\ManyToOne(targetEntity="BackBee\ClassContent\AbstractClassContent", cascade={"persist"}, fetch="EXTRA_LAZY")
     * @ORM\JoinColumn(name="content_uid", referencedColumnName="uid")
     */
    protected $_content;

    /**
     * Class constructor.
     */
    public function __construct()
    {
        $this->_date = new \DateTime();
        $this->_version = PageRevision::VERSION_DRAFT;
    }

    public function setUser(\BackBee\Security\User $user)
    {
        $this->_user = $user;

        return $this;
    }

    public function setPage(\BackBee\NestedNode\Page $page)
    {
        $this->_page = $page;

        return $this;
    }

    public function setContent($content)
    {
        $this->_content = $content;

        return $this;
    }

    public function setDate($date)
    {
        $this->_date = $date;

        return $this;
    }

    public function setVersion($version)
    {
        $this->_version = $version;

        return $this;
    }

    public function getId()
    {
        return $this->_id;
    }

    public function getUser()
    {
        return $this->_user;
    }

    public function getPage()
    {
        return $this->_page;
    }

    public function getContent()
    {
        return $this->_content;
    }

    public function getDate()
    {
        return $this->_date;
    }

    public function getVersion()
    {
        return $this->_version;
    }
}
