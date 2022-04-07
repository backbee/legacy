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

namespace BackBee\DependencyInjection\Util;

use BackBee\ApplicationInterface;
use BackBee\DependencyInjection\Container;
use BackBee\DependencyInjection\ContainerBuilder;
use Exception;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\Loader\ClosureLoader;
use Symfony\Component\DependencyInjection\Loader\IniFileLoader;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Class ServiceLoader
 *
 * Allows to easily load services into container from yml or xml file.
 *
 * @package BackBee\DependencyInjection\Util
 *
 * @author  e.chau <eric.chau@lp-digital.fr>
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class ServiceLoader
{
    /**
     * Load services from yaml file into your container.
     *
     * @param Container    $container        the container we want to load services into
     * @param string|array $dir              directory (or directories) in where we can find services files
     * @param string|null  $service_filename define the service's filename we want to load,
     *                                       default: ContainerBuilder::SERVICE_FILENAME
     */
    public static function loadServicesFromYamlFile(Container $container, $dir, $service_filename = null): void
    {
        try {
            if (null === $service_filename) {
                $service_filename = ContainerBuilder::SERVICE_FILENAME;
            }

            (new YamlFileLoader($container, new FileLocator((array)$dir)))->load($service_filename . '.yml');
        } catch (Exception $exception) {
            $container->get('backbee.logger')->error(
                sprintf(
                    '%s : %s : %s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }
    }

    /**
     * Load services from xml file into your container.
     *
     * @param Container    $container        the container we want to load services into
     * @param string|array $dir              directory (or directories) in where we can find services files
     * @param string|null  $service_filename define the service's filename we want to load,
     *                                       default: ContainerBuilder::SERVICE_FILENAME
     */
    public static function loadServicesFromXmlFile(Container $container, $dir, $service_filename = null): void
    {
        try {
            if (null === $service_filename) {
                $service_filename = ContainerBuilder::SERVICE_FILENAME;
            }

            (new XmlFileLoader($container, new FileLocator((array)$dir)))->load($service_filename . '.xml');
        } catch (Exception $exception) {
            $container->get('backbee.logger')->error(
                sprintf(
                    '%s : %s : %s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }
    }

    /**
     * Returns a loader for the container.
     *
     * @return DelegatingLoader The loader
     */
    public static function getContainerLoader(
        ApplicationInterface $application,
        Container $container
    ): DelegatingLoader {
        $locator = new FileLocator($application);
        $resolver = new LoaderResolver(
            [
                new XmlFileLoader($container, $locator),
                new YamlFileLoader($container, $locator),
                new IniFileLoader($container, $locator),
                new PhpFileLoader($container, $locator),
                new ClosureLoader($container),
            ]
        );

        return new DelegatingLoader($resolver);
    }
}
