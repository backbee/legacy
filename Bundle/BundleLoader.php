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

namespace BackBee\Bundle;

use BackBee\ApplicationInterface;
use BackBee\Config\Config;
use BackBee\DependencyInjection\ContainerInterface;
use BackBee\DependencyInjection\Dumper\DumpableServiceInterface;
use BackBee\DependencyInjection\Dumper\DumpableServiceProxyInterface;
use BackBee\DependencyInjection\Util\ServiceLoader;
use BackBee\Bundle\Event\BundleInstallUpdateEvent;
use BackBee\Exception\InvalidArgumentException;
use BackBee\Util\Resolver\BundleConfigDirectory;

use Doctrine\ORM\Tools\SchemaTool;

use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * BundleLoader loads and injects bundles into application and its dependency injection container.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      eric.chau <eric.chau@lp-digital.fr>
 */
class BundleLoader implements DumpableServiceInterface, DumpableServiceProxyInterface
{
    const CLASSCONTENT_RECIPE_KEY = 'classcontent';
    const CUSTOM_RECIPE_KEY = 'custom';
    const EVENT_RECIPE_KEY = 'event';
    const HELPER_RECIPE_KEY = 'helper';
    const NAMESPACE_RECIPE_KEY = 'namespace';
    const RESOURCE_RECIPE_KEY = 'resource';
    const ROUTE_RECIPE_KEY = 'route';
    const SERVICE_RECIPE_KEY = 'service';
    const TEMPLATE_RECIPE_KEY = 'template';

    /**
     * @var BackBee\ApplicationInterface
     */
    private $application;

    /**
     * @var BackBee\DependencyInjection\ContainerInterface
     */
    private $container;

    /**
     * @var array
     */
    private $bundlesBaseDir = [];

    /**
     * @var array
     */
    private $reflectionClasses = [];

    /**
     * @var array
     */
    private $bundleInfos = [];

    /**
     * define if renderer has been restored by container or not.
     *
     * @var boolean
     */
    private $isRestored;

    /**
     * @param ApplicationInterface $application
     */
    public function __construct(ApplicationInterface $application)
    {
        $this->application = $application;
        $this->container = $application->getContainer();
        $this->isRestored = false;
    }

    /**
     * Loads bundles into application.
     *
     * @param array $config bundle configurations
     */
    public function load(array $config)
    {
        foreach ($config as $id => $classname) {
            $serviceId = $this->generateBundleServiceId($id);

            if (false === $this->container->hasDefinition($serviceId)) {
                $baseDir = $this->buildBundleBaseDirectoryFromClassname($classname);
                $this->bundlesBaseDir[$serviceId] = $baseDir;
                $this->container->setDefinition(
                    $serviceId,
                    $this->buildBundleDefinition($classname, $id, $baseDir)
                );

                $this->bundleInfos[$id] = [
                    'main_class' => $classname,
                    'base_dir'   => $baseDir,
                ];
            }
        }

        if (0 < count($this->bundlesBaseDir)) {
            $this->loadFullBundles();
        }
    }

    /**
     * Returns bundle id if provided path is matched with any bundle base directory.
     *
     * @param string $path
     *
     * @return string
     */
    public function getBundleIdByBaseDir($path)
    {
        $bundleId = null;
        foreach ($this->bundleInfos as $id => $data) {
            if (0 === strpos($path, $data['base_dir'])) {
                $bundleId = $id;
                break;
            }
        }

        return $bundleId;
    }

    /**
     * Computes and returns bundle base directory.
     *
     * @param string $classname
     *
     * @return string
     */
    public function buildBundleBaseDirectoryFromClassname($classname)
    {
        if (false === array_key_exists($classname, $this->reflectionClasses)) {
            $this->reflectionClasses[$classname] = new \ReflectionClass($classname);
        }

        $baseDir = dirname($this->reflectionClasses[$classname]->getFileName());

        if (!is_dir($baseDir)) {
            throw new \RuntimeException("Invalid bundle `$bundle` base directory, expected `$baseDir` to exist.");
        }

        return $baseDir;
    }

    /**
     * Sets bundle's Config definition into dependency injection container.
     *
     * @param string $configId
     * @param string $baseDir
     */
    public function loadConfigDefinition($configId, $baseDir)
    {
        if (false === $this->container->hasDefinition($configId)) {
            $this->container->setDefinition($configId, $this->buildConfigDefinition($baseDir));
        }
    }

