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

namespace BackBee\Renderer\Helper;

/**
 * @category    BackBee
 *
 * @author      Nicolas Dufreche <nicolas.dufreche@lp-digital.fr>
 */
class bundleAdminLink extends bundleAdminUrl
{

    /**
     * @param  string   $route      route is composed by the bundle, controller and action name separated by a dot
     * @param  array    $query      optional url parameters and query parameters
     * @param  string   $httpMethod http method
     *
     * @return string               url
     */
    public function __invoke($route, Array $query = [], $httpMethod = 'GET')
    {
        $url = parent::__invoke($route, $query);

        return 'data-bundle="link" href="'.$url.'" data-http-method="'.$this->getJsMethod($httpMethod).'"';
    }
}
