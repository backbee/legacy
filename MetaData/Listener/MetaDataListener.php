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

namespace BackBee\MetaData\Listener;

use BackBee\ClassContent\AbstractClassContent;
use BackBee\Event\Event;
use BackBee\NestedNode\Page;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Listener to metadata events.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class MetaDataListener implements EventSubscriberInterface
{

    private static $onFlushPageAlreadyCalled = false;

    /**
     * Occur on classcontent.onflush events.
     *
     * @param Event $event
     */
    public static function onFlushContent(Event $event)
    {
        $content = $event->getTarget();
        if (!($content instanceof AbstractClassContent)) {
            return;
        }

        $application = $event->getApplication();
        $em = $application->getEntityManager();
        $uow = $em->getUnitOfWork();
        if ($uow->isScheduledForDelete($content)) {
            return;
        }

        if (null !== $page = $content->getMainNode()) {
            if (null !== $page->getMetaData()) {
                $newEvent = new Event($page, $content);
                $newEvent->setDispatcher($event->getDispatcher());
                self::onFlushPage($newEvent);
            }
        }
    }

    /**
     * Occur on nestednode.page.onflush events.
     *
     * @param Event $event
     */
    public static function onFlushPage(Event $event)
    {
        if (self::$onFlushPageAlreadyCalled) {
            return;
        }

        $page = $event->getTarget();
        if (!($page instanceof Page)) {
            return;
        }

        $application = $event->getApplication();
        $em = $application->getEntityManager();
        $uow = $em->getUnitOfWork();
        if ($uow->isScheduledForDelete($page)) {
            return;
        }

        $metadata = $application->getContainer()->get('nestednode.metadata.resolver')->resolve($page);
        $page->setMetaData($metadata);

        if ($uow->isScheduledForInsert($page) || $uow->isScheduledForUpdate($page)) {
            $uow->recomputeSingleEntityChangeSet($em->getClassMetadata('BackBee\NestedNode\Page'), $page);
        } elseif (!$uow->isScheduledForDelete($page)) {
            $uow->computeChangeSet($em->getClassMetadata('BackBee\NestedNode\Page'), $page);
        }

        self::$onFlushPageAlreadyCalled = true;
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents()
    {
        return [
            'classcontent.onflush'     => 'onFlushContent',
            'nestednode.page.postload' => 'onPostload',
        ];
    }

}
