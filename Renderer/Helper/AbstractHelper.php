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

namespace BackBee\Renderer\Helper;

use BackBee\Renderer\AbstractRenderer;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @category  BackBee
 *
 * @copyright Lp digital system
 *
 * @author    c.rouillon <charles.rouillon@lp-digital.fr>
 * @author    Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
abstract class AbstractHelper
{
    /**
     * @var \BackBee\Renderer\AbstractRenderer
     */
    protected $_renderer;

    /**
     * Class constructor.
     *
     * @param \BackBee\Renderer\AbstractRenderer $renderer
     */
    public function __construct(AbstractRenderer $renderer)
    {
        $this->setRenderer($renderer);
    }

    /**
     * Set the renderer.
     *
     * @param \BackBee\Renderer\AbstractRenderer $renderer
     *
     * @return \BackBee\Renderer\Helper\AbstractHelper
     */
    public function setRenderer(AbstractRenderer $renderer): AbstractHelper
    {
        $this->_renderer = $renderer;

        return $this;
    }

    /**
     * Get renderer.
     *
     * @return \BackBee\Renderer\AbstractRenderer
     */
    public function getRenderer(): AbstractRenderer
    {
        return $this->_renderer;
    }

    /**
     * Get container.
     *
     * @return \Symfony\Component\DependencyInjection\ContainerInterface
     */
    public function getContainer(): ContainerInterface
    {
        return $this->_renderer->getApplication()->getContainer();
    }
}