    /**
     * Loads bundles routes into application's router.
     */
    public function loadBundlesRoutes()
    {
        $loadedBundles = array_keys($this->container->findTaggedServiceIds('bundle.config'));
        foreach ($loadedBundles as $serviceId) {
            $config = $this->container->get($serviceId);
            $recipes = $this->getLoaderRecipesByConfig($config);

            $this->loadRoutes($config, $this->getCallbackFromRecipes($recipes, self::ROUTE_RECIPE_KEY));
        }
    }

    /**
     * It will at least install provided bundle Doctrine entities located into /Entity folder.
     *
     * Note that if $force is equal to false it will only return informations your bundle installation but
     * it won't execute any SQL statement.
     *
     * @param  BundleInterface $bundle The bundle to install
     * @param  boolean         $force  If true it will execute SQL query, default: false
     * @return array
     */
    public function installBundle(BundleInterface $bundle, $force = false)
    {
        $event = new BundleInstallUpdateEvent($bundle, [
            'force' => $force,
            'logs'  => [
                'sql'   => [],
            ],
        ]);

        $this->application->getEventDispatcher()->dispatch(sprintf('bundle.%s.preinstall', $bundle->getId()), $event);

        $sqls = $this->createEntitiesSchema($bundle, $force);

        $this->application->getEventDispatcher()->dispatch(sprintf('bundle.%s.postinstall', $bundle->getId()), $event);

        return array_merge($event->getLogs(), [
            'sql' => $sqls,
        ]);
    }

    /**
     * It will at least update provided bundle Doctrine entities located into /Entity folder.
     *
     * Note that if $force is equal to false it will only return updates informations about your bundle but
     * it won't execute any SQL statement.
     *
     * @param  BundleInterface $bundle The bundle to update
     * @param  boolean         $force  If true it will execute SQL query, default: false
     * @return array
     */
    public function updateBundle(BundleInterface $bundle, $force = false)
    {
        $event = new BundleInstallUpdateEvent($bundle, [
            'force' => $force,
            'logs'  => [
                'sql'   => [],
            ],
        ]);

        $this->application->getEventDispatcher()->dispatch(sprintf('bundle.%s.preupdate', $bundle->getId()), $event);

        $sqls = $this->updateEntitiesSchema($bundle, $force);

        $this->application->getEventDispatcher()->dispatch(sprintf('bundle.%s.postupdate', $bundle->getId()), $event);

        return array_merge($event->getLogs(), [
            'sql' => $sqls,
        ]);
    }

    /**
     * Creates SQL requests to create your bundle entities and executes them against application database
     * if force is equal to true.
     *
     * @param  BundleInterface $bundle
     * @param  boolean         $force
     * @return array
     */
    private function createEntitiesSchema(BundleInterface $bundle, $force = false)
    {
        if (!is_dir($entityDir = $this->getBundleEntityDir($bundle))) {
            return [];
        }

        $bundleEntityManager = $bundle->getEntityManager();
        $bundleEntityManager
            ->getConfiguration()
            ->getMetadataDriverImpl()
            ->addPaths([
                $entityDir,
            ])
        ;

        $metadata = $bundleEntityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($bundleEntityManager);
        $sqls = $schemaTool->getCreateSchemaSql($metadata);
        if (true === $force) {
            $schemaTool->createSchema($metadata);
        }

        return $sqls;
    }

    /**
     * Creates SQL requests to update your bundle entities and executes them against application database
     * if force is equal to true.
     *
     * @param  BundleInterface $bundle
     * @param  boolean         $force
     * @return array
     */
    private function updateEntitiesSchema(BundleInterface $bundle, $force = false)
    {
        if (!is_dir($entityDir = $this->getBundleEntityDir($bundle))) {
            return [];
        }

        $bundleEntityManager = $bundle->getEntityManager();
        $bundleEntityManager
            ->getConfiguration()
            ->getMetadataDriverImpl()
            ->addPaths([
                $entityDir,
            ])
        ;

        $metadata = $bundleEntityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($bundleEntityManager);
        $sqls = $schemaTool->getUpdateSchemaSql($metadata, true);
        if (true === $force) {
            $schemaTool->updateSchema($metadata, true);
        }

        return $sqls;
    }

