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

namespace BackBee\Profiler\EventListener;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Profiler Toolbar listener.
 *
 * @category    BackBee
 *
 *
 * @author      k.golovin
 */
class ToolbarListener implements ContainerAwareInterface
{
    private $enabled = false;

    private $container;

    public function __construct($enabled = false)
    {
        $this->enabled = $enabled;
    }

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param Request $request
     *
     * @return boolean - true if the listener should be enabled for the $request
     */
    public function isEnabled()
    {
        return $this->enabled;
    }

    public function onKernelResponse(FilterResponseEvent $event)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $event->getRequestType()) {
            return;
        }

        if (false === $this->isEnabled()) {
            return;
        }

        $response = $event->getResponse();
        $request = $event->getRequest();

        // do not capture redirects or modify XML HTTP Requests
        if ($request->isXmlHttpRequest()) {
            return;
        }

        if ($response->isRedirection()
            || ($response->headers->has('Content-Type') && false === strpos($response->headers->get('Content-Type'), 'html'))
            || 'html' !== $request->getRequestFormat()
        ) {
            return;
        }

        $profiler = $event->getKernel()->getApplication()->getContainer()->get('profiler');
        $profile = $profiler->collect($request, $response, null);
        $profile->getCollector('logger')->lateCollect();
        $renderer = $event->getKernel()->getApplication()->getRenderer();

        $this->injectToolbar($response, $profile, $renderer);
    }

    protected function loadTemplates()
    {
        $templates = array();

        $templateNames = $this->container->getParameter('data_collector.templates');

        foreach ($templateNames as $name => $file) {
            $templates[$name] = $this->container->get('renderer')->getAdapterByExt('twig')->loadTemplate($file);
        }

        return $templates;
    }

    /**
     * Injects the web debug toolbar into the given Response.
     *
     * @param Response $response A Response instance
     */
    protected function injectToolbar(Response $response, $profile, $renderer)
    {
        $content = $response->getContent();
        $pos = strripos($content, '</body>');

        if (false !== $pos) {
            $bb_dir = $renderer->getApplication()->getBBDir();
            $toolbar = "\n".str_replace("\n", '', $renderer->partial(
                $bb_dir.'/Resources/scripts/Profiler/toolbar.html.twig',
                array(
                    'profile'   => $profile,
                    'templates' => $this->loadTemplates(),
                )
            ))."\n";

            $content = substr($content, 0, $pos).$toolbar.substr($content, $pos);
            $response->setContent($content);
        }
    }

    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::RESPONSE => array('onKernelResponse', -128),
        );
    }
}
