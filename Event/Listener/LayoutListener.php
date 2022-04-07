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

namespace BackBee\Event\Listener;

use BackBee\Event\Event;

/**
 * Listener to Layout events :
 *    - site.layout.beforesave: occurs before a layout entity is saved
 *    - site.layout.postremove: occurs after a layout entity has been removed.
 *
 * @category    BackBee
 *
 *
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class LayoutListener
{
    /**
     * Occur on site.layout.beforesave events.
     *
     * @access public
     *
     * @param Event $event
     */
    public static function onBeforeSave(Event $event)
    {
        $layout = $event->getTarget();
        if (!is_a($layout, 'BackBee\Site\Layout')) {
            return;
        }

        $dispatcher = $event->getDispatcher();
        if (null !== $dispatcher->getApplication()) {
            if (is_a($event->getEventArgs(), 'Doctrine\ORM\Event\PreUpdateEventArgs')) {
                if (!$event->getEventArgs()->hasChangedField('_data')) {
                    return;
                }
            }

            // Update the layout thumbnail - Beware of generate thumbnail before any other operation
            $thumb = $dispatcher->getApplication()->getEntityManager()
                    ->getRepository('BackBee\Site\Layout')
                    ->generateThumbnail($layout, $dispatcher->getApplication());

            // Update the layout file
            try {
                $dispatcher->getApplication()->getRenderer()->updateLayout($layout);
            } catch (\BackBee\Renderer\Exception\RendererException $e) {
                $dispatcher->getApplication()->warning($e->getMessage());
            }

            if (is_a($event->getEventArgs(), 'Doctrine\ORM\Event\PreUpdateEventArgs')) {
                if ($event->getEventArgs()->hasChangedField('_picpath')) {
                    $event->getEventArgs()->setNewValue('_picpath', $thumb);
                }
            }
        }
    }

    /**
     * Occur on site.layout.postremove events.
     *
     * @access public
     *
     * @param Event $event
     */
    public static function onAfterRemove(Event $event)
    {
        $layout = $event->getTarget();
        if (!is_a($layout, 'BackBee\Site\Layout')) {
            return;
        }

        $dispatcher = $event->getDispatcher();
        if (null !== $dispatcher->getApplication()) {
            $dispatcher->getApplication()->getEntityManager()
                    ->getRepository('BackBee\Site\Layout')
                    ->removeThumbnail($layout, $dispatcher->getApplication());
        }

        $dispatcher->getApplication()->getRenderer()->removeLayout($layout);
    }
}
