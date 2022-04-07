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

namespace BackBee\Renderer\Listener;

use BackBee\Event\Event;

/**
 * Twig renderer adapter listener.
 *
 * @category    BackBee
 *
 *
 * @author      Eric Chau <eric.chau@lp-digital.fr>
 */
class TwigListener
{
    /**
     * occurs on `bbapplication.init`.
     *
     * @param Event $event
     */
    public static function onApplicationReady(Event $event)
    {
        $app = $event->getTarget();

        $twigAdapter = $app->getRenderer()->getAdapter('twig');
        if (null === $twigAdapter) {
            return;
        }

        foreach ($app->getContainer()->findTaggedServiceIds('twig.extension') as $id => $data) {
            $twigAdapter->addExtension($app->getContainer()->get($id));
        }
    }
}
