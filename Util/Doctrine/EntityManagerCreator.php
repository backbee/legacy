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

namespace BackBee\Util\Doctrine;

use BackBee\DependencyInjection\ContainerInterface;
use BackBee\Exception\InvalidArgumentException;
use BackBee\Util\Collection\Collection;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Logging\SQLLogger;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMException;
use Exception;
use Psr\Log\LoggerInterface;

/**
 * Class EntityManagerCreator
 *
 * Utility class to create a new Doctrine entity manager.
 *
 * @package BackBee\Util\Doctrine
 *
 * @author  c.rouillon <charles.rouillon@lp-digital.fr>
 */
class EntityManagerCreator
{
    /**
     * Creates a new Doctrine entity manager.
     *
     * @param array $options Options provided to get an entity manager, keys should be :
     * - entity_manager EntityManager Optional, an already defined EntityManager (simply returns it)
     * - connection     Connection    Optional, an already initialized database connection
     * - proxy_dir      string        The proxy directory
     * - proxy_ns       string        The namespace for Doctrine proxy
     * - charset        string        Optional, the charset to use
     * - collation      string        Optional, the collation to use
     * - ...            mixed         All the required parameter to open a new connection
     *
     * @param LoggerInterface|null    $logger Optional logger
     * @param EventManager|null       $evm    Optional event manager
     * @param ContainerInterface|null $container
     *
     * @return EntityManager
     * @throws InvalidArgumentException|ORMException Occurs if $entityManager can not be returned
     */
    public static function create(
        array $options = [],
        LoggerInterface $logger = null,
        EventManager $evm = null,
        ContainerInterface $container = null
    ): EntityManager {
        if (isset($options['entity_manager'])) {
            // Test the nature of the entity_manager parameter
            $em = self::getEntityManagerWithEntityManager($options['entity_manager']);
        } else {
            // Init ORM Configuration
            $config = self::getORMConfiguration($options, $logger, $container);

            if (isset($options['connection'])) {
                // An existing connection is provided
                $em = self::createEntityManagerWithConnection($options['connection'], $config, $evm);
            } else {
                $em = self::createEntityManagerWithParameters($options, $config, $evm);
            }
        }

        // Force a connection to the database
        $em->getConnection()->errorInfo();

        self::setConnectionCharset($em->getConnection(), $options);
        self::setConnectionCollation($em->getConnection(), $options);

        if ('sqlite' === $em->getConnection()->getDatabasePlatform()->getName()) {
            self::expandSqlite($em->getConnection());
        }

        return $em;
    }

    /**
     * Custom SQLite logic.
     *
     * @param Connection $connection
     */
    private static function expandSqlite(Connection $connection): void
    {
        // add support for REGEXP operator
        $connection->getWrappedConnection()->sqliteCreateFunction(
            'regexp',
            function ($pattern, $data, string $delimiter = '~', string $modifiers = 'isuS') {
                if (isset($pattern, $data)) {
                    return (preg_match(sprintf('%1$s%2$s%1$s%3$s', $delimiter, $pattern, $modifiers), $data) > 0);
                }

                return null;
            }
        );
    }

    /**
     * @codeCoverageIgnore
     * Returns a new ORM Configuration.
     *
     * @param array                   $options Optional, the options to create the new Configuration
     * @param LoggerInterface|null    $logger
     * @param ContainerInterface|null $container
     *
     * @return Configuration
     * @throws ORMException
     */
    private static function getORMConfiguration(
        array $options = [],
        LoggerInterface $logger = null,
        ContainerInterface $container = null
    ): Configuration {
        $config = new Configuration();

        $driverImpl = $config->newDefaultAnnotationDriver([], false);

        $config->setMetadataDriverImpl($driverImpl);

        if (isset($options['proxy_dir'])) {
            $config->setProxyDir($options['proxy_dir']);
        }

        if (isset($options['proxy_ns'])) {
            $config->setProxyNamespace($options['proxy_ns']);
        }

        if (isset($options['orm'])) {
            if (isset($options['orm']['proxy_namespace'])) {
                $config->setProxyNamespace($options['orm']['proxy_namespace']);
            }

            if (isset($options['orm']['proxy_dir'])) {
                $config->setProxyDir($options['orm']['proxy_dir']);
            }

            if (isset($options['orm']['auto_generate_proxy_classes'])) {
                $config->setAutoGenerateProxyClasses($options['orm']['auto_generate_proxy_classes']);
            }

            if (
                isset(
                    $options['orm']['metadata_cache_driver']['type'],
                    $options['orm']['metadata_cache_driver']['id']
                ) &&
                is_array($options['orm']['metadata_cache_driver']) &&
                'service' === $options['orm']['metadata_cache_driver']['type']
            ) {
                $service = null;
                $serviceId = $options['orm']['metadata_cache_driver']['id'];
                if (is_object($serviceId)) {
                    $service = $serviceId;
                } elseif (is_string($serviceId)) {
                    $serviceId = str_replace('@', '', $serviceId);
                    if (null !== $container && $container->has($serviceId)) {
                        $service = $container->get($serviceId);
                    }
                }

                if (null !== $service) {
                    $config->setMetadataCacheImpl($service);
                }
            }

            if (
                isset($options['orm']['query_cache_driver']['type'], $options['orm']['query_cache_driver']['id']) &&
                is_array($options['orm']['query_cache_driver']) &&
                'service' === $options['orm']['query_cache_driver']['type']
            ) {
                $serviceId = str_replace('@', '', $options['orm']['query_cache_driver']['id']);
                if (null !== $container && $container->has($serviceId)) {
                    $config->setQueryCacheImpl($container->get($serviceId));
                }
            }
        }

        if ($logger instanceof SQLLogger) {
            $config->setSQLLogger($logger);
        }

        return self::addCustomFunctions($config, $options);
    }

