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
use BackBee\Bundle\BundleInterface;
use BackBee\Cache\CacheInterface;
use BackBee\Cache\DAO\Cache;
use BackBee\Config\Config;
use BackBee\Console\Console;
use BackBee\Controller\FrontController;
use BackBee\DependencyInjection\Container;
use BackBee\DependencyInjection\ContainerBuilder;
use BackBee\DependencyInjection\ContainerInterface;
use BackBee\DependencyInjection\Dumper\DumpableServiceInterface;
use BackBee\DependencyInjection\Dumper\DumpableServiceProxyInterface;
use BackBee\Event\Event;
use BackBee\Exception\BBException;
use BackBee\Renderer\AbstractRenderer;
use BackBee\Rewriting\UrlGenerator;
use BackBee\Routing\RouteCollection;
use BackBee\Security\SecurityContext;
use BackBee\Security\Token\BBUserToken;
use BackBee\Site\Site;
use BackBee\Util\File\File;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\ORM\EntityManager;
use Exception;
use InvalidArgumentException;
use LogicException;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Swift_Mailer;
use Swift_SmtpTransport;
use Symfony\Component\Debug\Debug;
use Symfony\Component\Debug\Exception\ContextErrorException;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Validator\ValidatorInterface;

/**
 * The main BackBee application.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class BBApplication implements ApplicationInterface, DumpableServiceInterface, DumpableServiceProxyInterface
{
    const VERSION = '1.3.11';

    /**
     * application's service container.
     *
     * @var ContainerInterface
     */
    private $container;

    /**
     * application's context.
     *
     * @var string
     */
    private $context;

    /**
     * application's environment.
     *
     * @var string
     */
    private $environment;

    /**
     * define if application is started with debug mode or not.
     *
     * @var boolean
     */
    private $debug;
    private $isInitialized;
    private $isStarted;
    private $repository;
    private $baseRepository;
    private $resourceDir;
    private $storageDir;
    private $tmpDir;
    private $classcontentDir;
    private $overwriteConfig;

    /**
     * tell us if application has been restored by container or not.
     *
     * @var boolean
     */
    private $isRestored;

    /**
     * @var array
     */
    private $dumpDatas;

    /**
     * @param null $context
     * @param null $environment
     * @param true $overwrite_config set true if you need overide base config with the context config
     *
     * @throws BBException
     * @throws ContextErrorException
     */
    public function __construct($context = null, $environment = null, $overwrite_config = false)
    {
        $this->context = $context ?? self::DEFAULT_CONTEXT;
        $this->isInitialized = false;
        $this->isStarted = false;
        $this->overwriteConfig = $overwrite_config;
        $this->isRestored = false;
        $this->environment = null !== $environment && is_string($environment)
            ? $environment
            : self::DEFAULT_ENVIRONMENT;
        $this->dumpDatas = [];

        $this->initAnnotationReader();
        $this->initContainer();

        register_shutdown_function([$this, 'onFatalError']);

        if ($this->isDebugMode()) {
            Debug::enable();
        }

        $this->initEnvVariables();
        $this->initAutoloader();
        $this->initContentWrapper();

        try {
            $this->initEntityManager();
        } catch (Exception $e) {
            $this->getLogging()->notice('BackBee initialized without EntityManager');
        }

        $this->initBundles();

        if (!$this->getContainer()->has('em')) {
            $this->debug(
                sprintf(
                    'BBApplication (v.%s) partial initialization with context `%s`, debugging set to %s',
                    self::VERSION,
                    $this->context,
                    var_export($this->debug, true)
                )
            );

            return;
        }

        // Force container to create SecurityContext object to activate listener
        try {
            $this->getSecurityContext();
        } catch (ContextErrorException $ex) {
            if (null === $this->getEntityManager()) {
                throw new InvalidArgumentException(
                    'Unable to initialize security context, did you try to activate ACL voter without database connection?'
                );
            }

            throw $ex;
        }

        $this->debug(
            sprintf(
                'BBApplication (v.%s) initialization with context `%s`, debugging set to %s',
                self::VERSION,
                $this->context,
                var_export($this->debug, true)
            )
        );
        $this->debug(sprintf('  - Base directory set to `%s`', $this->getBaseDir()));
        $this->debug(sprintf('  - Repository directory set to `%s`', $this->getRepository()));

        $this->isInitialized = true;

        // trigger bbapplication.init
        $this->getEventDispatcher()->dispatch('bbapplication.init', new Event($this));
    }

    public function __destruct()
    {
        $this->stop();
    }

    public function __call($method, $args)
    {
        if ($this->getContainer()->has('logging')) {
            call_user_func_array([$this->getContainer()->get('logging'), $method], $args);
        }
    }

    /**
     * @return Swift_Mailer
     */
    public function getMailer()
    {
        if (!$this->getContainer()->has('mailer') || is_null($this->getContainer()->get('mailer'))) {
            if (null !== $mailer_config = $this->getConfig()->getSection('mailer')) {
                $smtp = is_array($mailer_config['smtp']) ? reset($mailer_config['smtp']) : $mailer_config['smtp'];
                $port = is_array($mailer_config['port']) ? reset($mailer_config['port']) : $mailer_config['port'];
                $encryption = !isset($mailer_config['encryption'])
                    ? null : (is_array($mailer_config['encryption'])
                        ? reset($mailer_config['encryption'])
                        : $mailer_config['encryption']);

                $transport = Swift_SmtpTransport::newInstance($smtp, $port, $encryption);
                if (array_key_exists('username', $mailer_config) && array_key_exists('password', $mailer_config)) {
                    $username = is_array($mailer_config['username'])
                        ? reset($mailer_config['username'])
                        : $mailer_config['username'];
                    $password = is_array($mailer_config['password'])
                        ? reset($mailer_config['password'])
                        : $mailer_config['password'];

                    $transport->setUsername($username)->setPassword($password);
                }

                $this->getContainer()->set('mailer', Swift_Mailer::newInstance($transport));
            }
        }

        return $this->getContainer()->get('mailer');
    }

    /**
     * @return boolean
     */
    public function isDebugMode()
    {
        $debug = (bool)$this->debug;
        if (null !== $this->getContainer() && $this->getContainer()->hasParameter('debug')) {
            $debug = $this->getContainer()->getParameter('debug');
        }

        return $debug;
    }

    /**
     * @return boolean
     */
    public function isOverridedConfig()
    {
        return $this->overwriteConfig;
    }

    /**
     * @param type $name
     *
     * @return BundleInterface|null
     */
    public function getBundle($name)
    {
        $bundle = null;
        if ($this->getContainer()->has('bundle.' . $name)) {
            $bundle = $this->getContainer()->get('bundle.' . $name);
        }

        return $bundle;
    }

    /**
     * returns every registered bundles.
     *
     * @return array
     */
    public function getBundles()
    {
        $bundles = [];
        foreach ($this->getContainer()->findTaggedServiceIds('bundle') as $id => $datas) {
            $bundles[] = $this->getContainer()->get($id);
        }

        return $bundles;
    }

    /**
     * @param Site $site
     */
    public function start(Site $site = null)
    {
        if (null === $this->getEntityManager()) {
            throw new LogicException('Cannot start BackBee without database connection');
        }

        if (null === $site) {
            $site = $this->getEntityManager()->getRepository('BackBee\Site\Site')->findOneBy([]);
        }

        if (null !== $site) {
            $this->getContainer()->set('site', $site);
        }

        $this->isStarted = true;
        $this->info(sprintf('BackBee application started (Site Uid: %s)', null !== $site ? $site->getUid() : 'none'));

        // trigger bbapplication.start
        $this->getEventDispatcher()->dispatch('bbapplication.start', new Event($this));

        if (!$this->isClientSAPI()) {
            $response = $this->getController()->handle();
            if ($response instanceof Response) {
                $this->getController()->sendResponse($response);
            }
        }
    }

    /**
     * Stop the current BBApplication instance.
     */
    public function stop()
    {
        if ($this->isStarted()) {
            // trigger bbapplication.stop
            $this->getEventDispatcher()->dispatch('bbapplication.stop', new Event($this));
            $this->info('BackBee application ended');
        }
    }

    /**
     * @return FrontController
     */
    public function getController(): FrontController
    {
        return $this->getContainer()->get('controller');
    }

    /**
     * @return RouteCollection
     */
    public function getRouting(): RouteCollection
    {
        return $this->getContainer()->get('routing');
    }

    /**
     * @return AutoLoader
     */
    public function getAutoloader(): AutoLoader
    {
        return $this->getContainer()->get('autoloader');
    }

    /**
     * @return string
     */
    public function getBBDir()
    {
        return __DIR__;
    }

    /**
     * Returns path to Data directory.
     *
     * @return string absolute path to Data directory
     */
    public function getDataDir()
    {
        return $this->container->getParameter('bbapp.data.dir');
    }

    /**
     * @return string
     */
    public function getBaseDir()
    {
        return dirname($this->getBBDir());
    }

    /**
     * Get vendor dir.
     *
     * @return string
     */
    public function getVendorDir()
    {
        return $this->getBaseDir() . DIRECTORY_SEPARATOR . 'vendor';
    }

    /**
     * Returns TRUE if a starting context is defined, FALSE otherwise.
     *
     * @return boolean
     */
    public function hasContext()
    {
        return null !== $this->context && self::DEFAULT_CONTEXT !== $this->context;
    }

    /**
     * Returns the starting context.
     *
     * @return string|NULL
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * @return BBUserToken|null
     */
    public function getBBUserToken()
    {
        $token = $this->getSecurityContext()->getToken();

        if ($token instanceof BBUserToken && $token->isExpired()) {
            $event = new GetResponseEvent(
                $this->getController(),
                $this->getRequest(),
                HttpKernelInterface::MASTER_REQUEST
            );
            $this->getEventDispatcher()->dispatch('frontcontroller.request.logout', $event);
            $token = null;
        }

        return $token instanceof BBUserToken ? $token : null;
    }

    /**
     * Get cache provider from config.
     *
     * @return string Cache provider config name or \BackBee\Cache\DAO\Cache if not found
     */
    public function getCacheProvider()
    {
        $conf = $this->getConfig()->getCacheConfig();

        return isset($conf['provider']) && is_subclass_of($conf['provider'], '\BackBee\Cache\AExtendedCache')
            ? $conf['provider']
            : '\BackBee\Cache\DAO\Cache';
    }

    /**
     * @return Cache|null
     */
    public function getCacheControl()
    {
        return $this->getContainer()->get('cache.control');
    }

    /**
     * @return CacheInterface|null
     */
    public function getBootstrapCache()
    {
        return $this->getContainer()->get('cache.bootstrap');
    }

    public function getCacheDir()
    {
        if (null === $this->container) {
            throw new Exception('Application\'s container is not ready!');
        }

        return $this->getContainer()->getParameter('bbapp.cache.dir');
    }

    /**
     * @return Container
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Get validator service.
     *
     * @return ValidatorInterface|null
     */
    public function getValidator()
    {
        return $this->getContainer()->get('validator');
    }

    /**
     * @return Config
     */
    public function getConfig(): Config
    {
//        if (null === $this->container) {
//            throw new Exception('Application\'s container is not ready!');
//        }

        return $this->container->get('config');
    }

    /**
     * Get current environment.
     *
     * @return string
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * get current configuration directory path
     *
     * @return string
     */
    public function getConfigDir()
    {
        return $this->getRepository() . DIRECTORY_SEPARATOR . 'Config';
    }

    /**
     * get default configuration directory path
     *
     * @return string
     */
    public function getBBConfigDir()
    {
        return $this->getBaseRepository() . DIRECTORY_SEPARATOR . 'Config';
    }

    /**
     * @param string $name
     *
     * @return EntityManager
     */
    public function getEntityManager(string $name = 'default'): EntityManager
    {
        if ($this->getContainer()->getDefinition('em')->isSynthetic()) {
            try {
                $this->initEntityManager();
            } catch (Exception $exception) {
                $this->getLogging()->error(
                    sprintf(
                        '%s : %s :%s',
                        __CLASS__,
                        __FUNCTION__,
                        $exception->getMessage()
                    )
                );
            }
        }

        return $this->getContainer()->get('doctrine')->getManager($name);
    }

    /**
     * @return EventDispatcherInterface
     */
    public function getEventDispatcher(): EventDispatcherInterface
    {
        return $this->getContainer()->get('event.dispatcher');
    }

    /**
     * @return LoggerInterface
     */
    public function getLogging(): LoggerInterface
    {
        return $this->getContainer()->get('logging');
    }

    public function getMediaDir()
    {
        if (null === $this->container) {
            throw new Exception('Application\'s container is not ready!');
        }

        return $this->getContainer()->getParameter('bbapp.media.dir');
    }

    /**
     * @return AbstractRenderer|null
     */
    public function getRenderer()
    {
        return $this->getContainer()->get('renderer');
    }

    /**
     * get current repository directory path
     *
     * @return string
     */
    public function getRepository()
    {
        if (null === $this->repository) {
            $this->repository = $this->getBaseRepository();
            if ($this->hasContext()) {
                $this->repository .= DIRECTORY_SEPARATOR . $this->context;
            }
        }

        return $this->repository;
    }

    /**
     * get default repository directory path
     *
     * @return string
     */
    public function getBaseRepository()
    {
        if (null === $this->baseRepository) {
            $this->baseRepository = $this->getBaseDir() . DIRECTORY_SEPARATOR . 'repository';
        }

        return $this->baseRepository;
    }

    /**
     * Return the classcontent repositories path for this instance.
     *
     * @return array
     */
    public function getClassContentDir()
    {
        if (null === $this->classcontentDir) {
            $this->classcontentDir = [];

            array_unshift($this->classcontentDir, $this->getBBDir() . '/ClassContent');
            array_unshift($this->classcontentDir, $this->getBaseRepository() . '/ClassContent');

            if ($this->hasContext()) {
                array_unshift($this->classcontentDir, $this->getRepository() . '/ClassContent');
            }

            array_map(['BackBee\Util\File\File', 'resolveFilepath'], $this->classcontentDir);
        }

        return $this->classcontentDir;
    }

    /**
     * Push one directory at the end of classcontent dirs.
     *
     * @param string $dir
     *
     * @return ApplicationInterface
     */
    public function pushClassContentDir($dir)
    {
        File::resolveFilepath($dir);

        $classcontentdir = $this->getClassContentDir();
        array_push($classcontentdir, $dir);

        $this->classcontentDir = array_unique($classcontentdir);

        return $this;
    }

    /**
     * Prepend one directory at the beginning of classcontent dirs.
     *
     * @param type $dir
     *
     * @return ApplicationInterface
     */
    public function unshiftClassContentDir($dir)
    {
        File::resolveFilepath($dir);

        $classcontentdir = $this->getClassContentDir();
        array_unshift($classcontentdir, $dir);

        $this->classcontentDir = array_unique($classcontentdir);

        return $this;
    }

    /**
     * Return the resource directories, if undefined, initialized with common resources.
     *
     * @return array The resource directories
     */
    public function getResourceDir()
    {
        if (null === $this->resourceDir) {
            $this->initResourceDir();
        }

        return $this->resourceDir;
    }

    /**
     * Init the default resource directories
     */
    protected function initResourceDir()
    {
        $this->resourceDir = [];

        $this->addResourceDir($this->getBBDir() . '/Resources');

        if (is_dir($this->getBaseRepository() . '/Resources')) {
            $this->addResourceDir($this->getBaseRepository() . '/Resources');
        }

        if (is_dir($this->getBaseRepository() . '/Ressources')) {
            $this->addResourceDir($this->getBaseRepository() . '/Ressources');
        }

        if ($this->hasContext()) {
            if (is_dir($this->getRepository() . '/Resources')) {
                $this->addResourceDir($this->getRepository() . '/Resources');
            }

            if (is_dir($this->getRepository() . '/Resources')) {
                $this->addResourceDir($this->getRepository() . '/Resources');
            }
        }

        array_map(['BackBee\Util\File\File', 'resolveFilepath'], $this->resourceDir);
    }

    /**
     * Push one directory at the end of resources dirs.
     *
     * @param string $dir
     *
     * @return ApplicationInterface
     */
    public function pushResourceDir($dir)
    {
        File::resolveFilepath($dir);

        $resourcedir = $this->getResourceDir();
        array_push($resourcedir, $dir);

        $this->resourceDir = $resourcedir;

        return $this;
    }

    /**
     * Prepend one directory at the begining of resources dirs.
     *
     * @param type $dir
     *
     * @return ApplicationInterface
     */
    public function unshiftResourceDir($dir)
    {
        File::resolveFilepath($dir);

        $resourcedir = $this->getResourceDir();
        array_unshift($resourcedir, $dir);

        $this->resourceDir = $resourcedir;

        return $this;
    }

    /**
     * Prepend one directory of resources.
     *
     * @param String $dir The new resource directory to add
     *
     * @return ApplicationInterface The current BBApplication
     *
     * @throws BBException Occur on invalid path or invalid resource directories
     */
    public function addResourceDir($dir)
    {
        if (null === $this->resourceDir) {
            $this->initResourceDir();
        }

        if (!is_array($this->resourceDir)) {
            throw new BBException(
                'Misconfiguration of the BBApplication : resource dir has to be an array',
                BBException::INVALID_ARGUMENT
            );
        }

        if (!file_exists($dir) || !is_dir($dir)) {
            throw new BBException(
                sprintf('The resource folder `%s` does not exist or is not a directory', $dir),
                BBException::INVALID_ARGUMENT
            );
        }

        array_unshift($this->resourceDir, $dir);

        return $this;
    }

    /**
     * Return the current resource dir (ie the first one in those defined).
     *
     * @return string the file path of the current resource dir
     *
     * @throws BBException Occur when none resource dir is defined
     */
    public function getCurrentResourceDir()
    {
        $dir = $this->getResourceDir();

        if (0 === count($dir)) {
            throw new BBException(
                'Misconfiguration of the BBApplication : none resource dir defined',
                BBException::INVALID_ARGUMENT
            );
        }

        return array_shift($dir);
    }

    /**
     * @return Request
     */
    public function getRequest(): Request
    {
        return $this->container->get('request');
    }

    /**
     * @return UrlGenerator|null
     */
    public function getUrlGenerator()
    {
        return $this->getContainer()->get('rewriting.urlgenerator');
    }

    /**
     * @return SessionInterface|null The session
     */
    public function getSession()
    {
        if (null === $this->getRequest()->getSession()) {
            $session = $this->container->get('bb_session');
            $this->getRequest()->setSession($session);
        }

        return $this->getRequest()->getSession();
    }

    /**
     * @return SecurityContext|null
     */
    public function getSecurityContext()
    {
        return $this->getContainer()->get('security.context');
    }

    /**
     * @return Site|null
     */
    public function getSite()
    {
        return $this->getContainer()->has('site') ? $this->getContainer()->get('site') : null;
    }

    /**
     * @return string
     */
    public function getStorageDir()
    {
        if (null === $this->storageDir) {
            $this->storageDir = $this->container->getParameter('bbapp.data.dir') . DIRECTORY_SEPARATOR . 'Storage';
        }

        return $this->storageDir;
    }

    /**
     * @return string
     */
    public function getTemporaryDir()
    {
        if (null === $this->tmpDir) {
            $this->tmpDir = $this->container->getParameter('bbapp.data.dir') . DIRECTORY_SEPARATOR . 'Tmp';
        }

        return $this->tmpDir;
    }

    /**
     * @return boolean
     */
    public function isReady()
    {
        return $this->isInitialized && null !== $this->container;
    }

    /**
     * @return boolean
     */
    public function isStarted()
    {
        return $this->isStarted;
    }

    public function isClientSAPI()
    {
        return isset($GLOBALS['argv']);
    }

    /**
     * Finds and registers Commands.
     *
     * Override this method if your bundle commands do not follow the conventions:
     *
     * * Commands are in the 'Command' sub-directory
     * * Commands extend Symfony\Component\Console\Command\Command
     *
     * @param Console $console An Application instance
     */
    public function registerCommands(Console $console)
    {
        if (is_dir($dir = $this->getBBDir() . '/Console/Command')) {
            $finder = new Finder();
            $finder->files()->name('*Command.php')->in($dir);
            $ns = 'BackBee\\Console\\Command';

            foreach ($finder as $file) {
                if ($relativePath = $file->getRelativePath()) {
                    $ns .= '\\' . strtr($relativePath, '/', '\\');
                }
                $r = new ReflectionClass($ns . '\\' . $file->getBasename('.php'));
                if (
                    $r->isSubclassOf('BackBee\\Console\\AbstractCommand')
                    && !$r->isAbstract()
                    && !$r->getConstructor()->getNumberOfRequiredParameters()
                ) {
                    $console->add($r->newInstance());
                }
            }
        }

        foreach ($this->getBundles() as $bundle) {
            if (!is_dir($dir = $bundle->getBaseDirectory() . '/Command')) {
                continue;
            }

            $finder = new Finder();
            $finder->files()->name('*Command.php')->in($dir);
            $ns = (new ReflectionClass($bundle))->getNamespaceName() . '\\Command';

            foreach ($finder as $file) {
                if ($relativePath = $file->getRelativePath()) {
                    $ns .= '\\' . strtr($relativePath, '/', '\\');
                }
                $r = new ReflectionClass($ns . '\\' . $file->getBasename('.php'));
                if (
                    $r->isSubclassOf('BackBee\\Console\\AbstractCommand')
                    && !$r->isAbstract()
                    && 0 === $r->getConstructor()->getNumberOfRequiredParameters()
                ) {
                    $instance = $r->newInstance();
                    $instance->setBundle($bundle);
                    $console->add($instance);
                }
            }
        }
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
    public function dump(array $options = [])
    {
        return array_merge(
            $this->dumpDatas,
            [
                'classcontent_directories' => $this->classcontentDir,
                'resources_directories' => $this->resourceDir,
            ]
        );
    }

    /**
     * Restore current service to the dump's state.
     *
     * @param array $dump the dump provided by DumpableServiceInterface::dump() from where we can
     *                    restore current service
     */
    public function restore(ContainerInterface $container, array $dump)
    {
        $this->classcontentDir = $dump['classcontent_directories'];
        $this->resourceDir = $dump['resources_directories'];

        if (isset($dump['date_timezone'])) {
            date_default_timezone_set($dump['date_timezone']);
        }

        if (isset($dump['locale'])) {
            setLocale(LC_ALL, $dump['locale']);
        }

        $this->isRestored = true;
    }

    /**
     * @return boolean true if current service is already restored, otherwise false
     */
    public function isRestored()
    {
        return $this->isRestored;
    }

    /**
     * Initializes application's dependency injection container.
     *
     * @return ApplicationInterface
     */
    private function initContainer()
    {
        $this->container = (new ContainerBuilder($this))->getContainer();

        return $this;
    }

    /**
     * @return ApplicationInterface
     */
    private function initEnvVariables()
    {
        if ($this->isRestored()) {
            return $this;
        }

        $dateConfig = $this->getConfig()->getDateConfig();
        if (false !== $dateConfig && isset($dateConfig['timezone'])) {
            if (false === date_default_timezone_set($dateConfig['timezone'])) {
                throw new Exception(sprintf('Unabled to set default timezone (:%s)', $dateConfig['timezone']));
            }

            $this->dumpDatas['date_timezone'] = $dateConfig['timezone'];
        }

        if (null !== $encoding = $this->getConfig()->getEncodingConfig()) {
            if (array_key_exists('locale', $encoding)) {
                if (false === setLocale(LC_ALL, $encoding['locale'])) {
                    throw new Exception(sprintf('Unabled to setLocal with locale %s', $encoding['locale']));
                }

                $this->dumpDatas['locale'] = $encoding['locale'];
            }
        }

        return $this;
    }

    /**
     * @return ApplicationInterface
     */
    private function initAutoloader()
    {
        if ($this->getAutoloader()->isRestored()) {
            return $this;
        }

        $this->getAutoloader()
            ->register()
            ->registerNamespace('BackBee\Bundle', $this->getBaseDir() . DIRECTORY_SEPARATOR . 'bundle')
            ->registerNamespace(
                'BackBee\Renderer\Helper',
                implode(DIRECTORY_SEPARATOR, [$this->getRepository(), 'Templates', 'helpers'])
            )
            ->registerNamespace('BackBee\Event\Listener', $this->getRepository() . DIRECTORY_SEPARATOR . 'Listener')
            ->registerNamespace('BackBee\Controller', $this->getRepository() . DIRECTORY_SEPARATOR . 'Controller')
            ->registerNamespace('BackBee\Traits', $this->getRepository() . DIRECTORY_SEPARATOR . 'Traits');

        if ($this->hasContext()) {
            $this->getAutoloader()
                ->registerNamespace(
                    'BackBee\Renderer\Helper',
                    implode(DIRECTORY_SEPARATOR, [$this->getBaseRepository(), 'Templates', 'helpers'])
                )
                ->registerNamespace(
                    'BackBee\Event\Listener',
                    $this->getBaseRepository() . DIRECTORY_SEPARATOR . 'Listener'
                )
                ->registerNamespace(
                    'BackBee\Controller',
                    $this->getBaseRepository() . DIRECTORY_SEPARATOR . 'Controller'
                )
                ->registerNamespace('BackBee\Traits', $this->getBaseRepository() . DIRECTORY_SEPARATOR . 'Traits');
        }

        return $this;
    }

    /**
     * register all annotations and init the AnnotationReader
     *
     * @return boolean
     */
    private function initAnnotationReader()
    {
        AnnotationRegistry::registerFile(
            $this->getVendorDir() . '/doctrine/orm/lib/Doctrine/ORM/Mapping/Driver/DoctrineAnnotations.php'
        );

        // annotations require custom autoloading
        AnnotationRegistry::registerAutoloadNamespaces(
            [
                'JMS\Serializer\Annotation' => $this->getVendorDir() . '/jms/serializer/src/',
            ]
        );

        AnnotationRegistry::registerLoader(
            function ($classname) {
                if (0 === strpos($classname, 'BackBee')) {
                    return class_exists($classname);
                }

                if (0 === strpos($classname, 'Symfony\Component\Validator\Constraints')) {
                    return class_exists($classname);
                }

                if (0 === strpos($classname, 'Swagger\Annotations')) {
                    return class_exists($classname);
                }

                return false;
            }
        );
    }

    /**
     * @return ApplicationInterface
     *
     * @throws BBException
     */
    private function initContentWrapper()
    {
        if ($this->getAutoloader()->isRestored()) {
            return $this;
        }

        if (null === $contentwrapperConfig = $this->getConfig()->getContentwrapperConfig()) {
            throw new BBException('None class content wrapper found');
        }

        $namespace = isset($contentwrapperConfig['namespace']) ? $contentwrapperConfig['namespace'] : '';
        $protocol = isset($contentwrapperConfig['protocol']) ? $contentwrapperConfig['protocol'] : '';
        $adapter = isset($contentwrapperConfig['adapter']) ? $contentwrapperConfig['adapter'] : '';

        $this->getAutoloader()->registerStreamWrapper($namespace, $protocol, $adapter);

        return $this;
    }

    /**
     * @return ApplicationInterface
     *
     * @throws BBException
     */
    private function initEntityManager()
    {
        if (!$this->container->getDefinition('em')->isSynthetic()) {
            return;
        }

        if (null === $doctrineConfig = $this->getConfig()->getRawSection('doctrine')) {
            throw new BBException('None database configuration found');
        }

        if (!isset($doctrineConfig['dbal'])) {
            throw new BBException('None dbal configuration found');
        }

        if (!isset($doctrineConfig['dbal']['proxy_ns'])) {
            $doctrineConfig['dbal']['proxy_ns'] = 'Proxies';
        }

        if (!isset($doctrineConfig['dbal']['proxy_dir'])) {
            $doctrineConfig['dbal']['proxy_dir'] = $this->getCacheDir() . '/' . 'Proxies';
        }

        if (isset($doctrineConfig['orm'])) {
            $doctrineConfig['dbal']['orm'] = $doctrineConfig['orm'];
        }

        // Init ORM event
        $r = new ReflectionClass('Doctrine\ORM\Events');
        $definition = new Definition('Doctrine\Common\EventManager');
        $definition->addMethodCall('addEventListener', [$r->getConstants(), new Reference('doctrine.listener')]);
        $this->container->setDefinition('doctrine.event_manager', $definition);

        try {
            $loggerId = 'logging';

            if ($this->isDebugMode()) {
                // doctrine data collector
                $this->getContainer()->get('data_collector.doctrine')->addLogger(
                    'default',
                    $this->getContainer()->get('doctrine.dbal.logger.profiling')
                );
                $loggerId = 'doctrine.dbal.logger.profiling';
            }

            $definition = new Definition(
                'Doctrine\ORM\EntityManager', [
                    $doctrineConfig['dbal'],
                    new Reference($loggerId),
                    new Reference('doctrine.event_manager'),
                    new Reference('service_container'),
                ]
            );
            $definition->setFactory(['BackBee\Util\Doctrine\EntityManagerCreator', 'create']);
            $this->container->setDefinition('em', $definition);

            $this->debug(sprintf('%s(): Doctrine EntityManager initialized', __METHOD__));
        } catch (Exception $e) {
            $this->warning(sprintf('%s(): Cannot initialize Doctrine EntityManager', __METHOD__));
        }

        return $this;
    }

    /**
     * Loads every declared bundles into application.
     *
     * @return ApplicationInterface
     */
    private function initBundles()
    {
        $bundleLoader = $this->getContainer()->get('bundle.loader');
        if (!$bundleLoader->isRestored() && null !== $this->getConfig()->getBundlesConfig()) {
            $bundleLoader->load($this->getConfig()->getBundlesConfig());
        }

        return $this;
    }

    /**
     * Registered function for execution on shutdown.
     * Logs fatal error message if exists.
     */
    public function onFatalError()
    {
        $error = error_get_last();
        if (null !== $error && in_array($error['type'], [E_ERROR, E_RECOVERABLE_ERROR])) {
            $this->error($error['message']);
        }
    }

    /**
     * Get app parameter.
     *
     * @param string|null $parameter
     */
    public function getAppParameter(string $parameter)
    {
        $config = $this->getConfig()->getSection('app');

        return $config[$parameter] ?? null;
    }
}
