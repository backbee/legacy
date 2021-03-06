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

namespace BackBee\Renderer;

/**
 * Interface for the object that can be rendered.
 *
 * @category    BackBee
 *
 * 
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
interface RenderableInterface
{
    /**
     * Returns data associated to $var for rendering assignation, all data if NULL provided.
     *
     * @param string $var
     *
     * @return string|array|null
     */
    public function getData($var = null);

    /**
     * Returns parameters associated to $var for rendering assignation, all data if NULL provided.
     *
     * @param string $var
     *
     * @return string|array|null
     */
    public function getParam($var);

    /**
     * Returns TRUE if the object can be rendered.
     *
     * @return Boolean
     */
    public function isRenderable();

    /**
     * Returns return the entity template name.
     *
     * @return string
     */
    public function getTemplateName();
}