    /**
     * Returns bundle entity directory path.
     *
     * @param  BundleInterface $bundle
     * @return string
     */
    protected function getBundleEntityDir(BundleInterface $bundle)
    {
        return $bundle->getBaseDirectory().DIRECTORY_SEPARATOR.'Entity';
    }

    /**
     * Generates bundle service identifier.
     *
     * @param string $id The bundle identifier
     *
     * @return string
     */
    private function generateBundleServiceId($id)
    {
        return str_replace('%bundle_name%', strtolower($id), BundleInterface::BUNDLE_SERVICE_ID_PATTERN);
    }

    /**
     * Builds and return bundle definition.
     *
     * @param string $classname The bundle entry point classname
     * @param string $bundleId  The bundle id/name
     * @param string $baseDir   The bundle base directory
     *
     * @return \Symfony\Component\DependencyInjection\Definition
     *
     * @throws InvalidArgumentException if provided classname does not implements BackBee\Bundle\BundleInterface
     */
    private function buildBundleDefinition($classname, $bundleId, $baseDir)
    {
        if (false === is_subclass_of($classname, 'BackBee\Bundle\BundleInterface')) {
            throw new InvalidArgumentException(
                "Bundles must implement `BackBee\Bundle\BundleInterface`, `$classname` does not."
            );
        }

        $definition = new Definition($classname, array(new Reference('bbapp'), $bundleId, $baseDir));
        $definition->addTag('bundle', array('dispatch_event' => false));
        $definition->addMethodCall('start');
        $definition->addMethodCall('started');

        return $definition;
    }

    /**
     * Executes full bundle's loading process into application's dependency injection container.
     */
    private function loadFullBundles()
    {
        $data = [];
        foreach ($this->bundlesBaseDir as $serviceId => $baseDir) {
            $config = $this->loadAndGetBundleConfigByBaseDir($serviceId, $baseDir);
            $bundleConfig = $config->getSection('bundle');
            if (isset($bundleConfig['enable']) && !((boolean) $bundleConfig['enable'])) {
                continue;
            }

            $recipes = $this->getLoaderRecipesByConfig($config);

            $data[] = [$config, $recipes];

            $this->loadServices($config, $serviceId, $this->getCallbackFromRecipes(self::SERVICE_RECIPE_KEY, $recipes));
        }

        foreach ($data as $row) {
            list($config, $recipes) = $row;

            $this->loadEvents($config, $this->getCallbackFromRecipes(self::EVENT_RECIPE_KEY, $recipes));
            $this->loadRoutes($config, $this->getCallbackFromRecipes(self::ROUTE_RECIPE_KEY, $recipes));
            $this->addClassContentDir($config, $this->getCallbackFromRecipes(self::CLASSCONTENT_RECIPE_KEY, $recipes));
            $this->addTemplatesDir($config, $this->getCallbackFromRecipes(self::TEMPLATE_RECIPE_KEY, $recipes));
            $this->addHelpersDir($config, $this->getCallbackFromRecipes(self::HELPER_RECIPE_KEY, $recipes));
            $this->addResourcesDir($config, $this->getCallbackFromRecipes(self::RESOURCE_RECIPE_KEY, $recipes));
            $this->addNamespaces($config, $this->getCallbackFromRecipes(self::NAMESPACE_RECIPE_KEY, $recipes));
            $this->runRecipe($config, $this->getCallbackFromRecipes(self::CUSTOM_RECIPE_KEY, $recipes));
        }
    }

    /**
     * Loads and returns bundle's Config.
     *
     * @param string $serviceId
     * @param string $baseDir
     *
     * @return
     */
    private function loadAndGetBundleConfigByBaseDir($serviceId, $baseDir)
    {
        $configId = str_replace('%bundle_service_id%', $serviceId, BundleInterface::CONFIG_SERVICE_ID_PATTERN);

        $this->loadConfigDefinition($configId, $baseDir);
        $bundleConfig = $this->container->get($configId)->getBundleConfig();
        if (isset($bundleConfig['config_per_site']) && true === $bundleConfig['config_per_site']) {
            $definition = $this->container->getDefinition($configId);
            $definition->addTag('config_per_site');
        }

        return $this->container->get($configId);
    }

