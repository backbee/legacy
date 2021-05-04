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

namespace BackBee\Config\Persistor;

use BackBee\ApplicationInterface;
use BackBee\Bundle\Registry as RegistryEntity;
use BackBee\Config\Config;

/**
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      e.chau <eric.chau@lp-digital.fr>
 */
class Registry implements PersistorInterface
{
    /**
     * @var ApplicationInterface
     */
    private $application;

    /**
     * Is a configuration is persisted by application context ?
     *
     * @var boolean
     */
    private $persistPerContext;

    /**
     * Is a configuration is persisted by application environment ?
     *
     * @var boolean
     */
    private $persistPerEnvironment;

    /**
     * @see BackBee\Config\Persistor\PersistorInterface::__construct
     */
    public function __construct(ApplicationInterface $application, $persistPerContext, $persistPerEnvironment)
    {
        $this->application = $application;
        $this->persistPerContext = (true === $persistPerContext);
        $this->persistPerEnvironment = (true === $persistPerEnvironment);
    }

    /**
     * Returns the registry scope.
     *
     * @param  string $key
     *
     * @return string
     */
    public function getScope($key)
    {
        $scope = ('application' === $key) ? 'APPLICATION_CONFIG' : 'BUNDLE_CONFIG';

        if ($this->application->getContainer()->has('config.configurator')) {
            $scope = $this
                    ->application
                    ->getContainer()
                    ->get('config.configurator')
                    ->getRegistryScope($scope, $this->persistPerContext, $this->persistPerEnvironment)
            ;
        }

        return $scope;
    }

    /**
     * @see BackBee\Config\Persistor\PersistorInterface::persist
     */
    public function persist(Config $config, array $configToPersist)
    {
        if (array_key_exists('override_site', $configToPersist)) {
            $configToPersist = array(
                'override_site' => $configToPersist['override_site'],
            );
        }

        $key = $this->application->getContainer()->get('bundle.loader')->getBundleIdByBaseDir($config->getBaseDir());
        if (null === $key) {
            $key = 'application';
        }

        $scope = $this->getScope($key);

        $registry = $this->application->getEntityManager()
            ->getRepository('BackBee\Bundle\Registry')->findOneBy(array(
                'key'   => $key,
                'scope' => $scope,
            ))
        ;

        if (null === $registry) {
            $registry = new RegistryEntity();
            $registry->setKey($key);
            $registry->setScope($scope);
            $this->application->getEntityManager()->persist($registry);
        }

        $registry->setValue(serialize($configToPersist));
        $success = true;
        try {
            $this->application->getEntityManager()->flush($registry);
        } catch (\Exception $e) {
            $success = false;
        }

        return $success;
    }
}
