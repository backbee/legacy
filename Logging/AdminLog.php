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

namespace BackBee\Logging;

use Symfony\Component\Security\Acl\Domain\ObjectIdentity;
use Symfony\Component\Security\Acl\Domain\UserSecurityIdentity;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

use Doctrine\ORM\Mapping as ORM;

/**
 * @category    BackBee Bundle
 *
 *
 * @ORM\Entity(repositoryClass="BackBee\Logging\Repository\AdminLogRepository")
 * @ORM\Table(name="admin_log")
 */
class AdminLog
{
    /**
     * @var string
     * @ORM\Id
     * @ORM\Column(type="string", length=32, name="uid")
     */
    protected $_uid;

    /**
     * @var Symfony\Component\Security\Acl\Domain\UserSecurityIdentity
     * @ORM\Column(type="string", name="owner")
     */
    protected $_owner;

    /**
     * @var string
     * @ORM\Column(type="string", name="controller")
     */
    protected $_controller;

    /**
     * @var string
     * @ORM\Column(type="string", name="action")
     */
    protected $_action;

    /**
     * @var string
     * @ORM\Column(type="string", name="entity", nullable=true)
     */
    protected $_entity;

    /**
     * @var string
     * @ORM\Column(type="datetime", name="created_at")
     */
    protected $_created_at;

    public function __construct($uid = null, $token = null)
    {
        $this->_uid = (is_null($uid)) ? md5(uniqid('', true)) : $uid;
        $this->_created_at = new \DateTime();
        if ($token instanceof TokenInterface) {
            $this->_owner = UserSecurityIdentity::fromToken($token);
        }
    }

    /**
     * Get the owner of the log.
     *
     * @return UserInterface
     */
    public function getOwner()
    {
        return $this->_owner;
    }

    /**
     * Set the owner of the log.
     *
     * @param UserInterface $user
     *
     * @return AdminLog
     */
    public function setOwner(UserInterface $user)
    {
        $this->_owner = UserSecurityIdentity::fromAccount($user);

        return $this;
    }

    /**
     * Get the controller.
     *
     * @return String
     */
    public function getController()
    {
        return $this->_controller;
    }

    /**
     * Set the controller.
     *
     * @param string $controller
     *
     * @return AdminLog
     */
    public function setController($controller)
    {
        $this->_controller = $controller;

        return $this;
    }

    /**
     * Get the action call in the controller.
     *
     * @return String
     */
    public function getAction()
    {
        return $this->_action;
    }

    /**
     * Set the action call in the controller.
     *
     * @param String $action
     *
     * @return AdminLog
     */
    public function setAction($action)
    {
        $this->_action = $action;

        return $this;
    }

    /**
     * Set the entity call in the controller.
     *
     * @return Object
     */
    public function getEntity()
    {
        return $this->_entity;
    }

    /**
     * Set the entity call in the controller.
     *
     * @param Object $entity
     *
     * @return AdminLog
     */
    public function setEntity($entity)
    {
        $this->_entity = ObjectIdentity::fromDomainObject($entity);

        return $this;
    }

    /**
     * Get the datetime of the log.
     *
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->_created_at;
    }
}
