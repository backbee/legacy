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
 * Listener to content element events.
 *
 * @category    BackBee
 *
 *
 * @author      n.bremont <nicolas.bremont@lp-digital.fr>
 */
class elementListener
{
    public static function onRender(Event $event)
    {
        $dispatcher = $event->getDispatcher();
        $renderer = $event->getEventArgs();
        $renderer->assign('keyword', null);
        if (null !== $dispatcher) {
            $application = $dispatcher->getApplication();
            if (null === $application) {
                return;
            }
            $keywordloaded = $event->getTarget();
            if (!is_a($renderer, 'BackBee\Renderer\AbstractRenderer')) {
                return;
            }
            $keyWord = $application->getEntityManager()->find('BackBee\NestedNode\KeyWord', $keywordloaded->value);
            if (!is_null($keyWord)) {
                $renderer->assign('keyword', $keyWord);
            }
        }
    }
}
