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
 * Listener to metadata events.
 *
 * @category    BackBee
 *
 *
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class MetaDataListener
{

    /**
     * Occur on classcontent.onflush events.
     *
     * @param \BackBee\Event\Event $event
     * @deprecated since version 1.1
     */
    public static function onFlushContent(Event $event)
    {
        \BackBee\MetaData\Listener\MetaDataListener::onFlushContent($event);
    }

    /**
     * Occur on nestednode.page.onflush events.
     *
     * @param \BackBee\Event\Event $event
     * @deprecated since version 1.1
     */
    public static function onFlushPage(Event $event)
    {
        \BackBee\MetaData\Listener\MetaDataListener::onFlushPage($event);
    }
}