    /**
     * Builds bundle Config definition.
     *
     * @param string $baseDir The bundle base directory
     *
     * @return \Symfony\Component\DependencyInjection\Definition
     */
    private function buildConfigDefinition($baseDir)
    {
        $definition = new Definition('BackBee\Config\Config', array(
            $this->getConfigDirByBundleBaseDir($baseDir),
            new Reference('cache.bootstrap'),
            null,
            '%debug%',
            '%config.yml_files_to_ignore%',
        ));

        if (true === $this->application->getContainer()->getParameter('container.autogenerate')) {
            $definition->addTag('dumpable', array('dispatch_event' => false));
        }

        $definition->addMethodCall('setContainer', array(new Reference('service_container')));
        $definition->addMethodCall('setEnvironment', array('%bbapp.environment%'));
        $definition->setConfigurator(array(new Reference('config.configurator'), 'configureBundleConfig'));
        $definition->addTag('bundle.config', array('dispatch_event' => false));

        return $definition;
    }

    /**
     * Computes and returns Config base diretory.
     *
     * @param string $baseDir The bundle base directory
     *
     * @return string
     */
    private function getConfigDirByBundleBaseDir($baseDir)
    {
        $directory = $baseDir.DIRECTORY_SEPARATOR.BundleInterface::CONFIG_DIRECTORY_NAME;
        if (!is_dir($directory)) {
            $directory = $baseDir.DIRECTORY_SEPARATOR.BundleInterface::OLD_CONFIG_DIRECTORY_NAME;
        }

        return $directory;
    }

    /**
     * Extracts and returns bundle loader recipes from Config.
     *
     * @param Config $config
     *
     * @return array|null
     */
    private function getLoaderRecipesByConfig(Config $config)
    {
        $recipes = null;
        $bundleConfig = $config->getBundleConfig();
        if (null !== $bundleConfig && isset($bundleConfig['bundle_loader_recipes'])) {
            $recipes = $bundleConfig['bundle_loader_recipes'];
        }

        return $recipes;
    }

    /**
     * Extracts and returns callback from recipes if there is one which matchs with provided key.
     *
     * @param string $key
     * @param array  $recipes
     *
     * @return null|callable
     */
    private function getCallbackFromRecipes($key, array $recipes = null)
    {
        $recipe = null;
        if (isset($recipes[$key]) && is_callable($recipes[$key])) {
            $recipe = $recipes[$key];
        }

        return $recipe;
    }

    /**
     * Loads bundle services into application's dependency injection container.
     *
     * @param Config        $config
     * @param string        $serviceId
     * @param callable|null $recipe
     */
    private function loadServices(Config $config, $serviceId, callable $recipe = null)
    {
        if (false === $this->runRecipe($config, $recipe)) {
            $directories = array_unique(array_merge(
                            BundleConfigDirectory::getDirectories(
                                    $this->application->getBaseRepository(),
                                    $this->application->getContext(),
                                    $this->application->getEnvironment(),
                                    str_replace('bundle.', '', $serviceId)
                            ), BundleConfigDirectory::getDirectories(
                                    $this->application->getBaseRepository(),
                                    $this->application->getContext(),
                                    $this->application->getEnvironment(),
                                    basename(dirname($config->getBaseDir()))
            )));
            array_unshift($directories, $this->getConfigDirByBundleBaseDir(dirname($config->getBaseDir())));

            foreach ($directories as $directory) {
                $filepath = $directory.DIRECTORY_SEPARATOR.'services.xml';
                if (is_file($filepath) && is_readable($filepath)) {
                    try {
                        ServiceLoader::loadServicesFromXmlFile($this->container, $directory);
                    } catch (\Exception $e) {
                        // nothing to do
                    }
                }

                $filepath = $directory.DIRECTORY_SEPARATOR.'services.yml';
                if (is_file($filepath) && is_readable($filepath)) {
                    try {
                        ServiceLoader::loadServicesFromYamlFile($this->container, $directory);
                    } catch (\Exception $e) {
                        // nothing to do
                    }
                }
            }
        }
    }

    /**
     * Loads bundle's events into application's event dispatcher.
     *
     * @param Config        $config
     * @param callable|null $recipe
     */
    private function loadEvents(Config $config, callable $recipe = null)
    {
        if (false === $this->runRecipe($config, $recipe)) {
            $events = $config->getRawSection('events');
            if (true === is_array($events) && 0 < count($events)) {
                $this->application->getEventDispatcher()->addListeners($events);
            }
        }
    }

