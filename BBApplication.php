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

namespace BackBee;

use App\Helper\StandaloneHelper;
use BackBee\AutoLoader\AutoLoader;
use BackBee\Cache\CacheInterface;
use BackBee\Cache\DAO\Cache;
use BackBee\Config\Config;
use BackBee\Console\AbstractCommand;
use BackBee\Console\Console;
use BackBee\Controller\FrontController;
use BackBee\DependencyInjection\ContainerBuilder;
use BackBee\DependencyInjection\ContainerInterface;
use BackBee\DependencyInjection\Dumper\DumpableServiceInterface;
use BackBee\DependencyInjection\Dumper\DumpableServiceProxyInterface;
use BackBee\DependencyInjection\Util\ServiceLoader;
use BackBee\Event\Dispatcher;
use BackBee\Event\Event;
use BackBee\Exception\BBException;
use BackBee\Renderer\AbstractRenderer;
use BackBee\Rewriting\UrlGenerator;
use BackBee\Routing\RouteCollection;
use BackBee\Security\SecurityContext;
use BackBee\Security\Token\BBUserToken;
use BackBee\Site\Site;
use BackBee\Util\Doctrine\EntityManagerCreator;
use BackBee\Util\File\File;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\EventManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Exception;
use InvalidArgumentException;
use LogicException;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionException;
use Swift_Mailer;
use Swift_SmtpTransport;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Debug\Debug;
use Symfony\Component\Debug\Exception\ContextErrorException;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Validator\ValidatorInterface;
use Symfony\Component\Yaml\Yaml;
use function array_key_exists;
use function call_user_func_array;
use function count;
use function dirname;
use function in_array;
use function is_array;
use function is_string;

