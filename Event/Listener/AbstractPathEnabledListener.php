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

namespace BackBee\Event\Listener;

use Symfony\Component\HttpFoundation\Request;

/**
 * Abstract listener implementing PathEnabledListenerInterface
 *
 * @category    BackBee
 *
 *
 * @author      k.golovin
 */
abstract class AbstractPathEnabledListener implements PathEnabledListenerInterface
{
    protected $path;
    protected $request;
    /**
     * @param $path - route path for which this listener will be enabled
     */
    public function setPath($path)
    {
        $this->path = $path;
    }

    /**
     * @param Request $request
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;
    }

    /**
     * @param Request $request
     *
     * @return boolean - true if the listener should be enabled for the $request
     */
    public function isEnabled(Request $request = null)
    {
        if (null === $this->path) {
            return true;
        }

        if (null === $request) {
            $request = $this->request;
        }

        // skip if route does not match
        if (0 === strpos($request->getPathInfo(), $this->path)) {
            return true;
        }

        return false;
    }
}
