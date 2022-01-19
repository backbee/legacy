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

namespace BackBee\Event;

use BackBee\BBApplication;
use BackBee\ClassContent\AbstractClassContent;
use BackBee\Config\Config;
use BackBee\DependencyInjection\Container;
use BackBee\DependencyInjection\Dumper\DumpableServiceInterface;
use BackBee\Event\Exception\ContainerNotFoundException;
use Symfony\Component\EventDispatcher\Event as sfEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * An event dispatcher for BB application.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>, e.chau <eric.chau@lp-digital.fr>
 */
class Dispatcher extends EventDispatcher implements DumpableServiceInterface
{
    /**
     * Current BackBee application.
     *
     * @var BackBee\BBApplication
     */
    protected $application;

    /**
     * Container we use to get services as listener.
     *
     * @var BackBee\DependencyInjection\Container
     */
    protected $container;

    /**
     * every registered listeners; we need it so we can dump Dispatcher properly.
     *
     * @var array
     */
    protected $raw_listeners;

    /**
     * define if dispatcher is already restored by container or not.
     *
     * @var boolean
     */
    protected $_is_restored;

    /**
     * Dispatcher constructor.
     *
     * @param \BackBee\BBApplication $application The current instance of BB application
     */
    public function __construct(BBApplication $application = null, Config $config = null)
    {
        $this->application = $application;

        if (null === $config && null !== $application) {
            $config = $application->getConfig();
        }

        if (null !== $config) {
            if (null !== $events_config = $config->getRawSection('events')) {
                $this->addListeners($events_config);
            }
        }

        if (null !== $application) {
            $this->container = $this->application->getContainer();
        }

        $this->_is_restored = false;
    }

    /**
     * Setter of Event\Dispatcher's container.
     *
     * @param Container $container the container to set into Event\Dispatcher
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Add listener to events.
     *
     * @param array $events_config
     */
    public function addListeners(array $events_config)
    {
        foreach ($events_config as $name => $listeners) {
            if (false === array_key_exists('listeners', $listeners)) {
                $this->application->warning("None listener found for `$name` event.");
                continue;
            }

            $listeners['listeners'] = (array) $listeners['listeners'];
            foreach ($listeners['listeners'] as $listener) {
                $this->addListener($name, $listener);
            }
        }
    }

    /**
     * Removes all listeners.
     */
    public function clearAllListeners()
    {
        foreach ($this->raw_listeners as $eventName => $data) {
            foreach ($data as $listeners) {
                foreach ($listeners as $listener) {
                    $this->removeListener($eventName, $listener);
                }
            }
        }

        $this->raw_listeners = [];
    }

    /**
     * @see EventDispatcherInterface::dispatch
     *
     * @api
     */
    public function dispatch($eventName, sfEvent $event = null)
    {
        if (null !== $this->application) {
            $this->application->debug(sprintf('Dispatching `%s` event.', $eventName));
        }

        return parent::dispatch($eventName, $event);
    }

    /**
     * Trigger a BackBee\Event\Event depending on the entity and event name.
     *
     * @param string    $eventName The doctrine event name
     * @param Object    $entity    The entity instance
     * @param EventArgs $eventArgs The doctrine event arguments
     */
    public function triggerEvent($eventName, $entity, $eventArgs = null, Event $event = null)
    {
        if (null === $event) {
            $event = new Event($entity, $eventArgs);
        }

        $this->dispatch($this->formatEventName($eventName, $entity), $event);

        if ($entity instanceof AbstractClassContent) {
            foreach (class_parents($entity) as $class) {
                if ($class === 'BackBee\ClassContent\AbstractClassContent') {
                    break;
                }

                $this->dispatch($this->formatEventName($eventName, $class), $event);
            }

            $this->dispatch(strtolower('classcontent.'.$eventName), $event);
        }
    }

    /**
     * Return the current instance of BBapplication.
     *
     * @return \BackBee\BBApplication
     */
    public function getApplication()
    {
        return $this->application;
    }

