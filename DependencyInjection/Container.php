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

use Symfony\Component\DependencyInjection\ContainerBuilder as sfContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface as sfContainerInterface;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use BackBee\Event\Event;

/**
 * Extended Symfony Dependency injection component.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      e.chau <eric.chau@lp-digital.fr>
 */
class Container extends sfContainerBuilder implements ContainerInterface
{
    protected $is_restored = false;

    /**
     * Change current method default behavior: if we try to get a synthetic service it will return
     * null instead of throwing an exception;.
     *
     * @see Symfony\Component\DependencyInjection\ContainerBuilder::get()
     */
    public function get($id, $invalid_behavior = sfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE)
    {
        $service = null;
        try {
            $service = parent::get($id, $invalid_behavior);
        } catch (RuntimeException $e) {
            if (false === $this->hasDefinition($id)) {
                throw $e;
            }

            if (false === $this->getDefinition($id)->isSynthetic()) {
                throw $e;
            }
        }

        if (
            true === ($service instanceof DispatchTagEventInterface)
            && true === $service->needDispatchEvent()
            && true === $this->hasDefinition($id)
            && true === $this->has('event.dispatcher')
        ) {
            foreach ($this->getDefinition($id)->getTags() as $tag => $datas) {
                if (false === isset($datas[0]['dispatch_event']) || true === $datas[0]['dispatch_event']) {
                    $this->services['event.dispatcher']->dispatch(
                        'service.tagged.'.$tag,
                        new Event($service, array('id' => $id))
                    );
                }
            }
        }

        return $service;
    }

    /**
     * Giving a string, try to return the container service or parameter if exists
     * This method can be call by array_walk or array_walk_recursive.
     *
     * @param mixed $item
     *
     * @return mixed
     */
    public function getContainerValues(&$item)
    {
        if (false === is_object($item) && false === is_array($item)) {
            $item = $this->getContainerServices($this->getContainerParameters($item));
        }

        return $item;
    }

    /**
     * Replaces known container parameters key by their values.
     *
     * @param string $item
     *
     * @return string
     */
    private function getContainerParameters($item)
    {
        $matches = array();
        if (preg_match('/^%([^%]+)%$/', $item, $matches)) {
            if ($this->hasParameter($matches[1])) {
                return $this->getParameter($matches[1]);
            }
        }

        if (preg_match_all('/%([^%]+)%/', $item, $matches, PREG_PATTERN_ORDER)) {
            foreach ($matches[1] as $expr) {
                if ($this->hasParameter($expr)) {
                    $item = str_replace('%'.$expr.'%', $this->getParameter($expr), $item);
                }
            }
        }

        return $item;
    }

    /**
     * Returns the associated service to item if exists, item itself otherwise.
     *
     * @param string $item
     *
     * @return mixed
     */
    private function getContainerServices($item)
    {
        if (false === is_string($item)) {
            return $item;
        }

        $matches = array();
        if (preg_match('/^@([a-z0-9.-]+)$/i', trim($item), $matches)) {
            if ($this->has($matches[1])) {
                return $this->get($matches[1]);
            }
        }

        return $item;
    }

    /**
     * Returns true if the given service is loaded.
     *
     * @param string $id The service identifier
     *
     * @return Boolean true if the service is loaded, false otherwise
     */
    public function isLoaded($id)
    {
        $id = strtolower($id);

        return $this->hasInstanceOf($id)
            || method_exists($this, 'get'.strtr($id, array('_' => '', '.' => '_')).'Service')
        ;
    }

    /**
     * Checks if current container has an instance of service with $id or not.
     *
     * @param string $id identifier of the service we want to check for instance
     *
     * @return boolean true if current container has an instance of service with $id, else false
     */
    public function hasInstanceOf($id)
    {
        return isset($this->services[strtolower($id)]);
    }

    /**
     * Returns true if this container has been restored from dump.
     *
     * @return boolean true if is restored, else false
     */
    public function isRestored()
    {
        return $this->is_restored;
    }
}
