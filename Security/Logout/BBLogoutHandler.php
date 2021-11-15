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

namespace BackBee\Security\Logout;

use BackBee\Security\Authentication\Provider\BBAuthenticationProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Logout\LogoutHandlerInterface;

/**
 * Handler for clearing nonce file of BB connection.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class BBLogoutHandler implements LogoutHandlerInterface
{
    /**
     * The BB user authentication provider.
     *
     * @var \ $authenticationProvider
     */
    private $authenticationProvider;

    /**
     * Class constructor.
     *
     * @param \BackBee\Security\Authentication\Provider\BBAuthenticationProvider $authentication_provider
     */
    public function __construct(BBAuthenticationProvider $authentication_provider)
    {
        $this->authenticationProvider = $authentication_provider;
    }

    /**
     * Invalidate the current BB connection.
     *
     * @param \Symfony\Component\HttpFoundation\Request                            $request
     * @param \Symfony\Component\HttpFoundation\Response                           $response
     * @param \Symfony\Component\Security\Core\Authentication\Token\TokenInterface $token
     */
    public function logout(Request $request, Response $response, TokenInterface $token): void
    {
        if ($request->getSession()) {
            $request->getSession()->invalidate();
        }

        $this->authenticationProvider->clearNonce($token);
    }
}
