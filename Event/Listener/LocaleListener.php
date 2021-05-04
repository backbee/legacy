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

namespace BackBee\Event\Listener;

use BackBee\BBApplication;
use BackBee\ClassContent\AbstractClassContent;
use BackBee\Event\Event;
use BackBee\NestedNode\Page;
use BackBee\Rewriting\UrlGeneratorInterface;

/**
 * Listener to rewriting events.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class LocaleListener
{
    /**
     * Occur on classcontent.onflush events.
     *
     * @param \BackBee\Event\Event $event
     */
    public static function onFlushContent(Event $event)
    {
        $content = $event->getTarget();
        if (!($content instanceof AbstractClassContent)) {
            return;
        }

        $page = $content->getMainNode();
        if (null === $page) {
            return;
        }

        $newEvent = new Event($page, $content);
        $newEvent->setDispatcher($event->getDispatcher());
        self::onFlushPage($newEvent);
    }

    /**
     * Occur on nestednode.page.onflush events.
     *
     * @param \BackBee\Event\Event $event
     */
    public static function onFlushPage(Event $event)
    {
        $page = $event->getTarget();
        if (!($page instanceof Page)) {
            return;
        }

        $maincontent = $event->getEventArgs();
        if (!($maincontent instanceof AbstractClassContent)) {
            $maincontent = null;
        }

        $dispatcher = $event->getDispatcher();
        $application = $dispatcher->getApplication();
        $em = $application->getEntityManager();

        self::updateUrl($application, $page, $maincontent);

        $descendants = $em->getRepository('BackBee\NestedNode\Page')->getDescendants($page);
        foreach ($descendants as $descendant) {
            self::updateUrl($application, $descendant);
        }
    }

    /**
     * Update URL for a page and its descendants according to the application UrlGeneratorInterface.
     *
     * @param \BackBee\BBApplication              $application
     * @param \BackBee\NestedNode\Page            $page
     * @param \BackBee\ClassContent\AbstractClassContent $maincontent
     */
    private static function updateUrl(BBApplication $application, Page $page, AbstractClassContent $maincontent = null)
    {
        $urlGenerator = $application->getUrlGenerator();
        if (!($urlGenerator instanceof UrlGeneratorInterface)) {
            return;
        }

        $em = $application->getEntityManager();
        if (null === $maincontent && 0 < count($urlGenerator->getDiscriminators())) {
            $maincontent = $em->getRepository('BackBee\ClassContent\AbstractClassContent')->getLastByMainnode($page, $urlGenerator->getDiscriminators());
        }

        $newUrl = $urlGenerator->generate($page, $maincontent);
        if ($page->getUrl() != $newUrl) {
            $page->setUrl($newUrl);

            $uow = $em->getUnitOfWork();
            if ($uow->isScheduledForInsert($page) || $uow->isScheduledForUpdate($page)) {
                $uow->recomputeSingleEntityChangeSet($em->getClassMetadata('BackBee\NestedNode\Page'), $page);
            } elseif (!$uow->isScheduledForDelete($page)) {
                $uow->computeChangeSet($em->getClassMetadata('BackBee\NestedNode\Page'), $page);
            }
        }
    }
}
