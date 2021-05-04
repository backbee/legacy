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

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;
use BackBee\ApplicationInterface;

/**
 * This implementation uses the '_controller' request attribute to determine
 * the controller to execute and uses the request attributes to determine
 * the controller method arguments.
 *
 * @category    BackBee
 *
 * @copyright   Lp system
 * @author      k.golovin
 */
class ControllerResolver implements ControllerResolverInterface
{
    /**
     * @var ApplicationInterface
     */
    protected $bbapp;

    /**
     * Constructor.
     *
     * @param \BackBee\ApplicationInterface $bbapp
     */
    public function __construct(ApplicationInterface $bbapp = null)
    {
        if (null !== $bbapp) {
            $this->bbapp = $bbapp;
        }
    }

    /**
     * Returns the Controller instance associated with a Request.
     *
     * This method looks for a '_controller' request attribute that represents
     * the controller name (a string like ClassName::MethodName).
     *
     * @param Request $request A Request instance
     *
     * @return mixed|Boolean A PHP callable representing the Controller,
     *                       or false if this resolver is not able to determine the controller
     *
     * @throws \InvalidArgumentException|\LogicException If the controller can't be found
     *
     * @api
     */
    public function getController(Request $request)
    {
        if (!$controller = $request->attributes->get('_controller')) {
            if (null !== $this->bbapp) {
                $this->bbapp->getLogging()->warning('Unable to look for the controller as the "_controller" parameter is missing');
            }

            return false;
        }

        if (is_array($controller)) {
            return $controller;
        }

        if (is_object($controller)) {
            if ($request->attributes->has('_action')) {
                return array($controller, $request->attributes->get('_action'));
            }
        }

        list($controller, $method) = $this->createController($controller, $request->attributes->get('_action'));

        if (!method_exists($controller, $method)) {
            throw new \InvalidArgumentException(sprintf('Method "%s::%s" does not exist.', get_class($controller), $method));
        }

        return array($controller, $method);
    }

    /**
     * @param string $controllerName
     * @param string $actionName
     */
    protected function createController($controllerName, $actionName = null)
    {
        $controllerClass = null;
        if (null === $actionName) {
            // support for ControllerClass::methodName notation
            if (false !== strpos($controllerName, '::')) {
                list($controllerClass, $actionName) = explode('::', $controllerName, 2);
            } else {
                throw new \LogicException(sprintf('Unable to extract controller action from "%s".', $controllerName));
            }
        } else {
            $controllerClass = $controllerName;
        }

        if (null === $controllerClass) {
            throw new \InvalidArgumentException(sprintf('Controller class couldn\'t be resolved for "%s".', $controllerName));
        }

        if (class_exists($controllerClass)) {
            $controller = new $controllerClass();
            if ($controller instanceof ContainerAwareInterface) {
                $controller->setContainer($this->bbapp->getContainer());
            }
        } else {
            // support for service id
            $controller = $this->bbapp->getContainer()->get($controllerClass);
        }

        return array($controller, $actionName);
    }

    /**
     * Returns the arguments to pass to the controller.
     *
     * @param Request $request    A Request instance
     * @param mixed   $controller A PHP callable
     *
     * @return array
     *
     * @throws \RuntimeException When value for argument given is not provided
     *
     * @api
     */
    public function getArguments(Request $request, $controller)
    {
        if (is_array($controller)) {
            $r = new \ReflectionMethod($controller[0], $controller[1]);
        } elseif (is_object($controller) && !$controller instanceof \Closure) {
            $r = new \ReflectionObject($controller);
            $r = $r->getMethod('__invoke');
        } else {
            $r = new \ReflectionFunction($controller);
        }

        return $this->doGetArguments($request, $controller, $r->getParameters());
    }

    protected function doGetArguments(Request $request, $controller, array $parameters)
    {
        $attributes = $request->attributes->all();
        $arguments = array();
        foreach ($parameters as $param) {
            if (array_key_exists($param->name, $attributes)) {
                $arguments[] = $attributes[$param->name];
            } elseif ($param->getClass() && $param->getClass()->isInstance($request)) {
                $arguments[] = $request;
            } elseif ($param->isDefaultValueAvailable()) {
                $arguments[] = $param->getDefaultValue();
            } else {
                if (is_array($controller)) {
                    $repr = sprintf('%s::%s()', get_class($controller[0]), $controller[1]);
                } elseif (is_object($controller)) {
                    $repr = get_class($controller);
                } else {
                    $repr = $controller;
                }

                throw new \RuntimeException(sprintf('Controller "%s" requires that you provide a value for the "$%s" argument (because there is no default value or because there is a non optional argument after this one).', $repr, $param->name));
            }
        }

        return $arguments;
    }
}
