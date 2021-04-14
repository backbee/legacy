<?php

/*
 * Copyright (c) 2011-2015 Lp digital system
 *
 * This file is part of BackBee.
 *
 * BackBee is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * BackBee is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with BackBee. If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Charles Rouillon <charles.rouillon@lp-digital.fr>
 */

namespace BackBee\Tests\Mock;

use BackBee\ApplicationInterface;
use BackBee\AutoLoader\AutoLoader;
use BackBee\BBApplication;
use BackBee\Config\Config;
use BackBee\Console\Console;
use BackBee\Controller\FrontController;
use BackBee\DependencyInjection\ContainerInterface;
use BackBee\Routing\RouteCollection;
use BackBee\Site\Site;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Kernel;

/**
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      e.chau <eric.chau@lp-digital.fr>
 */
class ManualBBApplication extends Kernel implements ApplicationInterface
{
    /**
     * @var boolean
     */
    protected $is_started;

    /**
     * @var string
     */
    protected $context;

    /**
     * @var string
     */
    protected $environment;

    /**
     * @var string
     */
    protected $bb_dir;

    /**
     * @var string
     */
    protected $base_dir;

    /**
     * @var string
     */
    protected $base_repository;

    /**
     * @var string
     */
    protected $repository;

    /**
     * @var string
     */
    protected $config_dir;

    /**
     * @var boolean
     */
    protected $overrided_config;

    /**
     * @var boolean
     */
    protected $debug_mode;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Site
     */
    protected $site;

    /**
     * @var boolean
     */
    protected $isClientSAPI;

    /**
     * @var string[]
     */
    protected $resourceDir = [];

    /**
     * @var string[]
     */
    protected $classcontentDir = [];

    /**
     * ManualBBApplication's constructor.
     */
    public function __construct($context = null, $environment = null)
    {
        $this->is_started = false;
        $this->isClientSAPI = false;
        $this->context = null === $context ? BBApplication::DEFAULT_CONTEXT : $context;
        $this->environment = null === $environment ? BBApplication::DEFAULT_ENVIRONMENT : $environment;
        $this->overrided_config = false;
    }

    /**
     * __call allow us to catch everytime user wanted to set a value for a protected attribute;.
     *
     * @param string $method
     * @param array  $arguments
     */
    public function __call($method, $arguments)
    {
        if (1 === preg_match('#^set([a-zA-Z_]+)$#', $method, $matches) && 0 < count($matches)) {
            $property = strtolower($matches[1]);
            if (true === property_exists('BackBee\Tests\Mock\ManualBBApplication', $property)) {
                $this->$property = array_shift($arguments);
            }
        }
    }

    /**
     * @param Site $site
     */
    public function start(Site $site = null)
    {
        return true === $this->is_started;
    }

    /**
     * Stop the current BBApplication instance.
     */
    public function stop()
    {
        return false === $this->is_started;
    }

    /**
     * {@inheritDoc}
     */
    public function getContext(): string
    {
        return $this->context;
    }

    /**
     * Returns the starting context.
     *
     * @return string|NULL
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * {@inheritDoc}
     */
    public function getBBDir(): string
    {
        return $this->bb_dir;
    }

    /**
     * {@inheritDoc}
     */
    public function getBaseDir(): string
    {
        return $this->base_dir;
    }

    /**
     * {@inheritDoc}
     */
    public function getBaseRepository(): string
    {
        return $this->base_repository;
    }

    /**
     * {@inheritDoc}
     */
    public function getRepository(): string
    {
        return $this->repository;
    }

    /**
     * {@inheritDoc}
     */
    public function getConfigDir(): string
    {
        return $this->config_dir;
    }

    /**
     * {@inheritDoc}
     */
    public function getResourceDir(): array
    {
        return $this->resourceDir;
    }

    /**
     * {@inheritDoc}
     */
    public function getClassContentDir(): array
    {
        return $this->classcontentDir;
    }

    /**
     * {@inheritDoc}
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     *
     */
    public function registerCommands(Console $console)
    {
    }

    /**
     * @return boolean
     */
    public function isOverridedConfig(): bool
    {
        return $this->overrided_config;
    }

    /**
     * @return boolean
     */
    public function isDebugMode()
    {
        return $this->debug_mode;
    }

    /**
     * {@inheritDoc}
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * {@inheritDoc}
     */
    public function getSite(): ?Site
    {
        return $this->site;
    }

    /**
     * {@inheritDoc}
     */
    public function isStarted(): bool
    {
        return $this->is_started;
    }

    /**
     * {@inheritDoc}
     */
    public function isClientSAPI(): bool
    {
        return $this->isClientSAPI;
    }

    /**
     * {@inheritDoc}
     */
    public function getDataDir(): string
    {
        return '';
    }

    /**
     * {@inheritDoc}
     */
    public function getController(): FrontController
    {
        return $this->getContainer()->get('controller');
    }

    /**
     * {@inheritDoc}
     */
    public function getRouting(): RouteCollection
    {
        return $this->getContainer()->get('routing');
    }

    /**
     * {@inheritDoc}
     */
    public function getAutoloader(): AutoLoader
    {
        return $this->getContainer()->get('autoloader');
    }

    /**
     * {@inheritDoc}
     */
    public function getKernelBundles(): array
    {
        // TODO: Implement getKernelBundles() method.

        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function registerBundles()
    {
        // TODO: Implement registerBundles() method.
    }

    /**
     * {@inheritDoc}
     */
    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        // TODO: Implement registerContainerConfiguration() method.
    }
}
