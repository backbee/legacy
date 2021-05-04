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

namespace BackBee\Security\Listeners;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\Firewall\ListenerInterface;
use Symfony\Component\Security\Http\SecurityEvents;
use BackBee\Security\Token\UsernamePasswordToken;

/**
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class UsernamePasswordAuthenticationListener implements ListenerInterface
{
    private $_context;
    private $_authenticationManager;
    private $_login_path;
    private $_check_path;
    private $_logger;

    public function __construct(SecurityContextInterface $context, AuthenticationManagerInterface $authManager, $login_path = null, $check_path = null, LoggerInterface $logger = null)
    {
        $this->_context = $context;
        $this->_authenticationManager = $authManager;
        $this->_login_path = $login_path;
        $this->_check_path = $check_path;
        $this->_logger = $logger;
    }

    public function handle(GetResponseEvent $event)
    {
        $request = $event->getRequest();

        $token = $this->_context->getToken();
        $errmsg = '';

        if (null !== $request->request->get('login') && null !== $request->request->get('password')) {
            $token = new UsernamePasswordToken($request->request->get('login'), $request->request->get('password'));
            $token->setUser($request->request->get('login'), $request->request->get('password'));
            try {
                $token = $this->_authenticationManager->authenticate($token);

                if (null !== $this->_logger) {
                    $this->_logger->info(sprintf('Authentication request succeed for user "%s"', $token->getUsername()));
                }
                
                $loginEvent = new InteractiveLoginEvent($request, $token);
                $event->getDispatcher()
                        ->dispatch(SecurityEvents::INTERACTIVE_LOGIN, $loginEvent);
            } catch (\Symfony\Component\Security\Core\Exception\AuthenticationException $e) {
                $event->getDispatcher()
                        ->dispatch(\Symfony\Component\Security\Core\AuthenticationEvents::AUTHENTICATION_FAILURE, new \Symfony\Component\Security\Core\Event\AuthenticationFailureEvent($token, $e));
                $errmsg = $e->getMessage();
                if (null !== $this->_logger) {
                    $this->_logger->info(sprintf('Authentication request failed for user "%s": %s', $token->getUsername(), $e->getMessage()));
                }
            } catch (\Exception $e) {
                $errmsg = $e->getMessage();
                if (null !== $this->_logger) {
                    $this->_logger->info(sprintf('Authentication request failed for user "%s": %s', $token->getUsername(), $e->getMessage()));
                }
            }
        }

        if (is_a($token, 'BackBee\Security\Token\UsernamePasswordToken') && $errmsg != '') {
            if (null !== $this->_login_path) {
                if (preg_match('/%(.*)%/s', $this->_login_path, $matches)) {
                    if ($this->_context->getApplication()->getContainer()->hasParameter($matches[1])) {
                        $this->_login_path = $this->_context->getApplication()->getContainer()->getParameter($matches[1]);
                    }
                }

                $redirect = $request->query->get('redirect');
                if (null === $redirect) {
                    $redirect = $request->request->get('redirect', '');
                }
                if ('' === $redirect) {
                    $redirect = $request->getPathInfo();
                }
                if (null !== $qs = $request->getQueryString()) {
                    $redirect .= '?'.$qs;
                }

                $response = new RedirectResponse($event->getRequest()->getUriForPath($this->_login_path.'?redirect='.urlencode($redirect).'&errmsg='.urlencode($errmsg).'&login='.urlencode($request->request->get('login'))));
                $event->setResponse($response);

                return;
            }

            $response = new Response();
            $response->setStatusCode(403);
            $event->setResponse($response);
        }

        if (null !== $token && is_a($token, 'BackBee\Security\Token\UsernamePasswordToken')) {
            $this->_context->setToken($token);

            if ($request->request->get('redirect')) {
                $response = new RedirectResponse($request->getBaseUrl().$request->request->get('redirect'));
                $event->setResponse($response);
            }
        }
    }
}
