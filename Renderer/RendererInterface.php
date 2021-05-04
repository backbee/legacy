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

namespace BackBee\Renderer;

use BackBee\Site\Layout;

/**
 * Interface for the templates renderers.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
interface RendererInterface
{
    public function assign($var, $value = null);

    public function getAssignedVars();

    public function render(RenderableInterface $content = null, $mode = null, $params = null, $template = null);

    public function partial($template = null, $params = null);

    public function error($error_code, $title = null, $message = null, $trace = null);

    public function updateLayout(Layout $layout);

    public function removeLayout(Layout $layout);
}
