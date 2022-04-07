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

namespace BackBee\Bundle\Listener;

use BackBee\Bundle\Event\BundleStopEvent;
use BackBee\Event\Event;

/**
 * BackBee core bundle listener.
 *
 * @category    BackBee
 *
 *
 * @author      e.chau <eric.chau@lp-digital.fr>
 */
class BundleListener
{

    /**
     * Occurs on `bbapplication.stop` event to stop every started bundles.
     *
     * @param Event $event
     */
    public static function onApplicationStop(Event $event)
    {
        $container = $event->getTarget()->getContainer();
        $eventDispatcher = $container->has('event.dispatcher') ? $container->get('event.dispatcher') : null;

        foreach (array_keys($container->findTaggedServiceIds('bundle')) as $bundleId) {
            if (!$container->hasInstanceOf($bundleId)) {
                continue;
            }

            $bundle = $container->get($bundleId);
            $bundle->stop();

            if (null !== $eventDispatcher) {
                $eventDispatcher->dispatch(
                    sprintf('bundle.%s.stopped', $bundleId),
                    new BundleStopEvent($container->get($bundleId))
                );
            }
        }
    }
}