/**
 * Class BBApplication
 *
 * The main BackBee application.
 *
 * @package BackBee
 *
 * @author  c.rouillon <charles.rouillon@lp-digital.fr>
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class BBApplication extends Kernel implements ApplicationInterface, DumpableServiceInterface, DumpableServiceProxyInterface
{
    public const VERSION = '4.2.6';

    /**
     * application's context.
     *
     * @var string
     */
    private $context;

    /**
     * @var bool
     */
    private $isInitialized;

    /**
     * @var false
     */
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
    private $dumpData;

    /**
     * BBApplication constructor.
     *
     * @param null $context
     * @param null $environment
     * @param bool $overwrite_config
     *
     * @throws BBException
     * @throws ContextErrorException
     * @throws DependencyInjection\Exception\ContainerAlreadyExistsException
     * @throws DependencyInjection\Exception\MissingParametersContainerDumpException
     */
    public function __construct($context = null, $environment = null, bool $overwrite_config = false)
    {
        $this->context = $context ?? self::DEFAULT_CONTEXT;
        $this->isInitialized = false;
        $this->isStarted = false;
        $this->overwriteConfig = $overwrite_config;
        $this->isRestored = false;
        $this->environment = is_string($environment) ? $environment : self::DEFAULT_ENVIRONMENT;
        $this->dumpData = [];

        $this->initAnnotationReader();
        $this->initializeBundles();
        $this->initContainer();

        register_shutdown_function([$this, 'onFatalError']);

        if ($this->isDebugMode()) {
            Debug::enable();
        }

        try {
            $this->initEnvVariables();
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

        $this->initAutoloader();
        $this->initContentWrapper();

        try {
            $this->initEntityManager();
        } catch (Exception $exception) {
            $this->getLogging()->notice(
                sprintf('BackBee initialized without EntityManager: %s', $exception->getMessage())
            );
        }

        $this->initBundles();

        if ($this->isRestored() === false) {
            $this->initializeApp();
        }

        if (!$this->getContainer()->has('em')) {
            $this->getLogging()->debug(
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
            if ($this->getEntityManager() === null) {
                throw new InvalidArgumentException(
                    'Unable to initialize security context, did you try to activate ACL voter without database connection?'
                );
            }

            throw $ex;
        }

        $this->getLogging()->debug(
            sprintf(
                'BBApplication (v.%s) initialization with context `%s`, debugging set to %s',
                self::VERSION,
                $this->context,
                var_export($this->debug, true)
            )
        );
        $this->getLogging()->debug(sprintf('  - Base directory set to `%s`', $this->getBaseDir()));
        $this->getLogging()->debug(sprintf('  - Repository directory set to `%s`', $this->getRepository()));

        $this->isInitialized = true;

        // trigger bbapplication.init
        $this->getEventDispatcher()->dispatch('bbapplication.init', new Event($this));

        // Initialize mailer.
        $this->initializeMailer();

        parent::__construct($environment, $this->isDebugMode());
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
     * Get mailer.
     *
     * @return void
     */
    public function initializeMailer(): void
    {
        if (($config = $this->getConfig()->getSection('mailer')) && null === $this->getContainer()->get('mailer')) {
            $transport = Swift_SmtpTransport::newInstance(
                $config['server'] ?? '',
                $config['port'] ?? '',
                $config['encryption'] ?? null
            );

            $transport->setUsername($config['username'] ?? '')->setPassword($config['password'] ?? '');
            $this->getContainer()->set('mailer', Swift_Mailer::newInstance($transport));
        }
    }

    /**
     * Get mailer service.
     *
     * @return null|\Swift_Mailer
     */
    public function getMailer(): ?Swift_Mailer
    {
        return $this->container->get('mailer');
    }

    /**
     * {@inheritDoc}
     */
    public function isDebugMode()
    {
        $debug = $this->debug;
        if ($this->getContainer() !== null && $this->getContainer()->hasParameter('debug')) {
            $debug = $this->getContainer()->getParameter('debug');
        }

        return $debug;
    }

    /**
     * {@inheritDoc}
     */
    public function isOverridedConfig(): bool
    {
        return $this->overwriteConfig;
    }

    /**
     * @param string $name
     *
     * @return object|null
     */
    public function getBundle($name, $first = true)
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
    public function getBundles(): array
    {
        $bundles = [];
        foreach ($this->getContainer()->findTaggedServiceIds('bundle') as $id => $data) {
            $bundles[] = $this->getContainer()->get($id);
        }

        return $bundles;
    }

    /**
     * Start BBApp.
     *
     * @param Site|null $site
     *
     * @throws Controller\Exception\FrontControllerException
     */
    public function start(Site $site = null): void
    {
        if ($this->getEntityManager() === null) {
            throw new LogicException('Cannot start BackBee without database connection');
        }

        if ($site === null) {
            $site = $this->getEntityManager()->getRepository(Site::class)->findOneBy([]);
        }

        if ($site !== null) {
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
    public function getBBDir(): string
    {
        return __DIR__;
    }

    /**
     * {@inheritDoc}
     */
    public function getDataDir(): string
    {
        return $this->container->getParameter('bbapp.data.dir');
    }

    /**
     * {@inheritDoc}
     */
    public function getBaseDir(): string
    {
        return dirname($this->getBBDir());
    }

    /**
     * Get vendor dir.
     *
     * @return string
     */
    public function getVendorDir(): string
    {
        return $this->getBaseDir() . DIRECTORY_SEPARATOR . 'vendor';
    }

    /**
     * Get app dir.
     *
     * @return string
     */
    public function getAppDir(): string
    {
        return StandaloneHelper::appDir();
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
     * {@inheritDoc}
     */
    public function getContext(): string
    {
        return $this->context;
    }

    /**
     * Get BackBee user token.
     *
     * @return BBUserToken|null
     */
    public function getBBUserToken(): ?BBUserToken
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

    /**
     * {@inheritDoc}
     */
    public function getCacheDir(): string
    {
        return $this->getBaseDir() . DIRECTORY_SEPARATOR . 'cache';
    }

    /**
     * {@inheritdoc}
     */
    public function getLogDir(): string
    {
        return $this->getBaseDir() . DIRECTORY_SEPARATOR . 'log';
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
     * {@inheritDoc}
     */
    public function getConfig(): Config
    {
        return $this->container->get('config');
    }

    /**
     * {@inheritDoc}
     */
    public function getConfigDir(): string
    {
        return $this->getRepository() . DIRECTORY_SEPARATOR . 'Config';
    }

    /**
     * get default configuration directory path
     *
     * @return string
     */
    public function getBBConfigDir(): string
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
                        '%s : %s : %s',
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
     * @return Dispatcher
     */
    public function getEventDispatcher(): Dispatcher
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
     * @return AbstractRenderer
     */
    public function getRenderer(): AbstractRenderer
    {
        return $this->getContainer()->get('renderer');
    }

    /**
     * {@inheritDoc}
     */
    public function getRepository(): string
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
     * {@inheritDoc}
     */
    public function getBaseRepository(): string
    {
        if ($this->baseRepository === null) {
            $this->baseRepository = $this->getBaseDir() . DIRECTORY_SEPARATOR . 'repository';
        }

        return $this->baseRepository;
    }

    /**
     * {@inheritDoc}
     */
    public function getClassContentDir(): array
    {
        if ($this->classcontentDir === null) {
            $this->classcontentDir = [];

            array_unshift($this->classcontentDir, $this->getBBDir() . '/ClassContent');
            array_unshift($this->classcontentDir, $this->getBaseRepository() . '/ClassContent');

            if ($this->hasContext()) {
                array_unshift($this->classcontentDir, $this->getRepository() . '/ClassContent');
            }

            array_map([File::class, 'resolveFilepath'], $this->classcontentDir);
        }

        //dump($this->classcontentDir);

        return $this->classcontentDir;
    }

    /**
     * Push one directory at the end of class content dirs.
     *
     * @param string $dir
     *
     * @return BBApplication
     */
    public function pushClassContentDir(string $dir): self
    {
        File::resolveFilepath($dir);

        $classContentDir = $this->getClassContentDir();
        $classContentDir[] = $dir;

        $this->classcontentDir = array_unique($classContentDir);

        return $this;
    }

    /**
     * Prepend one directory at the beginning of class content dirs.
     *
     * @param string $dir
     *
     * @return BBApplication
     */
    public function unshiftClassContentDir(string $dir): self
    {
        File::resolveFilepath($dir);

        $classContentDir = $this->getClassContentDir();
        array_unshift($classContentDir, $dir);

        $this->classcontentDir = array_unique($classContentDir);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getResourceDir(): array
    {
        if (null === $this->resourceDir) {
            $this->initResourceDir();
        }

        return $this->resourceDir;
    }

    /**
     * Init the default resource directories
     */
    protected function initResourceDir(): void
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

        array_map([File::class, 'resolveFilepath'], $this->resourceDir);
    }

    /**
     * Push one directory at the end of resources dirs.
     *
     * @param string $dir
     *
     * @return ApplicationInterface
     */
    public function pushResourceDir(string $dir): ApplicationInterface
    {
        File::resolveFilepath($dir);

        $resourcedir = $this->getResourceDir();
        array_push($resourcedir, $dir);

        $this->resourceDir = $resourcedir;

        return $this;
    }

    /**
     * Prepend one directory at the beginning of resources dirs.
     *
     * @param string $dir
     *
     * @return ApplicationInterface
     */
    public function unshiftResourceDir(string $dir): ApplicationInterface
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
     * @param string $dir The new resource directory to add
     *
     * @return BBApplication The current BBApplication
     *
     * @throws BBException Occur on invalid path or invalid resource directories
     */
    public function addResourceDir(string $dir): self
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
    public function getCurrentResourceDir(): string
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
     * @return UrlGenerator
     */
    public function getUrlGenerator(): UrlGenerator
    {
        return $this->getContainer()->get('rewriting.urlgenerator');
    }

    /**
     * @return SessionInterface The session
     */
    public function getSession(): SessionInterface
    {
        if (null === $this->getRequest()->getSession()) {
            $session = $this->container->get('bb_session');
            $this->getRequest()->setSession($session);
        }

        return $this->getRequest()->getSession();
    }

    /**
     * @return SecurityContext
     */
    public function getSecurityContext(): SecurityContext
    {
        return $this->getContainer()->get('security.context');
    }

    /**
     * @return Site|null
     */
    public function getSite(): ?Site
    {
        return $this->getContainer()->has('site') ? $this->getContainer()->get('site') : null;
    }

    /**
     * @return string
     */
    public function getStorageDir(): string
    {
        if (null === $this->storageDir) {
            $this->storageDir = $this->container->getParameter('bbapp.data.dir') . DIRECTORY_SEPARATOR . 'Storage';
        }

        return $this->storageDir;
    }

    /**
     * @return string
     */
    public function getTemporaryDir(): string
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
     * {@inheritDoc}
     */
    public function isStarted(): bool
    {
        return $this->isStarted;
    }

    /**
     * {@inheritDoc}
     */
    public function isClientSAPI(): bool
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
     *
     * @throws ReflectionException
     */
    public function registerCommands(Console $console)
    {
        if (is_dir($dir = $this->getBBDir() . '/Console/Command')) {
            $finder = new Finder();
            $finder->files()->name('*Command.php')->in($dir);
            $ns = 'BackBee\\Console\\Command';

            foreach ($finder as $file) {
                if ($relativePath = $file->getRelativePath()) {
                    $ns .= '\\' . str_replace('/', '\\', $relativePath);
                }
                $r = new ReflectionClass($ns . '\\' . $file->getBasename('.php'));
                if (
                    $r->isSubclassOf(AbstractCommand::class)
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
                    $ns .= '\\' . str_replace('/', '\\', $relativePath);
                }
                $r = new ReflectionClass($ns . '\\' . $file->getBasename('.php'));
                if (
                    $r->isSubclassOf(AbstractCommand::class)
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
     * @return array contains every data required by this service to be restored at the same state
     */
    public function dump(array $options = []): array
    {
        return array_merge(
            $this->dumpData,
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
    public function restore(ContainerInterface $container, array $dump): void
    {
        $this->classcontentDir = $dump['classcontent_directories'];
        $this->resourceDir = $dump['resources_directories'];

        if (isset($dump['date_timezone'])) {
            date_default_timezone_set($dump['date_timezone']);
        }

        if (isset($dump['locale'])) {
            setLocale(LC_ALL, $dump['locale']);
            setLocale(LC_NUMERIC, 'en_US.UTF-8');
        }

        $this->isRestored = true;
    }

    /**
     * @return bool true if current service is already restored, otherwise false
     */
    public function isRestored(): bool
    {
        return $this->isRestored;
    }

    /**
     * Initializes application's dependency injection container.
     *
     * @return void
     * @throws DependencyInjection\Exception\ContainerAlreadyExistsException
     * @throws DependencyInjection\Exception\MissingParametersContainerDumpException
     */
    private function initContainer(): void
    {
        $this->container = (new ContainerBuilder($this))->getContainer();
    }

    /**
     * @throws Exception
     */
    private function initEnvVariables(): void
    {
        if ($this->isRestored()) {
            return;
        }

        $dateConfig = $this->getContainer()->getParameter('date');
        if (false !== $dateConfig && isset($dateConfig['timezone'])) {
            if (false === date_default_timezone_set($dateConfig['timezone'])) {
                throw new Exception(sprintf('Unabled to set default timezone (:%s)', $dateConfig['timezone']));
            }

            $this->dumpData['date_timezone'] = $dateConfig['timezone'];
        }

        if (
            (null !== $encoding = $this->getContainer()->getParameter('encoding')) &&
            array_key_exists('locale', $encoding)
        ) {
            if (false === setLocale(LC_ALL, $encoding['locale'])) {
                throw new Exception(sprintf('Unabled to setLocal with locale %s', $encoding['locale']));
            }

            setLocale(LC_NUMERIC, 'en_US.UTF-8');
            $this->dumpData['locale'] = $encoding['locale'];
        }
    }

    /**
     * @return void
     */
    private function initAutoloader(): void
    {
        if ($this->getAutoloader()->isRestored()) {
            return;
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
    }

    /**
     * register all annotations and init the AnnotationReader
     *
     * @return void
     */
    private function initAnnotationReader(): void
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
                if (0 === strncmp($classname, 'BackBee', 7)) {
                    return class_exists($classname);
                }

                if (0 === strncmp($classname, 'Symfony\Component\Validator\Constraints', 39)) {
                    return class_exists($classname);
                }

                if (0 === strncmp($classname, 'Swagger\Annotations', 19)) {
                    return class_exists($classname);
                }

                return false;
            }
        );
    }

    /**
     * @return void
     *
     * @throws BBException
     */
    private function initContentWrapper(): void
    {
        if ($this->getAutoloader()->isRestored()) {
            return;
        }

        if (null === $contentWrapperConfig = $this->getConfig()->getContentwrapperConfig()) {
            throw new BBException('None class content wrapper found');
        }

        $namespace = $contentWrapperConfig['namespace'] ?? '';
        $protocol = $contentWrapperConfig['protocol'] ?? '';
        $adapter = $contentWrapperConfig['adapter'] ?? '';

        $this->getAutoloader()->registerStreamWrapper($namespace, $protocol, $adapter);
    }

    /**
     * @return void
     *
     * @throws BBException
     */
    private function initEntityManager(): void
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
        $r = new ReflectionClass(Events::class);
        $definition = new Definition(EventManager::class);
        $definition->addMethodCall('addEventListener', [$r->getConstants(), new Reference('doctrine.listener')]);
        $this->container->setDefinition('doctrine.event_manager', $definition);

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
            EntityManager::class,
            [
                $doctrineConfig['dbal'],
                new Reference($loggerId),
                new Reference('doctrine.event_manager'),
                new Reference('service_container'),
            ]
        );

        try {

            $definition->setFactory([EntityManagerCreator::class, 'create']);
            $this->container->setDefinition('em', $definition);

            $this->getLogging()->debug(sprintf('%s(): Doctrine EntityManager initialized', __METHOD__));
        } catch (Exception $exception) {
            $this->getLogging()->warning(
                sprintf('%s(): Cannot initialize Doctrine EntityManager: %s', __METHOD__, $exception->getMessage())
            );
        }
    }

    /**
     * Loads every declared bundles into application.
     *
     * @return void
     */
    private function initBundles(): void
    {
        $bundleLoader = $this->getContainer()->get('bundle.loader');

        try {
            $bundles = $this->container->getParameter('bundles');

            // Init BackBee bundles.
            if ($bundles !== null && !$bundleLoader->isRestored()) {
                $bundleLoader->load($bundles);
            }

            // Init kernel bundles.
            foreach ($this->getKernelBundles() as $bundle) {
                $bundle->setContainer($this->container);
                $bundle->boot();
            }
        } catch (Exception $exception) {
            $this->getLogging()->error(
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
     * Registered function for execution on shutdown.
     * Logs fatal error message if exists.
     */
    public function onFatalError(): void
    {
        $error = error_get_last();
        if (null !== $error && in_array($error['type'], [E_ERROR, E_RECOVERABLE_ERROR], true)) {
            $this->getLogging()->error($error['message']);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function registerBundles(): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        try {
            $loader->load($this->getBBConfigDir() . DIRECTORY_SEPARATOR . 'config.yml');
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

    /**
     * {@inheritDoc}
     */
    public function getKernelBundles(): array
    {
        return $this->bundles;
    }

    /**
     * Initialize application.
     */
    public function initializeApp(): void
    {
        $eventsFilePath = StandaloneHelper::configDir() . DIRECTORY_SEPARATOR . 'events.yml';

        if (is_file($eventsFilePath) && is_readable($eventsFilePath)) {
            $events = Yaml::parse(file_get_contents($eventsFilePath));

            if (is_array($events) === true) {
                $this->getEventDispatcher()->addListeners($events);
            }
        }

        $serviceFilePath = StandaloneHelper::configDir() . DIRECTORY_SEPARATOR . 'services.yml';

        if (is_file($serviceFilePath) && is_readable($serviceFilePath)) {
            ServiceLoader::loadServicesFromYamlFile($this->container, StandaloneHelper::configDir());
        }
    }
}
