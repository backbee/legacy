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

namespace BackBee\DependencyInjection;

use BackBee\ApplicationInterface;
use BackBee\DependencyInjection\Exception\ContainerAlreadyExistsException;
use BackBee\DependencyInjection\Exception\MissingBootstrapParametersException;
use BackBee\DependencyInjection\Util\ServiceLoader;
use BackBee\Util\Resolver\ConfigDirectory;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Config\EnvParametersResource;
use Symfony\Component\HttpKernel\DependencyInjection\MergeExtensionConfigurationPass;
use function array_key_exists;
use function count;
use function get_class;

/**
 * Class ContainerBuilder
 *
 * This class build and hydrate a BackBee\DependencyInjection\Container for any class
 * which implements BackBee\ApplicationInterface; it will first hydrate container with application
 * default value (data directory, repository directory, cache directory, etc.);.
 *
 * ContainerBuilder also manage container dump to not reload and parse every xml and yml.
 *
 * @package BackBee\DependencyInjection
 *
 * @author  e.chau <eric.chau@lp-digital.fr>
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class ContainerBuilder
{
    /**
     * Define default name for data folder.
     */
    public const DATA_FOLDER_NAME = 'Data';

    /**
     * Define default name for media folder.
     */
    public const MEDIA_FOLDER_NAME = 'Media';

    /**
     * Define default name for cache folder.
     */
    public const CACHE_FOLDER_NAME = 'cache';

    /**
     * Define default name for log folder.
     */
    public const LOG_FOLDER_NAME = 'log';

    /**
     * Define service filename (without extension).
     */
    public const SERVICE_FILENAME = 'services';

    /**
     * Current application which we are building a container for.
     *
     * @var ApplicationInterface
     */
    private $application;

    /**
     * The container we are building.
     *
     * @var Container
     */
    private $container;

    /**
     * Application's default repository directory.
     *
     * @var string
     */
    private $repository_directory;

    /**
     * Application's context.
     *
     * @var string
     */
    private $context;

    /**
     * Application's environment.
     *
     * @var string
     */
    private $environment;

    /**
     * @var array
     */
    private $kernelBundles;

    /**
     * ContainerBuilder's constructor;.
     *
     * @param ApplicationInterface $application the application we want to build a container for
     */
    public function __construct(ApplicationInterface $application)
    {
        $this->application = $application;
        $this->repository_directory = $application->getBaseRepository();
        $this->context = $application->getContext();
        $this->environment = $application->getEnvironment();
        $this->kernelBundles = $application->getKernelBundles();
    }

    /**
     * Every time you invoke this method it will return a new BackBee\DependencyInjection\Container.
     *
     * @return Container
     *
     * @throws ContainerAlreadyExistsException
     * @throws Exception\BootstrapFileNotFoundException
     * @throws MissingBootstrapParametersException
     */
    public function getContainer(): Container
    {
        if (null !== $this->container) {
            throw new ContainerAlreadyExistsException($this->container);
        }

        // Construct container
        $this->container = new Container();
        $this->hydrateContainerWithBootstrapParameters();

        if (false === $this->tryParseContainerDump()) {
            $this->hydrateContainerWithApplicationParameters();
            $this->hydrateContainerWithKernelParameters();
            $this->loadApplicationServices();
            $this->loadKernelBundles();
            $this->registerContainerConfiguration();
        }

        $this->container->addResource(new EnvParametersResource('SYMFONY__'));

        return $this->container;
    }

    /**
     * Remove container dump.
     *
     * @return bool
     */
    public function removeContainerDump(): bool
    {
        $success = false;
        if (
            false === $this->container->getParameter('debug')
            && true === $this->container->getParameter('container.autogenerate')
        ) {
            $dump_filepath = $this->container->getParameter('container.dump_directory');
            $dump_filepath .= DIRECTORY_SEPARATOR . $this->container->getParameter('container.filename') . '.php';

            if (true === is_file($dump_filepath) && true === is_readable($dump_filepath)) {
                $success = false !== @unlink($dump_filepath);
            }
        }

        return $success;
    }

    /**
     * Hydrate container with bootstrap.yml parameter.
     *
     * @throws MissingBootstrapParametersException|Exception\BootstrapFileNotFoundException
     * raises if we are not able to find bootstrap.yml file
     */
    private function hydrateContainerWithBootstrapParameters(): void
    {
        $parameters = (
        new BootstrapResolver(
            $this->repository_directory,
            $this->context,
            $this->environment
        )
        )->getBootstrapParameters();

        $missing_parameters = array();
        $this->tryAddParameter('debug', $parameters, $missing_parameters);
        $this->tryAddParameter('bootstrap_filepath', $parameters, $missing_parameters);
        if (true === array_key_exists('container', $parameters)) {
            $this->tryAddParameter('dump_directory', $parameters['container'], $missing_parameters, 'container.');
            $this->tryAddParameter('autogenerate', $parameters['container'], $missing_parameters, 'container.');
        } else {
            $missing_parameters[] = 'container.dump_directory';
            $missing_parameters[] = 'container.autogenerate';
        }

        if (0 < count($missing_parameters)) {
            throw new MissingBootstrapParametersException($missing_parameters);
        }
    }

    /**
     * Try to add $parameters[$key] into container with "$prefix + $key" as container key;
     * add $key into missing_parameters if it does not exist as key in $parameter.
     *
     * @param string $key                key we are looking for
     * @param array  $parameters         array from where we are looking for $key
     * @param array  $missing_parameters key will be pushed into this array if key does not exist in parameters
     * @param string $prefix             prefix to add to key when we set it into container
     */
    private function tryAddParameter(
        string $key,
        array $parameters,
        array &$missing_parameters,
        string $prefix = ''
    ): void {
        if (false !== array_key_exists($key, $parameters)) {
            $this->container->setParameter($prefix . $key, $parameters[$key]);
        } else {
            $missing_parameters[] = $prefix . $key;
        }
    }

    /**
     * Hydrates container with application core parameters (like bbapp.context, bbapp.environement, etc.).
     */
    private function hydrateContainerWithApplicationParameters(): void
    {
        $this->container->setParameter('bbapp.context', $this->context);
        $this->container->setParameter('bbapp.environment', $this->environment);
        $this->container->setParameter('environment', $this->environment);

        // set default backbee base directory, config directory and repository directory
        $this->container->setParameter('bbapp.root.dir', $this->application->getBaseDir());
        $this->container->setParameter('bbapp.base.dir', $this->application->getBBDir());
        $this->container->setParameter('bbapp.config.dir', $this->application->getConfigDir());
        $this->container->setParameter('bbapp.repository.dir', $this->application->getRepository());

        // set default cache directory and cache autogenerate value
        $cache_directory = $this->application->getBaseDir() . DIRECTORY_SEPARATOR . self::CACHE_FOLDER_NAME;
        if (ApplicationInterface::DEFAULT_ENVIRONMENT !== $this->environment) {
            $cache_directory .= DIRECTORY_SEPARATOR . $this->environment;
        }

        $this->container->setParameter('bbapp.cache.dir', $cache_directory);
        $this->container->setParameter('bbapp.cache.autogenerate', '%container.autogenerate%');

        // define log directory
        $this->container->setParameter(
            'bbapp.log.dir',
            $this->application->getBaseDir() . DIRECTORY_SEPARATOR . self::LOG_FOLDER_NAME
        );

        // define data directory
        $this->container->setParameter(
            'bbapp.data.dir',
            $this->application->getRepository() . DIRECTORY_SEPARATOR . self::DATA_FOLDER_NAME
        );

        // define media directory
        $this->container->setParameter(
            'bbapp.media.dir',
            $this->container->getParameter('bbapp.data.dir') . DIRECTORY_SEPARATOR . self::MEDIA_FOLDER_NAME
        );
    }

    /**
     * This method try to restore container from a dump if it is possible, otherwise it will set
     * container.class, container.file and container.dir parameters into container.
     */
    private function tryParseContainerDump(): bool
    {
        $success = false;

        $container_directory = $this->container->getParameter('container.dump_directory');
        $container_filename = $this->getContainerDumpFilename($this->container->getParameter('bootstrap_filepath'));
        $container_filepath = $container_directory . DIRECTORY_SEPARATOR . $container_filename;

        if (false === $this->container->getParameter('debug') && true === is_readable($container_filepath . '.php')) {
            require_once $container_filepath . '.php';
            $this->container = new $container_filename();
            $this->container->init();

            // Add current application into container
            $this->container->set('bbapp', $this->application);
            // Add container builder into container
            $this->container->set('container.builder', $this);

            $success = true;
        } else {
            $this->container->setParameter('container.filename', $container_filename);
        }

        return $success;
    }

    /**
     * Generates and returns an uniq container dump classname depending on context and environment.
     *
     * @param string $bootstrapFilepath the bootstrap.yml used file path
     *
     * @return string uniq classname for container dump depending on context and environment
     */
    private function getContainerDumpFilename(string $bootstrapFilepath): string
    {
        return 'bb' . md5('__container__' . $this->context . $this->environment . filemtime($bootstrapFilepath));
    }

    /**
     * Load and override services into container; the load order is from the most global to the most specific
     * depends on context and environment.
     */
    private function loadApplicationServices(): void
    {
        // setting default services
        $this->container->set('bbapp', $this->application);
        $this->container->set('container.builder', $this);

        $services_directory = $this->application->getBBDir() . '/Config/services';

        foreach (scandir($services_directory) as $file) {
            if (1 === preg_match('#(\w+)\.(yml|xml)$#', $file, $matches)) {
                if ('yml' === $matches[2]) {
                    ServiceLoader::loadServicesFromYamlFile($this->container, $services_directory, $matches[1]);
                } else {
                    ServiceLoader::loadServicesFromXmlFile($this->container, $services_directory, $matches[1]);
                }
            }
        }

        // define in which directory we have to looking for services yml or xml
        $directories = ConfigDirectory::getDirectories(
            null,
            $this->repository_directory,
            $this->context,
            $this->environment
        );

        // Loop into every directory where we can potentially found a services.yml or services.xml
        foreach ($directories as $directory) {
            if (true === is_readable($directory . DIRECTORY_SEPARATOR . self::SERVICE_FILENAME . '.yml')) {
                ServiceLoader::loadServicesFromYamlFile($this->container, $directory);
            }

            if (true === is_readable($directory . DIRECTORY_SEPARATOR . self::SERVICE_FILENAME . '.xml')) {
                ServiceLoader::loadServicesFromXmlFile($this->container, $directory);
            }
        }

        $this->loadLoggerDefinition();
    }

    /**
     * Load on the fly logging service definition, depends on debug value.
     */
    private function loadLoggerDefinition(): void
    {
        $logger_class = $this->container->getParameter('bbapp.logger.class');
        if (true === $this->container->getParameter('debug')) {
            $logger_class = $this->container->getParameter('bbapp.logger_debug.class');
        }

        $this->container->setDefinition('logging', new Definition($logger_class, array(new Reference('bbapp'))));
    }

    /**
     * Load kernel bundles.
     */
    private function loadKernelBundles(): void
    {
        $extensions = [];

        foreach ($this->kernelBundles as $bundle) {
            if ($extension = $bundle->getContainerExtension()) {
                $this->container->registerExtension($extension);
                $extensions[] = $extension->getAlias();
            }

            if ($this->application->isDebugMode()) {
                $this->container->addObjectResource($bundle);
            }

            $bundle->build($this->container);
        }

        $this->container->getCompilerPassConfig()->setMergePass(new MergeExtensionConfigurationPass($extensions));
    }

    /**
     * Hydrate container with kernel parameters.
     */
    private function hydrateContainerWithKernelParameters(): void
    {
        $bundles = [];
        $bundlesMetadata = [];

        foreach ($this->kernelBundles as $name => $bundle) {
            $bundles[$name] = get_class($bundle);
            $bundlesMetadata[$name] = [
                'parent' => $bundle->getParent(),
                'path' => $bundle->getPath(),
                'namespace' => $bundle->getNamespace(),
            ];
        }

        $this->container->setParameter('kernel.environment', $this->environment);
        $this->container->setParameter('kernel.debug', $this->application->isDebugMode());
        $this->container->setParameter('kernel.cache_dir', $this->application->getCacheDir());
        $this->container->setParameter('kernel.logs_dir', $this->application->getLogDir());
        $this->container->setParameter('kernel.bundles', $bundles);
        $this->container->setParameter('kernel.bundles_metadata', $bundlesMetadata);
    }

    /**
     * Register container configuration.
     */
    private function registerContainerConfiguration(): void
    {
        $cont = $this->application->registerContainerConfiguration(
            ServiceLoader::getContainerLoader($this->application, $this->container)
        );

        if (null !== $cont) {
            $this->container->merge($cont);
        }
    }
}
