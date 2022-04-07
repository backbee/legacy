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

namespace BackBee\Rest\EventListener;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AccountStatusException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\InsufficientAuthenticationException;
use BackBee\Event\Listener\AbstractPathEnabledListener;
use BackBee\Controller\Exception\FrontControllerException;
use BackBee\Security\Exception\SecurityException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Body listener/encoder.
 *
 * @category    BackBee
 *
 *
 * @author      k.golovin
 */
class ExceptionListener extends AbstractPathEnabledListener
{
    /**
     * @var array
     */
    private $mapping;

    public function setMapping(array $mapping)
    {
        $this->mapping = $mapping;
    }

    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        if (!$this->isEnabled($event->getRequest())) {
            return;
        }

        $exception = $event->getException();
        $exceptionClass = get_class($exception);

        if (isset($this->mapping[$exceptionClass])) {
            $code = isset($this->mapping[$exceptionClass]['code']) ? $this->mapping[$exceptionClass]['code'] : 500;
            $message = isset($this->mapping[$exceptionClass]['message']) ? $this->mapping[$exceptionClass]['message'] : $exception->getMessage();

            if (!$event->getResponse()) {
                $event->setResponse(new Response());
            }

            $event->getResponse()->setStatusCode($code, $message);
            $event->getResponse()->headers->add(array('Content-Type' => 'application/json'));
        } elseif ($exception instanceof HttpExceptionInterface) {
            if (!$event->getResponse()) {
                $event->setResponse(new Response());
            }
            // keep the HTTP status code and headers
            $event->getResponse()->setStatusCode($exception->getStatusCode(), $exception->getMessage());
            $event->getResponse()->headers->add(array('Content-Type' => 'application/json'));

            if ($exception instanceof \BackBee\Rest\Exception\ValidationException) {
                $event->getResponse()->setContent(json_encode(array('errors' => $exception->getErrorsArray())));
            }else{
                $event->getResponse()->setContent(json_encode(array('exception' => $exception->getMessage())));
            }
        } elseif ($exception instanceof FrontControllerException) {
            if (!$event->getResponse()) {
                $event->setResponse(new Response());
            }
            // keep the HTTP status code
            $event->getResponse()->setStatusCode($exception->getStatusCode());
        } elseif ($exception instanceof AccountStatusException ||
                  $exception instanceof InsufficientAuthenticationException ||
                  $exception instanceof BadRequestHttpException) {
            // Forbidden access
            $this->setNewResponse($event, 403, $exception->getMessage());
        } elseif ($exception instanceof AuthenticationException || $exception instanceof AccessDeniedException) {
            // Unauthorized access
            $this->setNewResponse($event, 401, $exception->getMessage());
        } elseif ($exception instanceof SecurityException) {
            $event->setResponse(new Response());

            $statusCode = 403;

            switch ($exception->getCode()) {
                case SecurityException::UNKNOWN_USER:
                case SecurityException::INVALID_CREDENTIALS:
                case SecurityException::EXPIRED_AUTH:
                case SecurityException::EXPIRED_TOKEN:
                    $statusCode = 401;
                    break;

                default:
                    $statusCode = 403;
            }

            $this->setNewResponse($event, $statusCode, $exception->getMessage());
        } elseif ($exception instanceof \RuntimeException) {
            $this->setNewResponse($event, 500, $exception->getMessage());
            $event->getResponse()->setContent(json_encode(array('internal_error' => $exception->getMessage())));
            $event->getResponse()->headers->add(['Content-Type', 'application/json']);
        } else {
            $this->setNewResponse($event, 500, $exception->getMessage());
        }
    }

    /**
     * Sets a new response to $event
     * @param GetResponseForExceptionEvent $event   The event
     * @param int $statusCode                       The status code of the new response
     * @param string $message                       Optional, the message of the new response
     */
    private function setNewResponse(GetResponseForExceptionEvent $event, $statusCode, $message = '')
    {
        $event->setResponse(new Response());
        $event->getResponse()->setStatusCode($statusCode, $message);
    }
}
