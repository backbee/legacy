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

namespace BackBee\Event\Listener;

use BackBee\BBApplication;
use BackBee\Controller\Exception\FrontControllerException;
use BackBee\Renderer\AbstractRenderer;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @author Mickaël Andrieu <mickael.andrieu@lp-digital.fr>
 */
class ExceptionListener
{
    /**
     * @var AbstractRenderer
     */
    private $renderer;

    /**
     * @var Response
     */
    private $response;

    /**
     * Class constructor.
     *
     * @param BBApplication $application
     */
    public function __construct(BBApplication $application)
    {
        $this->application = $application;
        $this->renderer = $application->getRenderer();
        $this->response = new Response();
    }

    /**
     * @param GetResponseForExceptionEvent $event
     */
    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        $exception = $event->getException();
        $statusCode = $this->getHttpStatusCode($exception->getCode());

        if ($this->application->isDebugMode()) {
            $this->response = $this->getDebugTraceResponse($exception, $statusCode);
        } else {

            $this->response = $this->getErrorPageResponse($exception, $statusCode);
        }

        $event->setResponse($this->response);

        $filterEvent = new FilterResponseEvent($event->getKernel(), $event->getRequest(), $event->getRequestType(), $event->getResponse());
        $event->getDispatcher()->dispatch(KernelEvents::RESPONSE, $filterEvent);
    }

    /**
     * Return response with debug trace.
     *
     * @param \Exception $exception
     * @param int        $statusCode
     *
     * @return Response
     */
    private function getDebugTraceResponse(\Exception $exception, $statusCode)
    {
        $request = $this->application->getRequest();
        $response = (new \Symfony\Component\Debug\ExceptionHandler())->createResponse($exception);
        $response->setStatusCode($statusCode);

        if (in_array('application/json', $request->getAcceptableContentTypes())) {
            $response = new JsonResponse($response->getContent(), $response->getStatusCode(), $response->headers->all());
        }

        return $response;
    }

    /**
     * Returns response for rendered error page.
     *
     * @param  \Exception $exception
     * @param  int        $statusCode
     *
     * @return Response
     */
    private function getErrorPageResponse(\Exception $exception, $statusCode)
    {
        $parameter = $this->application->getContainer()->getParameter('error.default');
        if ($this->application->getContainer()->hasParameter('error.'.$statusCode)) {
            $parameter = $this->application->getContainer()->getParameter('error.'.$statusCode);
        }

        $view = $this->getErrorTemplate($parameter);

        return new Response($this->renderer->partial($view, ['error' => $exception]), $statusCode);
    }

    /**
     * Returns a valid HTTP status code.
     *
     * @param  int $statusCode
     *
     * @return int
     */
    private function getHttpStatusCode($statusCode)
    {
        if ($statusCode >= 100 && $statusCode < 600) {
            return $statusCode;
        } elseif (
                FrontControllerException::BAD_REQUEST === $statusCode
                || FrontControllerException::INTERNAL_ERROR === $statusCode
                || FrontControllerException::NOT_FOUND === $statusCode
        ) {
            return $statusCode - FrontControllerException::UNKNOWN_ERROR;
        }

        return 500;
    }

    /**
     * Returns the path of the template for the selected http status code
     * or default one.
     *
     * @input string $parameter path related to 404|500|default HTTP status code
     *
     * @return string
     */
    private function getErrorTemplate($parameter)
    {
        return $this->application
            ->getContainer()
            ->getParameter('error.base_folder')
            . $parameter
        ;
    }
}
