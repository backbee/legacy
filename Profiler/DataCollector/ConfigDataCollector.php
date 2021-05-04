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

namespace BackBee\Profiler\DataCollector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use BackBee\BBApplication;

/**
 * Config data collector.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      k.golovin
 */
class ConfigDataCollector extends DataCollector
{
    private $kernel;
    private $name;
    private $version;

    /**
     * Constructor.
     *
     * @param string $name    The name of the application using the web profiler
     * @param string $version The version of the application using the web profiler
     */
    public function __construct($name = null, $version = null)
    {
        $this->name = $name;
        $this->version = $version;
    }

    /**
     * Sets the Kernel associated with this Request.
     *
     * @param HttpKernelInterface $kernel
     */
    public function setKernel(HttpKernelInterface $kernel = null)
    {
        $this->kernel = $kernel;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        $this->data = array(
            'app_name'         => $this->name,
            'app_version'      => $this->version,
            'token'            => $response->headers->get('X-Debug-Token'),
            'BackBee_version'  => BBApplication::VERSION,
            'name'             => $this->kernel->getApplication()->getSite() ? $this->kernel->getApplication()->getSite()->getLabel() : 'n/a',
            'debug'            => isset($this->kernel) ? $this->kernel->getApplication()->isDebugMode() : 'n/a',
            'env'              => isset($this->kernel) ? $this->kernel->getApplication()->getEnvironment() : 'n/a',
            'context'          => isset($this->kernel) ? $this->kernel->getApplication()->getContext() : 'n/a',
            'php_version'      => PHP_VERSION,
            'xdebug_enabled'   => extension_loaded('xdebug'),
            'eaccel_enabled'   => extension_loaded('eaccelerator') && ini_get('eaccelerator.enable'),
            'apc_enabled'      => extension_loaded('apc') && ini_get('apc.enabled'),
            'xcache_enabled'   => extension_loaded('xcache') && ini_get('xcache.cacher'),
            'wincache_enabled' => extension_loaded('wincache') && ini_get('wincache.ocenabled'),
            'bundles'          => array(),
            'sapi_name'        => php_sapi_name(),
        );

        if (isset($this->kernel)) {
            foreach ($this->kernel->getApplication()->getBundles() as $name => $bundle) {
                $this->data['bundles'][$name] = $bundle->getBaseDirectory();
            }
        }
    }

    public function getApplicationName()
    {
        return $this->data['app_name'];
    }

    public function getApplicationVersion()
    {
        return $this->data['app_version'];
    }

    /**
     * Gets the token.
     *
     * @return string The token
     */
    public function getToken()
    {
        return $this->data['token'];
    }

    /**
     * Gets the BackBee version.
     *
     * @return string The BackBee version
     */
    public function getBackBeeVersion()
    {
        return $this->data['BackBee_version'];
    }

    /**
     * Gets the PHP version.
     *
     * @return string The PHP version
     */
    public function getPhpVersion()
    {
        return $this->data['php_version'];
    }

    /**
     * Gets the application name.
     *
     * @return string The application name
     */
    public function getAppName()
    {
        return $this->data['name'];
    }

    /**
     * Returns true if the debug is enabled.
     *
     * @return Boolean true if debug is enabled, false otherwise
     */
    public function isDebug()
    {
        return $this->data['debug'];
    }

    /**
     * Returns true if the XDebug is enabled.
     *
     * @return Boolean true if XDebug is enabled, false otherwise
     */
    public function hasXDebug()
    {
        return $this->data['xdebug_enabled'];
    }

    /**
     * Returns true if EAccelerator is enabled.
     *
     * @return Boolean true if EAccelerator is enabled, false otherwise
     */
    public function hasEAccelerator()
    {
        return $this->data['eaccel_enabled'];
    }

    /**
     * Returns true if APC is enabled.
     *
     * @return Boolean true if APC is enabled, false otherwise
     */
    public function hasApc()
    {
        return $this->data['apc_enabled'];
    }

    /**
     * Returns true if XCache is enabled.
     *
     * @return Boolean true if XCache is enabled, false otherwise
     */
    public function hasXCache()
    {
        return $this->data['xcache_enabled'];
    }

    /**
     * Returns true if WinCache is enabled.
     *
     * @return Boolean true if WinCache is enabled, false otherwise
     */
    public function hasWinCache()
    {
        return $this->data['wincache_enabled'];
    }

    /**
     * Returns true if any accelerator is enabled.
     *
     * @return Boolean true if any accelerator is enabled, false otherwise
     */
    public function hasAccelerator()
    {
        return $this->hasApc() || $this->hasEAccelerator() || $this->hasXCache() || $this->hasWinCache();
    }

    public function getBundles()
    {
        return $this->data['bundles'];
    }

    /**
     * Gets the PHP SAPI name.
     *
     * @return string The environment
     */
    public function getSapiName()
    {
        return $this->data['sapi_name'];
    }

    /**
     * Get the environment name
     *
     * @return string return 'default' if null
     */
    public function getEnvironment()
    {
        return empty($this->data['env']) ? 'default' : $this->data['env'];
    }

    /**
     * Get the context name
     *
     * @return string return the Application context
     */
    public function getContext()
    {
        return $this->data['context'];
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'config';
    }
}
