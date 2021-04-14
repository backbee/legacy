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

namespace BackBee\Config;

use BackBee\ApplicationInterface;
use BackBee\Cache\CacheInterface;
use BackBee\Config\Exception\InvalidBaseDirException;
use BackBee\Config\Exception\InvalidConfigException;
use BackBee\DependencyInjection\Container;
use BackBee\DependencyInjection\DispatchTagEventInterface;
use BackBee\DependencyInjection\Dumper\DumpableServiceInterface;
use BackBee\Exception\InvalidArgumentException;
use BackBee\Util\Collection\Collection;
use BackBee\Util\File\File;
use DateTime;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use function array_key_exists;
use function count;
use function in_array;
use function is_array;
use function unserialize;

/**
 * Class Config
 *
 * A set of configuration parameters store in a yaml file
 * The parameters had to be filtered by section
 * Note that parameters and services will be set only if setContainer() is called.
 *
 * @package BackBee\Config
 *
 * @author  c.rouillon <charles.rouillon@lp-digital.fr>
 * @author  e.chau <eric.chau@lp-digital.fr>
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class Config implements DispatchTagEventInterface, DumpableServiceInterface
{
    /**
     * Config proxy classname.
     *
     * @var string
     */
    public const CONFIG_PROXY_CLASSNAME = ConfigProxy::class;

    /**
     * Default config file to look for.
     *
     * @var string
     */
    public const CONFIG_FILE = 'config';

    /**
     * System extension config file.
     *
     * @var string
     */
    public const EXTENSION = 'yml';

    /**
     * The base directory to looking for configuration files.
     *
     * @var string
     */
    protected $basedir;

    /**
     * The extracted configuration parameters from the config file.
     *
     * @var array
     */
    protected $raw_parameters;

    /**
     * The already compiled parameters.
     *
     * @var array
     */
    protected $parameters;

    /**
     * The optional cache system.
     *
     * @var CacheInterface
     */
    protected $cache;

    /**
     * The service container.
     *
     * @var Container
     */
    protected $container;

    /**
     * Application's environment.
     *
     * @var string
     */
    protected $environment = ApplicationInterface::DEFAULT_ENVIRONMENT;

    /**
     * Is debug mode enabled.
     *
     * @var boolean
     */
    protected $debug = false;

    /**
     * Debug info.
     *
     * Only populated in dev environment
     *
     * @var array
     */
    protected $debug_data = array();

    /**
     * list of yaml filename we don't want to parse and load.
     *
     * @var array
     */
    protected $yml_names_to_ignore;

    /**
     * represents if current service has been already restored or not.
     *
     * @var boolean
     */
    protected $is_restored;

    /**
     * Class constructor.
     *
     * @param string              $basedir       The base directory in which look for config files
     * @param CacheInterface|null $cache         Optional cache system
     * @param Container|null      $container     The BackBee Container
     * @param boolean             $debug         The debug mode
     * @param array               $yml_to_ignore List of yaml filename to ignore form loading/parsing process
     *
     * @throws InvalidArgumentException
     * @throws InvalidBaseDirException
     * @throws InvalidConfigException
     */
    public function __construct(
        string $basedir,
        CacheInterface $cache = null,
        Container $container = null,
        bool $debug = false,
        array $yml_to_ignore = array()
    ) {
        $this->basedir = $basedir;
        $this->raw_parameters = array();
        $this->cache = $cache;
        $this->debug = $debug;
        $this->yml_names_to_ignore = $yml_to_ignore;
        $this->is_restored = false;

        $this->setContainer($container)->extend();
    }

    /**
     * Magic function to get configuration section
     * The called method has to match the pattern getSectionConfig()
     * for example getDoctrineConfig() aliases getSection('doctrine').
     *
     * @access public
     *
     * @param string $name      The name of the called method
     * @param array  $arguments The arguments passed to the called method
     *
     * @return array The configuration section if exists NULL else
     */
    public function __call(string $name, array $arguments)
    {
        $result = null;
        if (1 === preg_match('/get([a-z]+)config/i', strtolower($name), $sections)) {
            $section = $this->getSection($sections[1]);

            if (0 === count($arguments)) {
                $result = $section;
            } elseif (true === array_key_exists($arguments[0], $section)) {
                $result = $section[$arguments[0]];
            }
        }

        return $result;
    }

    /**
     * Set the service container to be able to parse parameter and service in config
     * Resets the compiled parameters array.
     *
     * @param Container|null $container
     *
     * @return Config
     */
    public function setContainer(Container $container = null): Config
    {
        $this->container = $container;
        $this->parameters = array();

        return $this;
    }

    /**
     * Set the cache used for the configuration
     *
     * @param CacheInterface $cache
     *
     * @return Config
     */
    public function setCache(CacheInterface $cache): Config
    {
        $this->cache = $cache;

        return $this;
    }

    /**
     * Get debug info.
     *
     * Populated only in dev env
     *
     * @return array
     */
    public function getDebugData(): array
    {
        return $this->debug_data;
    }

    /**
     * Add more yaml filename to ignore when we will try to find every yaml files of a directory.
     *
     * @param string|array $filename yaml filename(s) to ignore
     */
    public function addYamlFilenameToIgnore($filename): void
    {
        $this->yml_names_to_ignore = array_unique(array_merge($this->yml_names_to_ignore, (array)$filename));
    }

    /**
     * Returns, if exists, the raw parameter section, null otherwise.
     *
     * @param string $section
     *
     * @return mixed|null
     */
    public function getRawSection($section = null)
    {
        if (null === $section) {
            return $this->raw_parameters;
        }

        if (array_key_exists($section, $this->raw_parameters)) {
            return $this->raw_parameters[$section];
        }
    }

    /**
     * Returns all raw parameter sections.
     *
     * @return array
     */
    public function getAllRawSections(): ?array
    {
        return $this->getRawSection();
    }

    /**
     * Returns, if exists, the parameter section.
     *
     * @param string $section
     *
     * @return array|null
     */
    public function getSection($section = null): ?array
    {
        if (null === $this->container) {
            return $this->getRawSection($section);
        }

        return $this->compileParameters($section);
    }

    /**
     * Returns all sections.
     *
     * @return array
     */
    public function getAllSections(): ?array
    {
        return $this->getSection();
    }

    /**
     * Delete section by name and its parameters.
     *
     * @param string $section the name of the section you want to delete
     *
     * @return self
     */
    public function deleteSection(string $section): Config
    {
        unset($this->raw_parameters[$section], $this->parameters[$section]);

        return $this;
    }

    /**
     * Delete every sections of current Config.
     *
     * @return self
     */
    public function deleteAllSections(): Config
    {
        $this->raw_parameters = [];
        $this->parameters = [];

        return $this;
    }

    /**
     * Set environment context.
     *
     * @param string $env
     *
     * @return self
     */
    public function setEnvironment(string $env): Config
    {
        $this->environment = $env;

        return $this;
    }

    /**
     * Set debug mode.
     *
     * @param boolean $debug
     *
     * @return self
     */
    public function setDebug(bool $debug): Config
    {
        $this->debug = $debug;

        return $this;
    }

    /**
     * Checks if the key exists in the parameter section.
     *
     * @param string $section
     * @param string $key
     *
     * @return boolean
     */
    public function sectionHasKey(string $section, string $key): bool
    {
        return (isset($this->raw_parameters[$section])
            && is_array($this->raw_parameters[$section])
            && array_key_exists($key, $this->raw_parameters[$section])
        );
    }

    /**
     * Sets a parameter section.
     *
     * @param string  $section
     * @param array   $config
     * @param boolean $overwrite
     *
     * @return Config The current config object
     */
    public function setSection(string $section, array $config, bool $overwrite = false): Config
    {
        if (false === $overwrite && array_key_exists($section, $this->raw_parameters)) {
            $this->raw_parameters[$section] = Collection::array_merge_assoc_recursive(
                $this->raw_parameters[$section],
                $config
            );
        } else {
            $this->raw_parameters[$section] = $config;
        }

        if (array_key_exists($section, $this->parameters)) {
            unset($this->parameters[$section]);
        }

        return $this;
    }

    /**
     * Extends the current instance with a new base directory.
     *
     * @param null $basedir Optional base directory
     * @param bool $overwrite
     *
     * @return Config
     * @throws InvalidArgumentException
     * @throws InvalidBaseDirException
     * @throws InvalidConfigException
     */
    public function extend($basedir = null, bool $overwrite = false): self
    {
        if (null === $basedir) {
            $basedir = $this->basedir;
        }

        $basedir = File::realpath($basedir);

        if (false === $this->loadFromCache($basedir)) {
            $this->loadFromBaseDir($basedir, $overwrite);
            $this->saveToCache($basedir);
        }

        if (
            !empty($this->environment)
            && false === strpos($this->environment, $basedir)
            && is_dir($basedir . DIRECTORY_SEPARATOR . $this->environment)
        ) {
            $this->extend($basedir . DIRECTORY_SEPARATOR . $this->environment, $overwrite);
        }

        return $this;
    }

    /**
     * Returns base directory.
     *
     * @return string absolute path to current Config base directory
     */
    public function getBaseDir(): string
    {
        return $this->basedir;
    }

    /**
     * @see \BackBee\DependencyInjection\DispatchTagEventInterface::needDispatchEvent
     */
    public function needDispatchEvent(): bool
    {
        return true;
    }

    /**
     * Returns the namespace of the class proxy to use or null if no proxy is required.
     *
     * @return string|null the namespace of the class proxy to use on restore or null if no proxy required
     */
    public function getClassProxy()
    {
        return self::CONFIG_PROXY_CLASSNAME;
    }

    /**
     * Dumps current service state so we can restore it later by calling DumpableServiceInterface::restore()
     * with the dump array produced by this method.
     *
     * @return array contains every data required by this service to be restored at the same state
     */
    public function dump(array $options = array()): array
    {
        return [
            'basedir' => $this->basedir,
            'raw_parameters' => $this->raw_parameters,
            'environment' => $this->environment,
            'debug' => $this->debug,
            'yml_names_to_ignore' => $this->yml_names_to_ignore,
            'has_cache' => null !== $this->cache,
            'has_container' => null !== $this->container,
        ];
    }

    /**
     * @return boolean true if current service is already restored, otherwise false
     */
    public function isRestored()
    {
        return $this->is_restored;
    }

    /**
     * If a cache system is defined, try to load a cache for the current basedir.
     *
     * @param string $basedir The base directory
     *
     * @return boolean Returns TRUE if a valid cache has been found, FALSE otherwise
     * @throws InvalidArgumentException
     */
    private function loadFromCache($basedir)
    {
        if (true === $this->debug) {
            return false;
        }

        if (null === $this->cache) {
            return false;
        }

        $cached_parameters = $this->cache->load($this->getCacheId($basedir), false, $this->getCacheExpire($basedir));
        if (false === $cached_parameters) {
            return false;
        }

        $parameters = @unserialize($cached_parameters);
        if (!is_array($parameters)) {
            return false;
        }

        foreach ($parameters as $section => $data) {
            $this->setSection($section, $data, true);
        }

        return true;
    }

    /**
     * Saves the parameters in cache system if defined.
     *
     * @param string $basedir The base directory
     *
     * @return bool Returns TRUE if a valid cache has been saved, FALSE otherwise
     */
    private function saveToCache(string $basedir)
    {
        if (true === $this->debug) {
            return false;
        }

        if (null !== $this->cache) {
            return $this->cache->save(
                $this->getCacheId($basedir),
                serialize($this->raw_parameters),
                null,
                null
            );
        }

        return false;
    }

    /**
     * Returns a cache expiration date time (the newer modification date of files).
     *
     * @param string $basedir The base directory
     *
     * @return DateTime
     * @throws InvalidArgumentException
     */
    private function getCacheExpire(string $basedir): DateTime
    {
        $expire = 0;

        foreach ($this->getYmlFiles($basedir) as $file) {
            $stat = @stat($file);
            if ($expire < $stat['mtime']) {
                $expire = $stat['mtime'];
            }
        }

        $date = new DateTime();
        if (0 !== $expire) {
            $date->setTimestamp($expire);
        }

        return $date;
    }

    /**
     * Returns a cache id for the current instance.
     *
     * @param string $basedir The base directory
     *
     * @return string
     */
    private function getCacheId(string $basedir): string
    {
        return md5('config-' . $basedir . $this->environment);
    }

    /**
     * Returns an array of YAML files in the directory.
     *
     * @param string $basedir The base directory
     *
     * @return array
     *
     * @throws InvalidArgumentException
     */
    private function getYmlFiles(string $basedir): array
    {
        $ymlFiles = File::getFilesByExtension($basedir, self::EXTENSION);

        $defaultFile = $basedir . DIRECTORY_SEPARATOR . self::CONFIG_FILE . '.' . self::EXTENSION;

        if (is_file($defaultFile) && 1 < count($ymlFiles)) {
            // Ensure that config.yml is the first one
            $ymlFiles = array_diff($ymlFiles, array($defaultFile));
            array_unshift($ymlFiles, $defaultFile);
        }

        foreach ($ymlFiles as &$file) {
            $name = basename($file);
            if (in_array(substr($name, 0, strrpos($name, '.')), $this->yml_names_to_ignore, true)) {
                $file = null;
            }
        }

        return array_filter($ymlFiles);
    }

    /**
     * Loads the config files from the base directory.
     *
     * @param string $basedir The base directory
     * @param bool   $overwrite
     *
     * @throws InvalidConfigException
     * @throws InvalidArgumentException
     */
    private function loadFromBaseDir($basedir, $overwrite = false)
    {
        foreach ($this->getYmlFiles($basedir) as $filename) {
            $this->loadFromFile($filename, $overwrite);
        }
    }

    /**
     * Try to parse a yaml config file.
     *
     * @param string $filename
     * @param bool   $overwrite
     *
     * @throws InvalidConfigException Occurs when the file can't be parsed
     */
    private function loadFromFile(string $filename, bool $overwrite = false): void
    {
        try {
            $yamlData = Yaml::parse(file_get_contents($filename));

            if (is_array($yamlData)) {
                if (true === $this->debug) {
                    $this->debug_data[$filename] = $yamlData;
                }

                if (self::CONFIG_FILE . '.' . self::EXTENSION === basename($filename) ||
                    self::CONFIG_FILE . '.' . $this->environment . '.' . self::EXTENSION === basename($filename)) {
                    foreach ($yamlData as $component => $config) {
                        if (!is_array($config)) {
                            $this->container->get('logger')->error(
                                'Bad configuration, array expected, given : ' . $config
                            );
                        }
                        $this->setSection($component, $config, $overwrite);
                    }
                } else {
                    $this->setSection(basename($filename, '.' . self::EXTENSION), $yamlData, $overwrite);
                }
            }
        } catch (ParseException $e) {
            throw new InvalidConfigException($e->getMessage(), null, $e, $e->getParsedFile(), $e->getParsedLine());
        }
    }

    /**
     * Replace services and container parameters keys by their values for the whole config.
     *
     * @return array
     */
    private function compileAllParameters(): array
    {
        foreach (array_keys($this->raw_parameters) as $section) {
            $this->parameters[$section] = $this->compileParameters($section);
        }

        return $this->parameters;
    }

    /**
     * Replace services and container parameters keys by their values for the provided section.
     *
     * @param string|null $section The selected configuration section, can be null
     *
     * @return array|void
     */
    private function compileParameters($section = null)
    {
        if (null === $section) {
            return $this->compileAllParameters();
        }

        if (!array_key_exists($section, $this->raw_parameters)) {
            return;
        }

        if (!array_key_exists($section, $this->parameters)) {
            $value = $this->raw_parameters[$section];
            if (is_array($value)) {
                array_walk_recursive($value, array($this->container, 'getContainerValues'));
            } else {
                $this->container->getContainerValues($value);
            }
            $this->parameters[$section] = $value;
        }

        return $this->parameters[$section];
    }
}
