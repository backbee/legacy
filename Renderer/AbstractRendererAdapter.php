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

use BackBee\Renderer\Helper\HelperManager;
use BackBee\Site\Layout;

/**
 * abstract class for renderer adapter.
 *
 * @category    BackBee
 *
 *
 * @author      e.chau <eric.chau@lp-digital.fr>
 */
abstract class AbstractRendererAdapter implements RendererAdapterInterface
{
    /**
     * @var AbstractRenderer
     */
    protected $renderer;

    /**
     * Constructor.
     *
     * @param AbstractRenderer $renderer
     */
    public function __construct(AbstractRenderer $renderer, array $config = [])
    {
        $this->renderer = $renderer;
    }

    /**
     * Magic call method; allow current object to forward unknow method to
     * its associated renderer.
     *
     * @param string $method
     * @param array  $argv
     *
     * @return mixed
     */
    public function __call($method, $argv)
    {
        return call_user_func_array(array($this->renderer, $method), $argv);
    }

    /**
     * @param HelperManager $helperManager [description]
     */
    public function setRenderer(AbstractRenderer $renderer)
    {
        $this->renderer = $renderer;
    }

    /**
     * @see BackBee\Renderer\RendererAdapterInterface::getManagedFileExtensions()
     */
    public function getManagedFileExtensions()
    {
        return array();
    }

    /**
     * @see BackBee\Renderer\RendererAdapterInterface::isValidTemplateFile()
     */
    public function isValidTemplateFile($filename, array $templateDir)
    {
        return false;
    }

    /**
     * @see BackBee\Renderer\RendererAdapterInterface::renderTemplate()
     */
    public function renderTemplate($filename, array $templateDir, array $params = array(), array $vars = array())
    {
        return '';
    }

    /**
     * @see BackBee\Renderer\RendererAdapterInterface::updateLayout()
     */
    public function updateLayout(Layout $layout, $layoutFile)
    {
    }

    /**
     * @see BackBee\Renderer\RendererAdapterInterface::onNewRenderer()
     */
    public function onNewRenderer(AbstractRenderer $renderer)
    {
        $this->setRenderer($renderer);
    }

    /**
     * @see BackBee\Renderer\RendererAdapterInterface::onRestorePreviousRenderer()
     */
    public function onRestorePreviousRenderer(AbstractRenderer $renderer)
    {
        $this->setRenderer($renderer);
    }
}
