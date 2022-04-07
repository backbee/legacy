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

namespace BackBee\Bundle;

use BackBee\Bundle\Exception\BundleConfigurationException;
use BackBee\ApplicationInterface;

/**
 * @category    BackBee
 *
 * @author      Nicolas Dufreche <nicolas.dufreche@lp-digital.fr>
 */
class BundleControllerResolver
{
    /**
     * @var ApplicationInterface
     */
    private $application;
    /**
     * @var \BackBee\DependencyInjection\ContainerInterface
     */
    private $container;

    public function __construct(ApplicationInterface $application)
    {
        $this->application = $application;
        $this->container = $application->getContainer();
    }

    /**
     * Compute the service identifier bundle name
     *
     * @param  String $name
     * @return String
     */
    private function computeBundleName($name)
    {
        return str_replace('%bundle_name%', strtolower($name), BundleInterface::BUNDLE_SERVICE_ID_PATTERN);
    }

    /**
     * Resolve the bundle controller by passing two identifier (bundle and controller) and return it.
     *
     * @param  String $bundle       Bundle identifier used to declare it into bundle.yml
     * @param  String $controller   Controller identifier used to declare it inte your bundle configuration
     * @return \BackBee\Bundle\AbstractBundleController
     *
     * @throws Exception            Bad configuration
     */
    public function resolve($bundle, $controller)
    {
        if (!$this->container->has($this->computeBundleName($bundle))) {
            throw new BundleConfigurationException(sprintf("%s doesn't exist.", $bundle), BundleConfigurationException::BUNDLE_UNDECLARED);
        }

        $config = $this->container->get($this->computeBundleName($bundle))->getProperty();

        if (!isset($config['admin_controller'])) {
            throw new BundleConfigurationException(sprintf('No controller definition in %s bundle configuration.', $bundle),
                BundleConfigurationException::CONTROLLER_SECTION_MISSING
            );
        }

        if (!isset($config['admin_controller'][$controller])) {
            throw new BundleConfigurationException(sprintf('%s controller is undefined in %s bundle configuration.', $controller, $bundle),
                BundleConfigurationException::CONTROLLER_UNDECLARED);
        }
        $namespace = '\\'.$config['admin_controller'][$controller];
        return new $namespace($this->application);
    }

    /**
     * Compute the minimal Admin Base Url
     *
     * @param  String $bundleId     Bundle identifier used to declare it into bundle.yml
     * @param  String $controllerId Controller identifier used to declare it inte your bundle configuration
     * @param  String $actionId     Action identifier refere to a method name like "indexAction" into the controller without the Action keyword
     * @return String               Base URL to contact an bundle admin action
     */
    public function resolveBaseAdminUrl($bundleId, $controllerId, $actionId)
    {
        return str_replace(
            [
                '%bundle_id%',
                '%controller_id%',
                '%action_id%',
            ],
            [
                $bundleId,
                $controllerId,
                $actionId,
            ],
            BundleInterface::BUNDLE_ADMIN_URL_PATTERN
        );
    }
}
