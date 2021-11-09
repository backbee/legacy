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

namespace BackBee\Rest\Controller;

use BackBee\Rest\Controller\Annotations as Rest;
use BackBee\Security\Token\BBUserToken;
use BackBee\Security\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Auth Controller.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      k.golovin
 */
class SecurityController extends AbstractRestController
{
    /**
     * @Rest\RequestParam(name="username", requirements={@Assert\NotBlank})
     * @Rest\RequestParam(name="password", requirements={@Assert\NotBlank})
     */
    public function authenticateAction(Request $request): JsonResponse
    {
        $created = date('Y-m-d H:i:s');
        $token = new BBUserToken();
        $token->setUser($request->request->get('username'));
        $token->setCreated($created);
        $token->setNonce(md5(uniqid('', true)));

        if ($factory = $this->getApplication()->getSecurityContext()->getEncoderFactory()) {
            $encodedPassword = $factory
                ->getEncoder(User::class)
                ->encodePassword($request->request->get('password'), '');
        }

        $token->setDigest(md5($token->getNonce() . $created . $encodedPassword));

        $tokenAuthenticated = $this
            ->getApplication()
            ->getSecurityContext()
            ->getAuthenticationManager()
            ->authenticate($token);

        if (!$tokenAuthenticated->getUser()->getApiKeyEnabled()) {
            throw new DisabledException('API access forbidden');
        }

        $this->getApplication()->getSecurityContext()->setToken($tokenAuthenticated);

        return $this->createJsonResponse(null, 201, array(
            'X-API-KEY' => $tokenAuthenticated->getUser()->getApiKeyPublic(),
            'X-API-SIGNATURE' => $tokenAuthenticated->getNonce(),
        ));
    }

    /**
     * @Rest\Security(expression="is_fully_authenticated()")
     */
    public function deleteSessionAction(Request $request): Response
    {
        if (null === $request->getSession()) {
            throw new NotFoundHttpException('Session doesn\'t exist');
        }

        $event = new GetResponseEvent(
            $this->getApplication()->getController(),
            $request,
            HttpKernelInterface::MASTER_REQUEST
        );
        $this->getApplication()->getEventDispatcher()->dispatch('frontcontroller.request.logout', $event);

        return new Response('', 204);
    }
}
