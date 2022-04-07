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

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\Security\Http\Firewall\ListenerInterface;
use BackBee\Security\Exception\SecurityException;
use BackBee\Security\Token\PublicKeyToken;

/**
 * @category    BackBee
 *
 * 
 * @author      k.golovin
 */
class PublicKeyAuthenticationListener implements ListenerInterface
{
    const AUTH_PUBLIC_KEY_TOKEN = 'X-API-KEY';
    const AUTH_SIGNATURE_TOKEN = 'X-API-SIGNATURE';

    private $context;
    private $authenticationManager;
    private $logger;

    public function __construct(SecurityContextInterface $context, AuthenticationManagerInterface $authManager, LoggerInterface $logger = null)
    {
        $this->context = $context;
        $this->authenticationManager = $authManager;
        $this->logger = $logger;
    }

    public function handle(GetResponseEvent $event)
    {
        $request = $event->getRequest();

        $publicKey = $request->headers->get(self::AUTH_PUBLIC_KEY_TOKEN);

        $token = new PublicKeyToken();
        $token->setUser($publicKey);

        $token->setPublicKey($publicKey);
        $token->request = $request;

        $token->setNonce($request->headers->get(self::AUTH_SIGNATURE_TOKEN));

        try {
            $token = $this->authenticationManager->authenticate($token);

            if (null !== $this->logger) {
                $this->logger->info(sprintf('PubliKey Authentication request succeed for public key "%s"', $token->getUsername()));
            }

            return $this->context->setToken($token);
        } catch (SecurityException $e) {
            if (null !== $this->logger) {
                $this->logger->info(sprintf('PubliKey Authentication request failed for public key "%s": %s', $token->getUsername(), str_replace("\n", ' ', $e->getMessage())));
            }

            throw $e;
        } catch (\Exception $e) {
            if (null !== $this->logger) {
                $this->logger->error($e->getMessage());
            }

            throw $e;
        }
    }
}
