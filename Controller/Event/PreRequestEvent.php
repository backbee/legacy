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

namespace BackBee\Controller\Event;

use BackBee\Event\Event;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class PreRequestEvent
 *
 * Allows to execute logic after call of current request controller's action
 * and before the response is send.
 *
 * @package BackBee\Controller\Event
 *
 * @author  e.chau <eric.chau@lp-digital.fr>
 */
class PreRequestEvent extends Event
{
    /**
     * Create a new instance of PreRequestEvent.
     *
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        parent::__construct($request);
    }

    /**
     * Request getter.
     *
     * @return Request
     */
    public function getRequest(): Request
    {
        return $this->getTarget();
    }
}
