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

namespace BackBee\Security\Repository;

use Doctrine\ORM\EntityRepository;

use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use BackBee\Security\ApiUserProviderInterface;
use BackBee\Security\User;

/**
 * @category    BackBee
 *
 * 
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class UserRepository extends EntityRepository implements UserProviderInterface, UserCheckerInterface, ApiUserProviderInterface
{
    public function checkPreAuth(UserInterface $user)
    {
    }

    public function checkPostAuth(UserInterface $user)
    {
    }

    /**
     * Loads the user for the given public API key.
     *
     * @param string $publicApiKey The username
     *
     * @return UserInterface
     *
     * @throws UsernameNotFoundException if the user is not found
     */
    public function loadUserByPublicKey($publicApiKey)
    {
        if (null === $user = $this->findOneBy(array('_api_key_public' => $publicApiKey))) {
            throw new UsernameNotFoundException(sprintf('Unknown public API key `%s`.', $publicApiKey));
        }

        return $this->checkActivatedStatus($user);
    }

    /**
     * Loads the user for the given username.
     *
     * @param string $username The username
     *
     * @return UserInterface
     *
     * @throws UsernameNotFoundException if the user is not found
     */
    public function loadUserByUsername($username)
    {
        if (null === $user = $this->findOneBy(array('_login' => $username))) {
            throw new UsernameNotFoundException(sprintf('Unknown username `%s`.', $username));
        }

        return $this->checkActivatedStatus($user);
    }

    /**
     * Checks that the user is activated
     *
     * @param User $user The user
     *
     * @return User
     *
     * @throws DisabledException if the user is not activated
     */
    private function checkActivatedStatus(User $user)
    {
        if (!$user->isActivated()) {
            throw new DisabledException(sprintf('Account `%s`is disabled.', $user->getUsername()));
        }

        return $user;
    }

    public function refreshUser(UserInterface $user)
    {
        if (false === $this->supportsClass(get_class($user))) {
            throw new UnsupportedUserException(sprintf('Unsupported User class `%s`.', get_class($user)));
        }

        return $this->loadUserByUsername($user->getUsername());
    }

    public function supportsClass($class)
    {
        return ($class == 'BackBee\Security\User');
    }

    public function getCollection($params)
    {
        $qb = $this->createQueryBuilder('u');

        $likeParams = ['firstname', 'lastname', 'email', 'login'];

        if (array_key_exists('name', $params)) {
            $nameFilters = explode(' ', $params['name']);

            foreach ($nameFilters as $key => $value) {
                $qb->andWhere($qb->expr()->orX(
                    $qb->expr()->like('u._firstname', ':p' . $key),
                    $qb->expr()->like('u._lastname', ':p' . $key)
                ));
                $qb->setParameter(':p' . $key, '%' . $value . '%');
            }

            unset($params['name']);
        }
        foreach ($params as $key => $value) {
            if (property_exists('BackBee\Security\User', '_' . $key)) {
                if (in_array($key, $likeParams)) {
                    $qb->andWhere(
                        $qb->expr()->like('u._' . $key, ':' . $key)
                    );
                    $qb->setParameter(':' . $key, '%' . $value . '%');
                } else {
                    $qb->andWhere(
                        $qb->expr()->eq('u._' . $key, ':' . $key)
                    );
                    $qb->setParameter(':' . $key, $value);
                }
            }
        }


        return $qb->getQuery()->getResult();
    }
}
