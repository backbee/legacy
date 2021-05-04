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

use Symfony\Component\HttpFoundation\Request;

/**
 * Interface for listeners that are enabled for a certain request path only.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      k.golovin
 */
interface PathEnabledListenerInterface
{
    /**
     * @param $path - route path for which this listener will be enabled
     */
    public function setPath($path);

    /**
     * @param Request $request
     *
     * @return boolean - true if the listener should be enabled for the $request
     */
    public function isEnabled(Request $request = null);
}
