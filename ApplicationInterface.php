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

namespace BackBee;

use BackBee\AutoLoader\AutoLoader;
use BackBee\Config\Config;
use BackBee\Console\Console;
use BackBee\Controller\FrontController;
use BackBee\DependencyInjection\ContainerInterface;
use BackBee\Routing\RouteCollection;
use BackBee\Site\Site;
use Psr\Log\LoggerInterface;

/**
 * Interface ApplicationInterface
 *
 * @package BackBee
 *
 * @author c.rouillon <charles.rouillon@lp-digital.fr>
 * @author e.chau <eric.chau@lp-digital.fr>
 */
interface ApplicationInterface
{
    public const DEFAULT_CONTEXT = 'default';
    public const DEFAULT_ENVIRONMENT = '';

    /**
     * @param Site|null $site
     */
    public function start(Site $site = null);

    /**
     * @return boolean
     */
    public function isStarted(): bool;

    /**
     * Stop the current BBApplication instance.
     */
    public function stop();

    /**
     * Returns the starting context.
     *
     * @return string
     */
    public function getContext(): string;

    /**
     * Returns the starting context.
     *
     * @return string
     */
    public function getEnvironment();

    /**
     * @return string
     */
    public function getBBDir(): string;

    /**
     * @return string
     */
    public function getBaseDir(): string;

    /**
     * Get default repository directory path.
     *
     * @return string
     */
    public function getBaseRepository(): string;

    /**
     * Get current repository directory path.
     *
     * @return string
     */
    public function getRepository(): string;

    /**
     * @return Config
     */
    public function getConfig(): Config;

    /**
     * @return string
     */
    public function getConfigDir(): string;

    /**
     * Returns path to Data directory.
     *
     * @return string absolute path to Data directory
     */
    public function getDataDir(): string;

    /**
     * Return the resource directories, if undefined, initialized with common resources.
     *
     * @return string[] The resource directories.
     */
    public function getResourceDir(): array;

    /**
     * Return the class content repositories path for this instance.
     *
     * @return string[] The class contents directories
     */
    public function getClassContentDir(): array;

    /**
     * Gets the cache directory.
     *
     * @return string The cache directory
     */
    public function getCacheDir(): string;

    /**
     * Gets the log directory.
     *
     * @return string The log directory
     */
    public function getLogDir(): string;

    /**
     * @return FrontController
     */
    public function getController(): FrontController;

    /**
     * @return RouteCollection
     */
    public function getRouting(): RouteCollection;

    /**
     * @return AutoLoader
     */
    public function getAutoloader(): AutoLoader;

    /**
     * Get container.
     *
     * @return ContainerInterface
     */
    public function getContainer();

    /**
     * @return bool
     */
    public function isOverridedConfig(): bool;

    /**
     * @return bool
     */
    public function isDebugMode();

    /**
     * @return null|Site
     */
    public function getSite(): ?Site;

    /**
     * @return LoggerInterface
     */
    public function getLogging(): LoggerInterface;

    /**
     * Is the BackBee application started as SAPI client?
     *
     * @return bool Returns true is application started as SAPI client, false otherwise
     */
    public function isClientSAPI(): bool;

    /**
     * Register commands.
     *
     * @param Console $console
     *
     * @return mixed
     */
    public function registerCommands(Console $console);

    /**
     * Get kernel bundles.
     *
     * @return array
     */
    public function getKernelBundles(): array;
}
