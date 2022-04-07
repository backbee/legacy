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

namespace BackBee\Routing\Matcher;

use Symfony\Component\Routing\Matcher\UrlMatcher as sfUrlMatcher;
use Symfony\Component\Routing\Route;
use BackBee\Util\File\File;

/**
 * @category    BackBee
 *
 * 
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class UrlMatcher extends sfUrlMatcher
{
    /**
     * Handles specific route requirements.
     *
     * @param string $pathinfo The path
     * @param string $name     The route name
     * @param string $route    The route
     *
     * @return array The first element represents the status, the second contains additional information
     */
    protected function handleRouteRequirements($pathinfo, $name, Route $route)
    {
        $pathinfo = File::normalizePath($pathinfo, '/', false);
        $status = parent::handleRouteRequirements($pathinfo, $name, $route);

        if (self::REQUIREMENT_MATCH == $status[0] && 0 < count($route->getRequirements('HTTP-'))) {
            if (null === $request = $this->getContext()->getRequest()) {
                return array(self::REQUIREMENT_MISMATCH, null);
            }

            $requestMatcher = new RequestMatcher(null, null, null, null, array(), $route->getRequirements('HTTP-'));
            $status = array($requestMatcher->matches($request) ? self::REQUIREMENT_MATCH : self::REQUIREMENT_MISMATCH, null);
        }

        return $status;
    }

    /**
     * Tries to match a URL with a set of routes.
     *
     * @param string $pathinfo The path info to be parsed (raw format, i.e. not urldecoded)
     *
     * @return array An array of parameters
     *
     * @throws ResourceNotFoundException If the resource could not be found
     * @throws MethodNotAllowedException If the resource was found but the request method is not allowed
     */
    public function match($pathinfo)
    {
        $pathinfo = File::normalizePath($pathinfo, '/', false);

        return parent::match($pathinfo);
    }
}