    /**
     * return the normalize prefix of the eventname depending on classname.
     *
     * @param Object $entity
     *
     * @return string
     */
    public function getEventNamePrefix($entity)
    {
        if (is_object($entity)) {
            $eventPrefix = strtolower(str_replace(NAMESPACE_SEPARATOR, '.', get_class($entity)).'.');
        } else {
            $eventPrefix = strtolower(str_replace(NAMESPACE_SEPARATOR, '.', $entity).'.');
        }
        if ($entity instanceof \Doctrine\ORM\Proxy\Proxy) {
            $prefix = str_replace(NAMESPACE_SEPARATOR, '.', $this->application->getEntityManager()->getConfiguration()->getProxyNamespace());
            $prefix .= '.'.$entity::MARKER.'.';

            $eventPrefix = str_replace(strtolower($prefix), '', $eventPrefix);
        }

        $eventPrefix = str_replace(array('backbee.', 'classcontent.'), array('', ''), $eventPrefix);

        return $eventPrefix;
    }

    /**
     * @see Symfony\Component\EventDispatcher\EventDispatcherInterface::addListener
     */
    public function addListener($eventName, $listener, $priority = 0)
    {
        if (count($listener) === 3 && isset($listener[2])) {
            $priority = $listener[2];
            unset($listener[2]);
        }

        parent::addListener($eventName, $listener, $priority);

        $this->raw_listeners[$eventName][$priority][] = $listener;
    }

    /**
     * Returns the namespace of the class proxy to use or null if no proxy is required.
     *
     * @return string|null the namespace of the class proxy to use on restore or null if no proxy required
     */
    public function getClassProxy()
    {
        return 'BackBee\Event\DispatcherProxy';
    }

    /**
     * Dumps current service state so we can restore it later by calling DumpableServiceInterface::restore()
     * with the dump array produced by this method.
     *
     * @return array contains every datas required by this service to be restored at the same state
     */
    public function dump(array $options = array())
    {
        return array(
            'listeners'       => $this->raw_listeners,
            'has_application' => null !== $this->application,
            'has_container'   => null !== $this->container,
        );
    }

    /**
     * @return boolean true if current service is already restored, otherwise false
     */
    public function isRestored()
    {
        return $this->_is_restored;
    }

    /**
     * Triggers the listeners of an event.
     *
     * This method can be overridden to add functionality that is executed
     * for each listener.
     *
     * @param array[callback] $listeners The event listeners.
     * @param string          $eventName The name of the event to dispatch.
     * @param Event           $event     The event object to pass to the event handlers/listeners.
     */
    protected function doDispatch($listeners, $eventName, sfEvent $event)
    {
        //dump($listeners);

        foreach ($listeners as $listener) {
            $callable = array_shift($listener);
            if (true === is_string($callable)) {
                if (1 === preg_match('#^@(.+)#', $callable, $matches) && true === isset($matches[1])) {
                    if (null !== $this->getContainer()) {
                        $callable = $this->getContainer()->get($matches[1]);
                    } else {
                        throw new ContainerNotFoundException();
                    }
                }
            }

            array_unshift($listener, $callable);
            call_user_func($listener, $event);

            if ($event->isPropagationStopped()) {
                break;
            }
        }
    }

    /**
     * returns the container of current dispatcher (try to get it application if current dispatcher don't have it).
     *
     * @return mixed null if no container has been found, else the container
     */
    private function getContainer()
    {
        if (null === $this->container && null !== $this->application) {
            $this->container = $this->application->getContainer();
        }

        return $this->container;
    }

    /**
     * Format the name of an event.
     *
     * @param string $event_name
     * @param object $entity
     *
     * @return string the formated event name
     */
    private function formatEventName($event_name, $entity)
    {
        if (is_object($entity)) {
            $event_name = strtolower(str_replace(NAMESPACE_SEPARATOR, '.', get_class($entity)).'.'.$event_name);

            if ($entity instanceof \Doctrine\ORM\Proxy\Proxy) {
                $prefix = str_replace(
                    NAMESPACE_SEPARATOR,
                    '.',
                    $this->application->getEntityManager()->getConfiguration()->getProxyNamespace()
                );
                $prefix .= '.'.$entity::MARKER.'.';

                $event_name = str_replace(strtolower($prefix), '', $event_name);
            }
        } else {
            $event_name = strtolower(str_replace(NAMESPACE_SEPARATOR, '.', $entity).'.'.$event_name);
        }

        $event_name = str_replace(array('backbee.', 'classcontent.'), array('', ''), $event_name);

        return $event_name;
    }
}
