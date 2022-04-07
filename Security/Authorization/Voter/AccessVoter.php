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

namespace BackBee\Security\Authorization\Voter;

use BackBee\BBApplication;
use BackBee\Security\Authorization\Adaptator\RoleReaderAdapterInterface;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * @category    BackBee
 *
 *
 * @author      Nicolas Dufreche <nicolas.dufreche@lp-digital.fr>
 */
class AccessVoter implements VoterInterface
{
    private $_application;
    private $_adapter;
    private $_prefix;
    private $_class;

    /**
     * Constructor.
     *
     * @param RoleReaderAdapterInterface $adapter
     * @param string               $prefix    The role prefix
     */
    public function __construct(BBApplication $application, RoleReaderAdapterInterface $adapter, $class, $prefix = 'BB_')
    {
        $this->_adapter = $adapter;
        $this->_prefix = $prefix;
        $this->_class = $class;
        $this->_application = $application;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsAttribute($attribute)
    {
        return 0 === strpos($attribute, $this->_prefix);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsClass($class)
    {
        return $class === 'BackBee\Security\Token\BBUserToken';
    }

    /**
     * {@inheritdoc}
     */
    public function vote(TokenInterface $token, $object, array $attributes)
    {
        if (get_class($object) === $this->_class) {
            $result = $this->voteForSomething($token, $object, $attributes);
        } else {
            $result = $this->voteForAccess($token, $attributes);
        }

        return $result;
    }

    private function voteForAccess(TokenInterface $token, array $attributes)
    {
        $result = VoterInterface::ACCESS_ABSTAIN;
    }

    private function voteForSomething(TokenInterface $token, $object, array $attributes)
    {
        $result = VoterInterface::ACCESS_DENIED;
    }

    /**
     * @param TokenInterface $token
     *
     * @return array
     */
    private function extractRoles(TokenInterface $token)
    {
        return $this->_adapter->extractRoles($token);
    }

    private function getAccessRole()
    {
        $classPath = explode('\\', $this->_class);
        $config = $this->_application->getConfig()->getSecurityConfig();

        foreach ($array as $value) {
        }
    }
}
