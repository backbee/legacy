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

namespace BackBee\Config\Persistor;

use Symfony\Component\Yaml\Yaml;
use BackBee\ApplicationInterface;
use BackBee\Config\Config;

/**
 * @category    BackBee
 *
 *
 * @author      e.chau <eric.chau@lp-digital.fr>
 */
class File implements PersistorInterface
{
    /**
     * @var ApplicationInterface
     */
    private $app;

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
    public function __construct(ApplicationInterface $app, $persistPerContext, $persistPerEnvironment)
    {
        $this->app = $app;
        $this->persistPerContext = (true === $persistPerContext);
        $this->persistPerEnvironment = (true === $persistPerEnvironment);
    }

    /**
     * @see BackBee\Config\Persistor\PersistorInterface::persist
     */
    public function persist(Config $config, array $configToPersist)
    {
        try {
            $success = file_put_contents(
                $this->getConfigDumpRightDirectory($config->getBaseDir()).DIRECTORY_SEPARATOR.'config.yml',
                Yaml::dump($configToPersist)
            );
        } catch (\Exception $e) {
            $success = false;
        }

        return false !== $success;
    }

    /**
     * Returns path to the right directory to dump and save config.yml file.
     *
     * @param string $baseDir config base directory
     *
     * @return string
     */
    private function getConfigDumpRightDirectory($baseDir)
    {
        $configDumpDir = $this->app->getBaseRepository();
        if ($this->persistPerContext && ApplicationInterface::DEFAULT_CONTEXT !== $this->app->getContext()) {
            $configDumpDir .= DIRECTORY_SEPARATOR.$this->app->getContext();
        }

        $configDumpDir .= DIRECTORY_SEPARATOR.'Config';
        if ($this->persistPerEnvironment && ApplicationInterface::DEFAULT_ENVIRONMENT !== $this->app->getEnvironment()) {
            $configDumpDir .= DIRECTORY_SEPARATOR.$this->app->getEnvironment();
        }

        $key = $this->app->getContainer()->get('bundle.loader')->getBundleIdByBaseDir($baseDir);
        if (null !== $key) {
            $configDumpDir .= DIRECTORY_SEPARATOR.'bundle'.DIRECTORY_SEPARATOR.$key;
        }

        if (!is_dir($configDumpDir) && false === @mkdir($configDumpDir, 0755, true)) {
            throw new \Exception('Unable to create config dump directory');
        }

        return $configDumpDir;
    }
}
