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

namespace BackBee\Security\Token;

use BackBee\Security\User;
use DateTime;
use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;

/**
 * Base class for BackBee token's user.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class BBUserToken extends AbstractToken
{
    /**
     * Token default lifetime (20 minutes)
     */
    const DEFAULT_LIFETIME = 1200;

    /**
     * Creation date of the token.
     *
     * @var DateTime
     */
    private $created;

    /**
     * Digest to be checks associated to the token.
     *
     * @var string
     */
    private $digest;

    /**
     * User's private nonce for the token.
     *
     * @var string
     */
    private $nonce;

    /**
     * Token max lifetime (in second).
     *
     * @var integer
     */
    private $lifetime = self::DEFAULT_LIFETIME;

    /**
     * Class Constructor.
     *
     * @param array $roles An array of roles
     */
    public function __construct(array $roles = array())
    {
        parent::__construct($roles);
        $this->setAuthenticated(true);
    }

    /**
     * Returns the creation date of the token.
     *
     * @codeCoverageIgnore
     *
     * @return DateTime
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * Returns the credentials (empty for this token).
     *
     * @codeCoverageIgnore
     *
     * @return string
     */
    public function getCredentials()
    {
        return '';
    }

    /**
     * Returns the current digest.
     *
     * @codeCoverageIgnore
     *
     * @return string
     */
    public function getDigest()
    {
        return $this->digest;
    }

    /**
     * Returns the user's private nonce.
     *
     * @codeCoverageIgnore
     *
     * @return type
     */
    public function getNonce()
    {
        return $this->nonce;
    }

    /**
     * Sets the creation date.
     *
     * @codeCoverageIgnore
     *
     * @param type $created
     *
     * @return self
     */
    public function setCreated($created)
    {
        $this->created = $created instanceof DateTime ? $created->format('Y-m-d H:i:s') : $created;

        return $this;
    }

    /**
     * Sets token max lifetime.
     *
     * @param integer $lifetime The token max lifetime value
     */
    public function setLifetime($lifetime)
    {
        $this->lifetime = (int) $lifetime;

        return $this;
    }

    /**
     * Returns true if current token is expired by comparing current timestamp
     * with token created datetime and its max lifetime.
     *
     * @return boolean
     * @throws \LogicException if token max lifetime or/and token created datetime are not setted
     */
    public function isExpired()
    {
        if (null === $this->created || 0 === $this->lifetime) {
            throw new \LogicException(
                'Cannot define if token is expired, created datetime or/and lifetime are missing.'
            );
        }

        return time() > strtotime($this->created) + $this->lifetime;
    }

    /**
     * Sets the digest.
     *
     * @codeCoverageIgnore
     *
     * @param type $digest
     *
     * @return self
     */
    public function setDigest($digest)
    {
        $this->digest = $digest;

        return $this;
    }

    /**
     * Sets the user's private nonce.
     *
     * @codeCoverageIgnore
     *
     * @param type $nonce
     *
     * @return self
     */
    public function setNonce($nonce)
    {
        $this->nonce = $nonce;

        return $this;
    }

    /**
     * Sets the user.
     *
     * @codeCoverageIgnore
     *
     * @param type $user
     *
     * @return self
     */
    public function setUser($user)
    {
        parent::setUser($user);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        return serialize([
            is_object($this->getUser()) ? clone $this->getUser() : $this->getUser(),
            $this->isAuthenticated(),
            $this->getRoles(),
            $this->getAttributes(),
            $this->nonce,
            $this->created,
            $this->lifetime,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized)
    {
        $array = unserialize($serialized);
        $this->lifetime = array_pop($array);
        $this->created = array_pop($array);
        $this->nonce = array_pop($array);

        parent::unserialize(serialize($array));
    }

    /**
     * Get uniq token.
     *
     * @return BBUserToken
     */
    public function getUniqToken(): BBUserToken
    {
        $uniqToken = new self(['ROLE_API_USER']);
        $uniqToken
            ->setUser(new User('uniq_user'))
            ->setCreated(new DateTime());

        return $uniqToken;
    }
}
