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

namespace BackBee\Workflow\Listener;

use BackBee\Event\Event;
use BackBee\NestedNode\Page;

use Doctrine\ORM\Event\PreUpdateEventArgs;

/**
 * Listener to page events.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      d.Bensid <djoudi.bensid@lp-digital.fr>
 */
class PageListener
{
    /**
     * Occur on nestednode.page.preupdate events.
     *
     * @access public
     *
     * @param Event $event
     */
    public static function onPreUpdate(Event $event)
    {
        $page = $event->getTarget();
        $eventArgs = $event->getEventArgs();

        if ($eventArgs instanceof PreUpdateEventArgs) {
            if ($eventArgs->hasChangedField('_workflow_state')) {
                $old = $eventArgs->getOldValue('_workflow_state');
                $new = $eventArgs->getNewValue('_workflow_state');

                if (null !== $new && null !== $listener = $new->getListenerInstance()) {
                    $listener->switchOnState($event);
                }

                if (null !== $old && null !== $listener = $old->getListenerInstance()) {
                    $listener->switchOffState($event);
                }
            }

            if ($eventArgs->hasChangedField('_state')) {
                if (
                    !($eventArgs->getOldValue('_state') & Page::STATE_ONLINE)
                    && $eventArgs->getNewValue('_state') & Page::STATE_ONLINE
                ) {
                    $event->getDispatcher()->triggerEvent('putonline', $page);
                } elseif (
                    $eventArgs->getOldValue('_state') & Page::STATE_ONLINE
                    && !($eventArgs->getNewValue('_state') & Page::STATE_ONLINE)
                ) {
                    $event->getDispatcher()->triggerEvent('putoffline', $page);
                }
            }
        }
    }
}