    /**
     * Adds bundle's classcontent directory into application.
     *
     * @param Config        $config
     * @param callable|null $recipe
     */
    private function addClassContentDir(Config $config, callable $recipe = null)
    {
        if (null !== $recipe) {
            $this->runRecipe($config, $recipe);
        } else {
            $directory = realpath(dirname($config->getBaseDir()).DIRECTORY_SEPARATOR.'ClassContent');
            if (false !== $directory) {
                $this->application->unshiftClassContentDir($directory);
            }
        }
    }

    /**
     * Adds bundle's templates base directory into application's renderer.
     *
     * @param Config        $config
     * @param callable|null $recipe
     */
    private function addTemplatesDir(Config $config, callable $recipe = null)
    {
        if (false === $this->runRecipe($config, $recipe)) {
            $directory = realpath(
                dirname($config->getBaseDir()).DIRECTORY_SEPARATOR
                .'Templates'.DIRECTORY_SEPARATOR.'scripts'
            );
            if (false !== $directory) {
                $this->application->getRenderer()->addScriptDir($directory);
            }
        }
    }

    /**
     * Adds bundle's helpers directory into application's renderer.
     *
     * @param Config        $config
     * @param callable|null $recipe
     */
    private function addHelpersDir(Config $config, callable $recipe = null)
    {
        if (false === $this->runRecipe($config, $recipe)) {
            $directory = realpath(
                dirname($config->getBaseDir()).DIRECTORY_SEPARATOR
                .'Templates'.DIRECTORY_SEPARATOR.'helpers'
            );

            if (false !== $directory) {
                $this->application->getRenderer()->addHelperDir($directory);
            }
        }
    }

    /**
     * Executes loading of bundle's routes into application's front controller.
     *
     * @param Config        $config
     * @param callable|null $recipe
     */
    private function loadRoutes(Config $config, callable $recipe = null)
    {
        if (false === $this->runRecipe($config, $recipe)) {
            $route = $config->getRouteConfig();
            if (true === is_array($route) && 0 < count($route)) {
                $this->application->getController()->registerRoutes(
                    $this->generateBundleServiceId($this->getBundleIdByBaseDir($config->getBaseDir())),
                    $route
                );
            }
        }
    }

    /**
     * Adds bundle's resources directory into application.
     *
     * @param Config        $config
     * @param callable|null $recipe
     */
    private function addResourcesDir(Config $config, callable $recipe = null)
    {
        if (false === $this->runRecipe($config, $recipe)) {
            $baseDir = dirname($config->getBaseDir()).DIRECTORY_SEPARATOR;
            $directory = realpath($baseDir.DIRECTORY_SEPARATOR.'Resources');

            if (false === $directory) {
                $directory = realpath($baseDir.DIRECTORY_SEPARATOR.'Ressources');
            }

            if (false !== $directory) {
                $this->application->pushResourceDir($directory);
            }
        }
    }

    /**
     * Runs bundle's custom namespace callback if exists.
     *
     * @param Config        $config
     * @param callable|null $recipe
     */
    private function addNamespaces(Config $config, callable $recipe = null)
    {
        $this->runRecipe($config, $recipe);
    }

    /**
     * Runs recipe/callback if the provided one is not null.
     *
     * @param Config        $config
     * @param callable|null $recipe
     *
     * @return boolean
     */
    private function runRecipe(Config $config, callable $recipe = null)
    {
        $done = false;
        if (null !== $recipe) {
            call_user_func_array($recipe, array($this->application, $config));
            $done = true;
        }

        return $done;
    }

    /**
     * Returns the namespace of the class proxy to use or null if no proxy is required.
     *
     * @return string|null the namespace of the class proxy to use on restore or null if no proxy required
     */
    public function getClassProxy()
    {
        return;
    }

    /**
     * Dumps current service state so we can restore it later by calling DumpableServiceInterface::restore()
     * with the dump array produced by this method.
     *
     * @return array contains every datas required by this service to be restored at the same state
     */
    public function dump(array $options = array())
    {
        return [
            'bundleInfos' => $this->bundleInfos,
        ];
    }

    /**
     * Restore current service to the dump's state.
     *
     * @param array $dump the dump provided by DumpableServiceInterface::dump() from where we can
     *                    restore current service
     */
    public function restore(ContainerInterface $container, array $dump)
    {
        $this->bundleInfos = $dump['bundleInfos'];

        $this->isRestored = true;
    }

    /**
     * @return boolean true if current service is already restored, otherwise false
     */
    public function isRestored()
    {
        return $this->isRestored;
    }
}
