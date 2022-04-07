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

namespace BackBee\Cache\IdentifierAppender;

use BackBee\Renderer\RendererInterface;

/**
 * Every cache identifier appender must implements this interface to be usable.
 *
 * @category    BackBee
 *
 * 
 * @author      e.chau <eric.chau@lp-digital.fr>
 */
interface IdentifierAppenderInterface
{
    /**
     * This method allows every identifier appender to customize cache identifier with its own logic.
     *
     * @param string    $identifier the identifier to update if needed
     * @param RendererInterface $renderer   the current renderer, can be null
     *
     * @return string return the new identifier
     */
    public function computeIdentifier($identifier, RendererInterface $renderer = null);

    /**
     * Returns every group name this appender is associated with.
     *
     * @return array
     */
    public function getGroups();
}
