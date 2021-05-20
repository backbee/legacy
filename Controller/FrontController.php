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

namespace BackBee\Controller;

use BackBee\BBApplication;
use BackBee\Controller\Exception\FrontControllerException;
use BackBee\Event\PageFilterEvent;
use BackBee\NestedNode\Page;
use BackBee\Routing\Matcher\UrlMatcher;
use BackBee\Routing\RequestContext;
use BackBee\Routing\RouteCollection;
use Exception;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Event\PostResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The BackBee front controller
 * It handles and dispatches HTTP requests received.
 *
 * @category    BackBee
 *
 * @copyright   Lp system
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class FrontController implements HttpKernelInterface
{
    const DEFAULT_URL_EXTENSION = 'html';

    /**
     * Current BackBee application.
     *
     * @var BBApplication
     */
    protected $application;

    /**
     * Current request handled.
     *
     * @var Request
     */
    protected $request;

    /**
     * Response.
     *
     * @var Response
     */
    protected $response;

    /**
     * Current request context.
     *
     * @var RequestContext
     */
    protected $requestContext;

    /**
     * @var boolean
     */
    protected $force_url_extension = true;

    /**
     * @var string
     */
    protected $url_extension;

    /**
     * Class constructor.
     *
     * @access public
     *
     * @param BBApplication|null $application The current BBapplication
     *
     * @throws FrontControllerException
     */
    public function __construct(BBApplication $application = null)
    {
        $this->application = $application;

        if (null !== $application) {
            if (null !== $forceUrlExtension = $application->getContainer()->getParameter('force_url_extension')) {
                $this->force_url_extension = $forceUrlExtension;
            }

            if (false === $this->getRouteCollection()->isRestored()) {
                $route = $application->getConfig()->getRouteConfig();
                if (true === is_array($route) && 0 < count($route)) {
                    $this->registerRoutes('controller', $route);
                }
            }
        }

        $this->url_extension = self::DEFAULT_URL_EXTENSION;

        register_shutdown_function(array($this, 'terminate'));
    }

    /**
     * Returns current BackBee application.
     *
     * @return BBApplication
     */
    public function getApplication(): BBApplication
    {
        return $this->application;
    }

    /**
     * Returns the current request.
     *
     * @access public
     *
     * @return Request
     */
    public function getRequest()
    {
        if (null === $this->request) {
            $this->request = $this->getApplication()->getContainer()->get('request');
        }

        return $this->request;
    }

    /**
     * Returns the routes collection defined.
     *
     * @access public
     *
     * @return RouteCollection
     */
    public function getRouteCollection()
    {
        return $this->application->getContainer()->get('routing');
    }

    /**
     * Returns true if url extension is required, else false.
     *
     * @return boolean true if url extension is required, else false
     */
    public function isUrlExtensionRequired()
    {
        return $this->force_url_extension;
    }

    /**
     * Getter of url extension.
     *
     * @return string configured url extension, html by default
     */
    public function getUrlExtension()
    {
        return $this->url_extension;
    }

    /**
     * Handles a request.
     *
     * @access public
     *
     * @param Request $request The request to handle
     * @param integer $type    The type of the request
     *                         (one of HttpKernelInterface::MASTER_REQUEST or HttpKernelInterface::SUB_REQUEST)
     * @param Boolean $catch   Whether to catch exceptions or not
     *
     * @throws FrontControllerException
     */
    public function handle(Request $request = null, $type = self::MASTER_REQUEST, $catch = true)
    {
        // request
        $event = new GetResponseEvent($this, $this->getRequest(), $type);
        $this->application->getEventDispatcher()->dispatch(KernelEvents::REQUEST, $event);

        if (null !== $response = $event->getResponse()) {
            return $response;
        }

        try {
            if (null !== $request) {
                $this->request = $request;
            }

            // resolve url
            if (!$this->getRequest()->attributes->get('_controller')) {
                $urlMatcher = new UrlMatcher($this->getRouteCollection(), $this->getRequestContext());
                $matches = $urlMatcher->match($this->getRequest()->getPathInfo());

                if (!isset($matches['_controller'])) {
                    // set default controller to this
                    $matches['_controller'] = $this;
                }

                $this->getRequest()->attributes->add($matches);
            }

            if ($this->getRequest()->attributes->has('_controller')) {
                return $this->invokeAction($type);
            }

            throw new FrontControllerException(
                sprintf(
                    'Unable to handle URL `%s`.',
                    $this->getRequest()->getHost() . '/' . $this->getRequest()->getPathInfo()
                ), FrontControllerException::NOT_FOUND
            );
        } catch (Exception $e) {
            if (false === $catch) {
                throw $e;
            }

            return $this->handleException($e, $this->getRequest(), $type);
        }
    }

    /**
     * Handles the request when none other action was found.
     *
     * @access public
     *
     * @param string $uri The URI to handle
     *
     * @throws FrontControllerException
     */
    public function defaultAction($uri = null, $sendResponse = true)
    {
        if (null === $this->application) {
            throw new FrontControllerException(
                'A valid BackBee application is required.',
                FrontControllerException::INTERNAL_ERROR
            );
        }

        if (false === $this->application->getContainer()->has('site')) {
            throw new FrontControllerException(
                'A BackBee\Site instance is required.',
                FrontControllerException::INTERNAL_ERROR
            );
        }

        $site = $this->application->getContainer()->get('site');

        preg_match('/(.*)(\.[' . $this->url_extension . ']+)/', $uri, $matches);
        if (
            (
                '_root_' !== $uri
                && '/' !== $uri[strlen($uri) - 1]
                && 0 === count($matches)
                && true === $this->force_url_extension
            ) || (0 < count($matches) && true === isset($matches[2]) && $site->getDefaultExtension() !== $matches[2])
        ) {
            throw new FrontControllerException(
                sprintf(
                    'The URL `%s` can not be found.',
                    $this->getRouteCollection()->getUri($uri)
                ), FrontControllerException::NOT_FOUND
            );
        }

        $uri = preg_replace('/(.*)\.' . $this->url_extension . '?$/i', '$1', $uri);

        $redirect_page = null !== $this->application->getRequest()->get('bb5-redirect', null)
            ? ('false' !== $this->application->getRequest()->get('bb5-redirect'))
            : true;

        if ('_root_' == $uri) {
            $page = $this->application->getEntityManager()
                ->getRepository('BackBee\NestedNode\Page')
                ->getRoot($site);
        } else {
            $page = $this->application->getEntityManager()
                ->getRepository('BackBee\NestedNode\Page')
                ->findOneBy(
                    array(
                        '_site' => $site,
                        '_url' => '/' . $uri,
                        '_state' => Page::getUndeletedStates(),
                    )
                );
        }

        if (null !== $page && false === $page->isOnline()) {
            $page = (null === $this->application->getBBUserToken()) ? null : $page;
        }

        if (null === $page) {
            throw new FrontControllerException(
                sprintf('The URL `%s` can not be found.', $this->request->getHost() . '/' . $uri),
                FrontControllerException::NOT_FOUND
            );
        }

        if ((null !== $redirect = $page->getRedirect()) && $page->getUseUrlRedirect()) {
            if ((null === $this->application->getBBUserToken()) || ((null !== $this->application->getBBUserToken(
                        )) && (true === $redirect_page))) {
                $redirect = $this->application->getRenderer()->getUri($redirect);

                $response = new RedirectResponse(
                    $redirect, 301, [
                        'Cache-Control' => 'no-store, no-cache, must-revalidate',
                        'Expires' => 'Thu, 01 Jan 1970 00:00:00 GMT',
                    ]
                );

                $this->send($response);
                $this->application->stop();
            }
        }

        try {
            $this->application->info(sprintf('Handling URL request `%s`.', $uri));

            $event = new PageFilterEvent($this, $this->application->getRequest(), self::MASTER_REQUEST, $page);
            $this->application->getEventDispatcher()->dispatch('application.page', $event);

            if (null !== $this->getRequest()->get('bb5-mode')) {
                $response = new Response(
                    $this->application->getRenderer()->render($page, $this->getRequest()->get('bb5-mode'))
                );
            } else {
                $response = new Response($this->application->getRenderer()->render($page));
            }

            if ($sendResponse) {
                $this->send($response);
            } else {
                return $response;
            }
        } catch (FrontControllerException $fe) {
            throw $fe;
        } catch (Exception $e) {
            throw new FrontControllerException(
                sprintf('An error occured while rendering URL `%s`.', $this->request->getHost() . '/' . $uri),
                FrontControllerException::INTERNAL_ERROR,
                $e
            );
        }
    }

    public function rssAction($uri = null)
    {
        if (null === $this->application) {
            throw new FrontControllerException(
                'A valid BackBee application is required.',
                FrontControllerException::INTERNAL_ERROR
            );
        }

        if (false === $this->application->getContainer()->has('site')) {
            throw new FrontControllerException(
                'A BackBee\Site instance is required.',
                FrontControllerException::INTERNAL_ERROR
            );
        }

        $site = $this->application->getContainer()->get('site');
        if (false !== $ext = strrpos($uri, '.')) {
            $uri = substr($uri, 0, $ext);
        }

        if ('_root_' == $uri) {
            $page = $this->application->getEntityManager()
                ->getRepository('BackBee\NestedNode\Page')
                ->getRoot($site);
        } else {
            $page = $this->application->getEntityManager()
                ->getRepository('BackBee\NestedNode\Page')
                ->findOneBy(
                    array(
                        '_site' => $site,
                        '_url' => '/' . $uri,
                        '_state' => Page::getUndeletedStates(),
                    )
                );
        }

        try {
            $this->application->info(sprintf('Handling URL request `rss%s`.', $uri));

            $response = new Response($this->application->getRenderer()->render($page, 'rss', null, 'rss.phtml', false));
            $response->headers->set('Content-Type', 'text/xml');
            $response->setClientTtl(15);
            $response->setTtl(15);

            $this->send($response);
        } catch (Exception $e) {
            $this->defaultAction('/rss/' . $uri);
        }
    }

    /**
     * Return the url to the provided route path.
     *
     * @param string $route_path
     *
     * @return string
     */
    public function getUrlByRoutePath($route_path)
    {
        if (null === $url = $this->getRouteCollection()->getRoutePath($route_path)) {
            $url = '/';
        }

        return $url;
    }

    /**
     * Register every valid route defined in $route_config array.
     *
     * @param mixed      $default_controller used as default controller if a route comes without any specific controller
     * @param array|null $route_config
     */
    public function registerRoutes($default_controller, array $route_config)
    {
        foreach ($route_config as $name => &$route) {
            if (false === isset($route['defaults']) || false === isset($route['defaults']['_action'])) {
                $this->getApplication()->warning("Unable to parse the action method for the route `$name`.");
                continue;
            }

            if (false === array_key_exists('_controller', $route['defaults'])) {
                $route['defaults']['_controller'] = $default_controller;
            }

            if (false === is_string($route['defaults']['_controller'])) {
                throw new FrontControllerException(
                    'Route controller must be type of string. '
                    . 'Please provide controller namespace or controller service id instead of '
                    . 'instance of `' . get_class($route['defaults']['_controller']) . '`.'
                );
            }
        }

        $router = $this->getRouteCollection();
        $router->pushRouteCollection($route_config);
    }

    /**
     * Send response to client.
     *
     * @param Response $response
     */
    public function sendResponse(Response $response)
    {
        $this->send($response);
    }

    /**
     * This method executed on shutdown after the response is sent.
     */
    public function terminate()
    {
        if (!$this->application || false === $this->application->isStarted()) {
            return;
        }

        ob_implicit_flush(true);
        flush();

        // $response may not be set
        if ($this->response instanceof Response) {
            $this->application->getEventDispatcher()->dispatch(
                KernelEvents::TERMINATE,
                new PostResponseEvent($this, $this->getRequest(), $this->response)
            );
        }
    }

    /**
     * Returns the current request context.
     *
     * @access protected
     *
     * @return RequestContext
     */
    protected function getRequestContext()
    {
        if (null === $this->requestContext) {
            $this->requestContext = new RequestContext();
            $this->requestContext->fromRequest($this->getRequest());
        }

        return $this->requestContext;
    }

    /**
     * Dispatches GetResponseEvent.
     *
     * @access private
     *
     * @param string  $eventName        The name of the event to dispatch
     * @param integer $type             The type of the request
     *                                  (one of HttpKernelInterface::MASTER_REQUEST or HttpKernelInterface::SUB_REQUEST)
     * @param boolean $stopWithResponse Send response if TRUE and response exists
     */
    private function dispatch($eventName, $controller = null, $type = self::MASTER_REQUEST, $stopWithResponse = true)
    {
        if (null === $this->application) {
            return;
        }

        if (null !== $this->application->getEventDispatcher()) {
            $event = new GetResponseEvent(null === $controller ? $this : $controller, $this->getRequest(), $type);
            $this->application->getEventDispatcher()->dispatch($eventName, $event);

            if ($stopWithResponse && $event->hasResponse()) {
                $this->send($event->getResponse());
            }
        }
    }

    /**
     * Dispatch FilterResponseEvent then send response.
     *
     * @acces private
     *
     * @param Response $response The response to filter then send
     * @param integer  $type     The type of the request
     *                           (one of HttpKernelInterface::MASTER_REQUEST or HttpKernelInterface::SUB_REQUEST)
     */
    private function send(Response $response, $type = self::MASTER_REQUEST)
    {
        if (null !== $this->application && null !== $this->application->getEventDispatcher()) {
            $event = new FilterResponseEvent($this, $this->getRequest(), $type, $response);
            $this->application->getEventDispatcher()->dispatch('frontcontroller.response', $event);
            $this->application->getEventDispatcher()->dispatch(KernelEvents::RESPONSE, $event);
        }

        $response->send();
        $this->response = $response;
        $this->application->stop();
    }

    /**
     * Invokes associated action to the current request.
     *
     * @access private
     *
     * @param int $type request type
     *
     * @throws FrontControllerException
     */
    protected function invokeAction($type = self::MASTER_REQUEST)
    {
        $this->dispatch('frontcontroller.request');

        $request = $this->getRequest();
        $controllerResolver = $this->getApplication()->getContainer()->get('controller_resolver');
        $controller = $controllerResolver->getController($request);

        // logout Event dispatch
        if (
            null !== $request->get('logout')
            && true === $request->get('logout')
            && true === $this->getApplication()->getSecurityContext()->isGranted('IS_AUTHENTICATED_FULLY')
        ) {
            $this->dispatch('frontcontroller.request.logout');
        }

        if (null !== $controller) {
            $dispatcher = $this->application->getEventDispatcher();

            // dispatch kernel.controller event
            $event = new FilterControllerEvent($this, $controller, $request, $type);
            $dispatcher->dispatch(KernelEvents::CONTROLLER, $event);

            // a listener could have changed the controller
            $controller = $event->getController();

            $eventName = $this->getControllerActionEventName($controller[0], $request->attributes->get('_action'));
            $dispatcher->dispatch($eventName . '.precall', new Event\PreRequestEvent($request));

            // get controller action arguments
            $actionArguments = $controllerResolver->getArguments($request, $controller);

            $response = call_user_func_array($controller, $actionArguments);
            if (!($response instanceof Response)) {
                $this->application->warning(
                    sprintf(
                        'Action %s from controller %s should return an object Response, got %s.',
                        $request->attributes->get('_action'),
                        get_class($controller[0]),
                        gettype($response)
                    )
                );

                $response = new Response();
            }
            $dispatcher->dispatch($eventName . '.postcall', new Event\PostResponseEvent($response, $request));

            return $response;
        } else {
            throw new FrontControllerException(
                sprintf('Unknown action `%s`.', $request->attributes->get('_action')),
                FrontControllerException::BAD_REQUEST
            );
        }
    }

    /**
     * Handles an exception by trying to convert it to a Response.
     *
     * @param Exception $e       An \Exception instance
     * @param Request   $request A Request instance
     * @param integer   $type    The type of the request
     *
     * @return Response A Response instance
     */
    private function handleException(Exception $exception, Request $request, $type)
    {
        $event = new GetResponseForExceptionEvent($this, $request, $type, $exception);
        $this->application->getEventDispatcher()->dispatch(KernelEvents::EXCEPTION, $event);

        return $event->getResponse();
    }

    /**
     * Builds and returns controller's action event name.
     *
     * @param object $controller
     * @param string $actionName
     *
     * @return string
     *
     * @throws InvalidArgumentException if provided controller is not an object
     */
    private function getControllerActionEventName($controller, $actionName)
    {
        if (!is_object($controller)) {
            throw new InvalidArgumentException(
                'Controller must be type of object, ' . gettype($controller) . ' given.'
            );
        }

        $eventName = str_replace('\\', '.', strtolower(get_class($controller)));

        if (0 === strpos($eventName, 'backbee.')) {
            $eventName = str_replace('backbee.', '', $eventName);
        }

        if (0 === strpos($eventName, 'frontcontroller.')) {
            $eventName = str_replace('frontcontroller.', '', $eventName);
        }

        return $eventName . '.' . strtolower($actionName);
    }
}
