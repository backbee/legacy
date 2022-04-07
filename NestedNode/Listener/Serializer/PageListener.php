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

namespace BackBee\NestedNode\Listener\Serializer;

use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use BackBee\DependencyInjection\ContainerInterface;

/**
 * Listener to NestedNode\Page events :
 *    - serializer.post_serialize: add URI entry to serialized data.
 *
 * @category    BackBee
 *
 *
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class PageListener implements EventSubscriberInterface
{
    /**
     * The services container.
     *
     * @var \BackBee\DependencyInjection\ContainerInterface
     */
    private $container;

    /**
     * Class constructor.
     *
     * @param \BackBee\DependencyInjection\ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /*
     * Returns the events to which this class has subscribed.
     *
     * Return format:
     *     array(
     *         array('event' => 'the-event-name', 'method' => 'onEventName', 'class' => 'some-class', 'format' => 'json'),
     *         array(...),
     *     )
     *
     * The class may be omitted if the class wants to subscribe to events of all classes.
     * Same goes for the format key.
     *
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            array('event' => 'serializer.post_serialize', 'class' => 'BackBee\NestedNode\Page', 'method' => 'onPostSerialize'),
        );
    }

    /**
     * Method called on serializer.post_serialize event for Page object.
     *
     * @param \JMS\Serializer\EventDispatcher\ObjectEvent $event
     */
    public function onPostSerialize(ObjectEvent $event)
    {
        if ($this->container->has('routing')) {
            $uri = $this->container->get('routing')->getUri($event->getObject()->getUrl());
            $event->getVisitor()->addData('uri', $uri);
        }
    }
}
