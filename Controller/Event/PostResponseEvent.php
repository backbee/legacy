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
use Symfony\Component\HttpFoundation\Response;

/**
 * Class PostResponseEvent
 *
 * Allows to execute logic before call of current request controller's action.
 *
 * @package BackBee\Controller\Event
 *
 * @author  e.chau <eric.chau@lp-digital.fr>
 */
class PostResponseEvent extends Event
{
    /**
     * @var Request
     */
    private $request;

    /**
     * Create a new instance of PostResponseEvent.
     *
     * @param Response $response
     * @param Request  $request
     */
    public function __construct(Response $response, Request $request)
    {
        parent::__construct($response, $request);

        $this->request = $request;
    }

    /**
     * Response's getter.
     *
     * @return Response
     */
    public function getResponse(): Response
    {
        return $this->getTarget();
    }

    /**
     * Request's getter.
     *
     * @return Request
     */
    public function getRequest(): Request
    {
        return $this->request;
    }
}