    /**
     * Adds userdefined functions.
     *
     * @param Configuration $config
     * @param array         $options
     *
     * @return Configuration
     * @throws ORMException
     */
    private static function addCustomFunctions(Configuration $config, array $options = []): Configuration
    {
        if (null !== $strFcts = Collection::get($options, 'orm:entity_managers:default:dql:string_functions')) {
            foreach ($strFcts as $name => $class) {
                if (class_exists($class)) {
                    $config->addCustomStringFunction($name, $class);
                }
            }
        }

        if (null !== $numFcts = Collection::get($options, 'orm:entity_managers:default:dql:numeric_functions')) {
            foreach ($numFcts as $name => $class) {
                if (class_exists($class)) {
                    $config->addCustomNumericFunction($name, $class);
                }
            }
        }

        if (null !== $datetimeFcts = Collection::get($options, 'orm:entity_managers:default:dql:datetime_functions')) {
            foreach ($datetimeFcts as $name => $class) {
                if (class_exists($class)) {
                    $config->addCustomDatetimeFunction($name, $class);
                }
            }
        }

        return $config;
    }

    /**
     * Returns the EntityManager provided.
     *
     * @param EntityManager $entityManager
     *
     * @return EntityManager
     * @throws InvalidArgumentException Occurs if $entityManager is not an EntityManager
     */
    private static function getEntityManagerWithEntityManager(EntityManager $entityManager): EntityManager
    {
        if ($entityManager instanceof EntityManager) {
            return $entityManager;
        }

        throw new InvalidArgumentException(
            'Invalid EntityManager provided',
            InvalidArgumentException::INVALID_ARGUMENT
        );
    }

    /**
     * Returns a new EntityManager with the provided connection.
     *
     * @param Connection        $connection
     * @param Configuration     $config
     * @param EventManager|null $evm Optional event manager
     *
     * @return EntityManager
     * @throws InvalidArgumentException Occurs if $entityManager can not be created
     */
    private static function createEntityManagerWithConnection(
        $connection,
        Configuration $config,
        EventManager $evm = null
    ): EntityManager {
        if ($connection instanceof Connection) {
            try {
                return EntityManager::create($connection, $config, $evm);
            } catch (Exception $e) {
                throw new InvalidArgumentException(
                    'Enable to create new EntityManager with provided Connection',
                    InvalidArgumentException::INVALID_ARGUMENT,
                    $e
                );
            }
        }

        throw new InvalidArgumentException('Invalid Connection provided', InvalidArgumentException::INVALID_ARGUMENT);
    }

    /**
     * Returns a new EntityManager with the provided parameters.
     *
     * @param array             $options
     * @param Configuration     $config
     * @param EventManager|null $evm
     *
     * @return EntityManager
     *
     * @throws InvalidArgumentException Occurs if $entityManager can not be created
     */
    private static function createEntityManagerWithParameters(
        array $options,
        Configuration $config,
        EventManager $evm = null
    ): EntityManager {
        try {
            return EntityManager::create(self::randomizeServerPoolConnection($options), $config, $evm);
        } catch (Exception $e) {
            throw new InvalidArgumentException(
                'Enable to create new EntityManager with provided parameters',
                InvalidArgumentException::INVALID_ARGUMENT,
                $e
            );
        }
    }

    /**
     * If an array og db hosts is provided, randomize the selection of one of them
     *
     * @param array $options
     *
     * @return array
     */
    private static function randomizeServerPoolConnection($options): array
    {
        if (array_key_exists('host', $options) && is_array($options['host'])) {
            if (1 < count($options['host'])) {
                shuffle($options['host']);
            }
            $options['host'] = reset($options['host']);
        }

        return $options;
    }

    /**
     * Sets the character set for the provided connection.
     *
     * @param Connection $connection
     * @param array      $options
     *
     * @throws InvalidArgumentException Occurs if charset is invalid
     */
    private static function setConnectionCharset(Connection $connection, array $options = array()): void
    {
        if (isset($options['charset'])) {
            try {
                if ('pdo_mysql' === $connection->getDriver()->getName()) {
                    $connection->executeQuery(
                        'SET SESSION character_set_client = "' . addslashes($options['charset']) . '";'
                    );
                    $connection->executeQuery(
                        'SET SESSION character_set_connection = "' . addslashes($options['charset']) . '";'
                    );
                    $connection->executeQuery(
                        'SET SESSION character_set_results = "' . addslashes($options['charset']) . '";'
                    );
                }
            } catch (Exception $e) {
                throw new InvalidArgumentException(
                    sprintf('Invalid database character set `%s`', $options['charset']),
                    InvalidArgumentException::INVALID_ARGUMENT,
                    $e
                );
            }
        }
    }

    /**
     * Sets the collation for the provided connection.
     *
     * @param Connection $connection
     * @param array      $options
     *
     * @throws InvalidArgumentException Occurs if collation is invalid
     */
    private static function setConnectionCollation(Connection $connection, array $options = array()): void
    {
        if (isset($options['collation'])) {
            try {
                if ('pdo_mysql' === $connection->getDriver()->getName()) {
                    $connection->executeQuery(
                        'SET SESSION collation_connection = "' . addslashes($options['collation']) . '";'
                    );
                }
            } catch (Exception $e) {
                throw new InvalidArgumentException(
                    sprintf('Invalid database collation `%s`', $options['collation']),
                    InvalidArgumentException::INVALID_ARGUMENT,
                    $e
                );
            }
        }
    }
}
