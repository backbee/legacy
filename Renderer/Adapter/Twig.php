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

namespace BackBee\Renderer\Adapter;

use BackBee\Controller\Exception\FrontControllerException;
use BackBee\Renderer\AbstractRenderer;
use BackBee\Renderer\AbstractRendererAdapter;
use BackBee\Renderer\Exception\RendererException;

/**
 * twig renderer adapter for BackBee\Renderer\Renderer.
 *
 * @category    BackBee
 *
 *
 * @author      e.chau <eric.chau@lp-digital.fr>
 */
class Twig extends AbstractRendererAdapter
{
    /**
     * Extensions to include in searching file.
     *
     * @var array
     */
    protected $extensions = [
        '.twig',
        '.html.twig',
        '.xml.twig',
    ];

    /**
     * @var \Twig_Environment
     */
    protected $twig;

    /**
     * @var TwigLoaderFilesystem
     */
    private $loader;

    /**
     * Twig adapter constructor.
     *
     * @param AbstractRenderer $renderer
     */
    public function __construct(AbstractRenderer $renderer, array $config = [])
    {
        parent::__construct($renderer, $config);

        $this->loader = new TwigLoaderFilesystem([]);
        $this->twig = new \Twig_Environment($this->loader, $config);

        $app = $this->renderer->getApplication();
        if ($app->isDebugMode() || (isset($config['debug']) && true === $config['debug'])) {
            $this->twig->enableDebug();
            $this->twig->addExtension(new \Twig_Extension_Debug());
        }

        if (isset($config['enable_autoreload']) && true === $config['enable_autoreload']) {
            $this->twig->enableAutoReload();
        }

        if (isset($config['enable_cache']) && true === $config['enable_cache']) {
            $cacheDir = isset($config['cache_dir']) && is_string($config['cache_dir'])
                ? $config['cache_dir']
                : $app->getCacheDir() . DIRECTORY_SEPARATOR . 'twig'
            ;
            $this->setTwigCache($cacheDir);
        }
    }

    /**
     * Add Twig extension.
     *
     * @param \Twig_ExtensionInterface $extension
     */
    public function addExtension(\Twig_ExtensionInterface $extension)
    {
        $this->twig->addExtension($extension);
    }

    /**
     * Set Twig cache directory.
     *
     * @param string $cacheDir
     */
    public function setTwigCache($cacheDir)
    {
        if (!is_dir($cacheDir) && (!is_writable(dirname($cacheDir)) || false === @mkdir($cacheDir, 0775))) {
            throw new RendererException(
                sprintf('Unable to create twig cache "%s"', $cacheDir),
                RendererException::RENDERING_ERROR
            );
        }

        if (!is_writable($cacheDir)) {
            throw new RendererException(
                sprintf('Twig cache "%s" is not writable', $cacheDir),
                RendererException::RENDERING_ERROR
            );
        }

        $this->twig->setCache($cacheDir);
    }

    /**
     * @see BackBee\Renderer\RendererAdapterInterface::getManagedFileExtensions()
     */
    public function getManagedFileExtensions()
    {
        return $this->extensions;
    }

    /**
     * Check if $filename exists in directories provided by $templateDir.
     *
     * @param string $filename
     * @param array  $templateDir
     *
     * @return boolean true if the file was found and is readable
     */
    public function isValidTemplateFile($filename, array $templateDir)
    {
        if (0 === count($templateDir)) {
            return false;
        }

        $this->addDirPathIntoLoaderIfNotExists($templateDir);

        return $this->loader->exists($filename);
    }

    /**
     * Add dir path into loader only if it not already exists.
     *
     * @param array $templateDir
     */
    private function addDirPathIntoLoaderIfNotExists(array $templateDir)
    {
        $paths = $this->loader->getPaths();
        if ((count($paths) !== count($templateDir)) || (0 < count(array_diff($paths, $templateDir)))) {
            $this->loader->removeAllPaths();
            try {
                $this->loader->setPaths($templateDir);
            } catch (\Twig_Error_Loader $e) {}
        }
    }

    /**
     * Generate the render of $filename template with $params and $vars.
     *
     * @param string $filename
     * @param array  $templateDir
     * @param array  $params
     * @param array  $vars
     *
     * @return string
     */
    public function renderTemplate($filename, array $templateDir, array $params = [], array $vars = [])
    {
        $this->addDirPathIntoLoaderIfNotExists($templateDir);
        $render = '';
        try {
            $params['this'] = $this;
            $params = array_merge($params, $vars);

            try {
                $render = $this->twig->render($filename, $params);
            } catch (\Twig_Error_Runtime $e) {
                if($e->getPrevious() instanceof FrontControllerException) {
                    throw $e->getPrevious();
                }

                throw $e;
            }

        } catch (FrontControllerException $fe) {
            throw $fe;
        } catch (\Exception $e) {
            throw new RendererException(
                sprintf('%s in %s.', $e->getMessage(), $filename),
                RendererException::RENDERING_ERROR,
                $e
            );
        }

        return $render;
    }

    /**
     * @param string $filename
     *
     * @return \Twig_TemplateInterface
     */
    public function loadTemplate($filename)
    {
        return $this->twig->loadTemplate($filename);
    }
}
