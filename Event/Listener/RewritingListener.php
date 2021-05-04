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

/**
 * Listener to rewriting events.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class RewritingListener
{
    /**
     * Stores uids of the pages already computed.
     * 
     * @var string[]
     */
    static private $alreadyDone = [];

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

        $url = $page->getUrl(false);
        if (!empty($url) && in_array($page->getUid(), self::$alreadyDone)) {
            return false;
        }

        $newEvent = new Event($page);
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

        if (self::updateUrl($application, $page, $maincontent)) {
            $descendants = $application->getEntityManager()
                    ->getRepository('BackBee\NestedNode\Page')
                    ->getDescendants($page, 1);
            foreach ($descendants as $descendant) {
                self::updateUrl($application, $descendant);
            }
        }
    }

    /**
     * Update URL for a page and its descendants according to the application UrlGeneratorInterface.
     *
     * @param BBApplication        $application
     * @param Page                 $page
     * @param AbstractClassContent $maincontent
     */
    private static function updateUrl(BBApplication $application, Page $page, AbstractClassContent $maincontent = null)
    {
        $urlGenerator = $application->getUrlGenerator();

        $em = $application->getEntityManager();

        $uow = $em->getUnitOfWork();
        $changeSet = $uow->getEntityChangeSet($page);

        if (null === $maincontent && 0 < count($urlGenerator->getDiscriminators())) {
            if ($uow->isScheduledForInsert($page)) {
               foreach($uow->getScheduledEntityInsertions() as $entity) {
                   if ($entity instanceof \BackBee\ClassContent\AbstractClassContent && $page === $entity->getMainNode()) {
                       $maincontent = $entity;
                       break;
                   }
               }
            } else {
                $maincontent = $em->getRepository('BackBee\ClassContent\AbstractClassContent')
                    ->getLastByMainnode($page, $urlGenerator->getDiscriminators())
                ;
            }
        }

        $newUrl = null;
        $url = $page->getUrl(false);
        if (isset($changeSet['_url']) && !empty($url)) {
            $newUrl = $urlGenerator->getUniqueness($page, $page->getUrl());
        } else {
            $force = isset($changeSet['_state']) && !($changeSet['_state'][0] & Page::STATE_ONLINE);
            $newUrl = $urlGenerator->generate($page, $maincontent, $force);
        }

        self::$alreadyDone[] = $page->getUid();

        if ($newUrl !== $page->getUrl(false)) {
            $page->setUrl($newUrl);

            $classMetadata = $em->getClassMetadata('BackBee\NestedNode\Page');
            if ($uow->isScheduledForInsert($page) || $uow->isScheduledForUpdate($page)) {
                $uow->recomputeSingleEntityChangeSet($classMetadata, $page);
            } elseif (!$uow->isScheduledForDelete($page)) {
                $uow->computeChangeSet($classMetadata, $page);
            }

            return true;
        }

        return false;
    }
}
