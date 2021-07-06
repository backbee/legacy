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

namespace BackBee\Routing;

use BackBee\ApplicationInterface;
use BackBee\DependencyInjection\ContainerInterface;
use BackBee\DependencyInjection\Dumper\DumpableServiceInterface;
use BackBee\DependencyInjection\Dumper\DumpableServiceProxyInterface;
use BackBee\Site\Site;
use BackBee\Util\File\File;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\RouteCollection as sfRouteCollection;
use function array_key_exists;

/**
 * A RouteCollection represents a set of Route instances.
 *
 * When adding a route at the end of the collection, an existing route
 * with the same name is removed first. So there can only be one route
 * with a given name.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class RouteCollection extends sfRouteCollection implements DumpableServiceInterface, DumpableServiceProxyInterface
{
    const DEFAULT_URL = 0;
    const IMAGE_URL = 1;
    const MEDIA_URL = 2;
    const RESOURCE_URL = 3;

    /**
     * The current application.
     *
     * @var ApplicationInterface
     */
    protected $application;

    /**
     * A message logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * The raw routes of the collection.
     *
     * @var array
     */
    private $rawRoutes;

    /**
     * URL prefixes for resources.
     *
     * @var atring[]
     */
    protected $uriPrefixes;

    /**
     * The default protocol scheme for this instance.
     *
     * @var string
     */
    private $defaultScheme;

    /**
     * Is the collection restored?
     *
     * @var boolean
     */
    private $isRestored;

    /**
     * Class constructor.
     *
     * @param ApplicationInterface|null $application Optional, the current application.
     * @param LoggerInterface|null      $logger      Optional, a message logger
     */
    public function __construct(ApplicationInterface $application = null, LoggerInterface $logger = null)
    {
        $this->application = $application;
        $this->logger = $logger;
        $this->isRestored = false;
        $this->rawRoutes = [];
        $this->uriPrefixes = [];
        $this->defaultScheme = '';

        if (
            null !== $this->application &&
            null !== $container = $this->application->getContainer()
        ) {
            $this->readFromContainer($container);
        }
    }

    /**
     * Reads varibles from the applicative container.
     *
     * @param ContainerInterface $container
     */
    private function readFromContainer(ContainerInterface $container)
    {
        $this->setValueIfParameterExists(
            $container,
            $this->uriPrefixes[self::IMAGE_URL],
            'bbapp.routing.image_uri_prefix'
        )
            ->setValueIfParameterExists(
                $container,
                $this->uriPrefixes[self::MEDIA_URL],
                'bbapp.routing.media_uri_prefix'
            )
            ->setValueIfParameterExists(
                $container,
                $this->uriPrefixes[self::RESOURCE_URL],
                'bbapp.routing.resource_uri_prefix'
            )
            ->setValueIfParameterExists($container, $this->defaultScheme, 'bbapp.routing.default_protocol');

        if (null === $this->logger && $container->has('lo')) {
            $this->logger = $container->get('logging');
        }
    }

    /**
     * Sets the reference $value to the parameter value if exists in the container.
     *
     * @param ContainerInterface $container The container to read.
     * @param mixed              $value     The reference value to set.
     * @param string             $parameter The parameter name to look for in the container.
     *
     * @return RouteCollection
     */
    private function setValueIfParameterExists(ContainerInterface $container, &$value, $parameter)
    {
        if ($container->hasParameter($parameter)) {
            $value = $container->getParameter($parameter);
        }

        return $this;
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed  $level   The log level.
     * @param string $message The log message.
     * @param array  $context The log contexts.
     */
    private function log($level, $message, array $context = [])
    {
        if (null !== $this->logger) {
            $this->logger->log($level, $message, $context);
        }
    }

    /**
     * Pushes an array of new routes in the collection.
     *
     * @param array $routes The new routes to push.
     */
    public function pushRouteCollection(array $routes)
    {
        foreach ($routes as $name => $route) {
            if (!array_key_exists('pattern', $route) || !array_key_exists('defaults', $route)) {
                $this->log('warning', sprintf('Unable to parse the route definition `%s`.', $name));
                continue;
            }

            $this->addRoute($name, $route);
        }

        $this->moveDefaultRoute();
    }

    /**
     * Returns the path associated to a route.
     *
     * @param string $name The name of the route to look for.
     *
     * @return string|null     The path of the route if found, null otherwise.
     */
    public function getRoutePath($name)
    {
        if (null !== $this->get($name)) {
            return $this->get($name)->getPath();
        }

        return null;
    }

    /**
     * Returns complete url which match with routeName and routeParams; you can also customize
     * the base url; by default it use current site base url.
     *
     * @param string      $name       The name of the route to look for.
     * @param array|null  $params     Optional, parameters to apply to the route.
     * @param string|null $baseUrl    Optional, base URL to use rather than the computed one.
     * @param boolean     $addExt     If true adds the default extension to result.
     * @param Site|null   $site       Optional, the site for which the uri will be built.
     * @param boolean     $buildQuery If true, adds unused parameters in querystring
     *
     * @return string                  The computed URL.
     */
    public function getUrlByRouteName(
        $name,
        array $params = null,
        $baseUrl = null,
        $addExt = true,
        Site $site = null,
        $buildQuery = false
    ) {
        $paramsToAdd = [];
        $uri = $this->applyRouteParameters(
            $this->getRoutePath($name),
            (array)$params,
            $paramsToAdd
        );

        $path = $this->getUri($baseUrl . $uri, null, $site);
        if (true !== $addExt) {
            $path = File::removeExtension($path);
        }

        if (!empty($paramsToAdd) && true === $buildQuery) {
            $path = $path . '?' . http_build_query($paramsToAdd);
        }

        return $path;
    }

    /**
     * Applies parameters to route pattern.
     *
     * @param string $uri              The route pattern.
     * @param array  $parameters       An array of parameters to apply.
     * @param array  $additionalParams Optional, the parameters to found in the pattern.
     *
     * @return string                   The route uri modified.
     */
    private function applyRouteParameters($uri, array $parameters, array &$additionalParams = array())
    {
        $result = $uri;

        foreach ($parameters as $key => $value) {
            $count = 0;
            $result = str_replace('{' . $key . '}', $value, $result, $count);

            if (0 === $count) {
                $additionalParams[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Returns $pathinfo with base url of current page
     * If $site is provided, the url will be pointing on the associate domain.
     *
     * @param null|string      $pathinfo  The pathinfo to treate.
     * @param string|null $extension Optional, the extension to add to URI.
     * @param Site|null   $site      Optional, the site for which the uri will be built.
     * @param int|null    $urlType   Optional, the URL prefix to use.
     *
     * @return null|string                 The URI computed.
     */
    public function getUri(
        ?string $pathinfo = null,
        ?string $extension = null,
        Site $site = null,
        ?int $urlType = null,
        bool $addDefaultExtension = true
    ): ?string {
        // if scheme already provided, returns pathinfo
        if (parse_url($pathinfo, PHP_URL_SCHEME)) {
            return $pathinfo;
        }

        // ensure $pathinfo is absolute
        if (strncmp($pathinfo, '/', 1) !== 0) {
            $pathinfo = '/' . $pathinfo;
        }

        // prefixes $pathinfo if needed
        if (array_key_exists((int)$urlType, $this->uriPrefixes)) {
            $pathinfo = '/' . $this->uriPrefixes[(int)$urlType] . $pathinfo;
        }

        if (null === $site && $this->hasRequestAvailable()) {
            $pathinfo = $this->getUriFromBaseUrl($pathinfo);
        } else {
            $pathinfo = $this->getUriForSite($pathinfo, $site);
        }

        return $this->getDefaultExtFromSite($pathinfo, $extension, $site, $addDefaultExtension);
    }

    /**
     * @see BackBee\DependencyInjection\Dumper\DumpableServiceInterface::getClassProxy
     */
    public function getClassProxy()
    {
        return;
    }

    /**
     * @see BackBee\DependencyInjection\Dumper\DumpableServiceInterface::dump
     */
    public function dump(array $options = array())
    {
        return ['routes' => $this->rawRoutes];
    }

    /**
     * @see BackBee\DependencyInjection\Dumper\DumpableServiceProxyInterface::restore
     */
    public function restore(ContainerInterface $container, array $dump)
    {
        if (!isset($dump['routes'])) {
            $this->log('warning', 'No routes found when restoring collection.');

            return;
        }

        foreach ($dump['routes'] as $name => $route) {
            $this->addRoute($name, $route);
        }

        $this->moveDefaultRoute();

        $this->isRestored = true;
    }

    /**
     * @see BackBee\DependencyInjection\Dumper\DumpableServiceInterface::isRestored
     */
    public function isRestored()
    {
        return $this->isRestored;
    }

    /**
     * Adds a new route to the collection.
     *
     * @param string $name  The name of the new route.
     * @param array  $route The array description of the route.
     */
    private function addRoute($name, array $route)
    {
        $this->rawRoutes[$name] = $route;

        $newRoute = new Route(
            $route['pattern'],
            $route['defaults'],
            array_key_exists('requirements', $route) ? $route['requirements'] : []
        );

        $this->add($name, $newRoute);

        $this->log('debug', sprintf('Route `%s` with pattern `%s` defined.', $name, $route['pattern']));
    }

    /**
     * Ensures that the default route is always the last one in the collection.
     */
    private function moveDefaultRoute()
    {
        $defaultRoute = $this->get('default');
        if (null !== $defaultRoute) {
            $this->remove('default');
            $this->add('default', $defaultRoute);
        }
    }

    /**
     * Returns URI from pathinfo according to current request BaseUrl().
     *
     * @param string $pathinfo The pathinfo to treate.
     *
     * @return string           The computed URI.
     */
    private function getUriFromBaseUrl($pathinfo)
    {
        $request = $this->application->getRequest();
        if (basename($request->getBaseUrl()) === basename($request->server->get('SCRIPT_NAME'))) {
            return $request->getSchemeAndHttpHost()
                . substr($request->getBaseUrl(), 0, -1 * (1 + strlen(basename($request->getBaseUrl()))))
                . $pathinfo;
        } else {
            return $request->getUriForPath($pathinfo);
        }
    }

    /**
     * Checks is a request is available.
     *
     * @return boolean Returns true is a request is available, false otherwise.
     */
    private function hasRequestAvailable()
    {
        return (
            null !== $this->application
            && !$this->application->isClientSAPI()
            && $this->application->isStarted()
        );
    }

    /**
     * Returns URI from pathinfo according to site to be reached.
     *
     * @param string    $pathinfo The pathinfo to treate.
     * @param Site|null $site     Optional, the site to care of, if null get the current site.
     *
     * @return string               The computed URI.
     */
    private function getUriForSite($pathinfo, Site $site = null)
    {
        $siteUsed = $this->getCurrentSite($site);
        if (null === $siteUsed || null === $this->application) {
            return $pathinfo;
        }

        $protocol = '';
        if (null === parse_url($siteUsed->getServerName(), PHP_URL_SCHEME)) {
            $protocol = $this->defaultScheme;
            $protocol .= ($protocol ? ':' : '') . '//';
        }

        return $protocol . $siteUsed->getServerName() . $pathinfo;
    }

    /**
     * Adds the extension provided, pr the default one for a site.
     *
     * @param string      $pathinfo  The pathinfo to change.
     * @param string|null $extension Optional, the extention to add.
     * @param Site|null   $site      Optional, the site from which the default extension will be got.
     * @param bool        $addDefaultExtension
     *
     * @return string                 The pathinfo with the extension added.
     */
    private function getDefaultExtFromSite(
        string $pathinfo,
        ?string $extension = null,
        Site $site = null,
        bool $addDefaultExtension = true
    ): string {
        if ('/' === substr($pathinfo, -1) || strpos(basename($pathinfo), '.')) {
            return $pathinfo;
        }

        $siteUsed = $this->getCurrentSite($site);
        $addExtension = $extension;
        if (null === $addExtension && null !== $siteUsed && $addDefaultExtension === true) {
            $addExtension = $siteUsed->getDefaultExtension();
        }

        return $pathinfo . $addExtension;
    }

    /**
     * Returns the current site if defined.
     *
     * @param Site|null $site Optional, if provided, will be return.
     *
     * @return Site|null
     */
    private function getCurrentSite(Site $site = null)
    {
        if (null !== $site) {
            return $site;
        }

        if (null !== $this->application) {
            return $this->application->getSite();
        }

        return null;
    }
}
