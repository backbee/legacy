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

namespace BackBee\Renderer\Event;

use BackBee\Event\Event;

/**
 * A generic class of event in BB application.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      e.chau <eric.chau@lp-digital.fr>
 */
class RendererEvent extends Event
{
    /**
     * @var BackBee\Renderer\Renderer
     */
    private $renderer;

    /**
     * @var string
     */
    private $render;

    /**
     * Create an instance of RendererEvent.
     *
     * @param mixed $target
     * @param mixed $arguments
     */
    public function __construct($target, $arguments = null)
    {
        parent::__construct($target, $arguments);

        $this->render = null;
        if (is_array($arguments)) {
            $this->renderer = &$arguments[0];
            $this->render = &$arguments[1];
        } else {
            $this->renderer = &$arguments;
        }
    }

    /**
     * Getter of current event renderer object.
     *
     * @return BackBee\Renderer\Renderer
     */
    public function getRenderer()
    {
        return $this->renderer;
    }

    /**
     * Returns render string if it is setted, else null.
     *
     * @return string|null
     */
    public function getRender()
    {
        return $this->render;
    }
}
