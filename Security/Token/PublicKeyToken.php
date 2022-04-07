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

namespace BackBee\Security\Token;

use Symfony\Component\Security\Core\User\UserInterface;
use BackBee\Security\User;

/**
 * @category    BackBee
 *
 *
 * @author      k.golovin
 */
class PublicKeyToken extends BBUserToken
{
    /**
     * @var string
     */
    private $publicKey;

    /**
     * @var string
     */
    private $signature;

    /**
     * Constructor.
     *
     * @param array $roles An array of roles
     */
    public function __construct(array $roles = array())
    {
        parent::__construct($roles);

        $this->setAuthenticated(count($roles) > 0);
    }

    /**
     * @codeCoverageIgnore
     *
     * @return type
     */
    public function isAuthenticated()
    {
        return ($this->getUser() instanceof UserInterface)
            ? 0 < count($this->getUser()->getRoles())
            : false
        ;
    }

    /**
     * @codeCoverageIgnore
     *
     * @return type
     */
    public function getCredentials()
    {
        return '';
    }

    /**
     * @codeCoverageIgnore
     * {@inheritdoc}
     */
    public function eraseCredentials()
    {
        parent::eraseCredentials();

        $this->_credentials = null;
    }

    /**
     * {@inheritdoc}
     */
    public function getUsername()
    {
        $username = '';
        if ($this->getUser() instanceof User) {
            $username = $this->getUser()->getApiKeyPublic();
        } elseif ($username instanceof UserInterface) {
            $username = $this->getUser()->getUsername();
        } else {
            $username = (string) $this->getUser();
        }

        return $username;
    }

    /**
     * Public key attribute setter.
     *
     * @param string $signature new public key value
     *
     * @return self
     */
    public function setPublicKey($publicKey)
    {
        $this->publicKey = $publicKey;

        return $this;
    }

    /**
     * Public key attribute getter.
     *
     * @return string the current token public key
     */
    public function getPublicKey()
    {
        return $this->publicKey;
    }

    /**
     * Signature attribute setter.
     *
     * @param string $signature new signature value
     *
     * @return self
     */
    public function setSignature($signature)
    {
        $this->signature = $signature;

        return $this;
    }

    /**
     * Signature attribute getter.
     *
     * @return string the current token signature
     */
    public function getSignature()
    {
        return $this->signature;
    }
}
