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

namespace BackBee\Security\Listeners;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\Security\Http\Firewall\ListenerInterface;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Http\Logout\LogoutHandlerInterface;
use Symfony\Component\Security\Http\Logout\LogoutSuccessHandlerInterface;

/**
 * LogoutListener logout users.
 *
 * @category    BackBee
 *
 * 
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class LogoutListener implements ListenerInterface
{
    /**
     * The current security context.
     *
     * @var \Symfony\Component\Security\Core\SecurityContextInterface
     */
    private $securityContext;

    /**
     * An array of logout handlers.
     *
     * @var array
     */
    private $handlers;

    /**
     * @var \Symfony\Component\Security\Http\HttpUtils
     */
    private $httpUtils;

    /**
     * On success handler.
     *
     * @var \Symfony\Component\Security\Http\Logout\LogoutSuccessHandlerInterface
     */
    private $successHandler;

    /**
     * Class constructor.
     *
     * @param \Symfony\Component\Security\Core\SecurityContextInterface             $securityContext
     * @param \Symfony\Component\Security\Http\HttpUtils                            $httpUtils       An HttpUtilsInterface instance
     * @param \Symfony\Component\Security\Http\Logout\LogoutSuccessHandlerInterface $successHandler  A LogoutSuccessHandlerInterface instance
     */
    public function __construct(SecurityContextInterface $securityContext, HttpUtils $httpUtils, LogoutSuccessHandlerInterface $successHandler)
    {
        $this->securityContext = $securityContext;
        $this->httpUtils = $httpUtils;
        $this->handlers = array();
        $this->successHandler = $successHandler;
    }

    /**
     * Adds a logout handler.
     *
     * @param \Symfony\Component\Security\Http\Logout\LogoutHandlerInterface $handler
     * @codeCoverageIgnore
     */
    public function addHandler(LogoutHandlerInterface $handler)
    {
        $this->handlers[] = $handler;
    }

    /**
     * Performs the logout if requested.
     *
     * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event A GetResponseEvent instance
     *
     * @throws RuntimeException if the LogoutSuccessHandlerInterface instance does not return a response
     */
    public function handle(GetResponseEvent $event)
    {
        $request = $event->getRequest();

        $response = $this->successHandler->onLogoutSuccess($request);
        if (!$response instanceof Response) {
            throw new \RuntimeException('Logout Success Handler did not return a Response.');
        }

        // handle multiple logout attempts gracefully
        if (null !== $token = $this->securityContext->getToken()) {
            foreach ($this->handlers as $handler) {
                $handler->logout($request, $response, $token);
            }
        }

        $this->securityContext->setToken(null);
        $event->setResponse($response);
    }

    /**
     * Whether this request is asking for logout.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return Boolean
     * @codeCoverageIgnore
     */
    protected function requiresLogout(Request $request)
    {
        return true;
    }
}
