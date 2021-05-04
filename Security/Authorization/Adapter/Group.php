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

namespace BackBee\Security\Authorization\Adapter;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use BackBee\BBApplication;

/**
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      Nicolas Dufreche <nicolas.dufreche@lp-digital.fr>
 */
class Group implements RoleReaderAdapterInterface
{
    /**
     * {@inheritdoc}
     */
    public function __construct(BBApplication $application)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function extractRoles(TokenInterface $token)
    {
        $user_roles = array();
        foreach ($token->getUser()->getGroups() as $group) {
            $tmp = array();
            foreach ($group->getRoles() as $role) {
                $tmp[$role->getRole()] = $role;
            }
            $user_roles = array_merge($tmp, $user_roles);
        }

        return $user_roles;
    }
}
