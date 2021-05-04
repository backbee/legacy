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

use Doctrine\ORM\Event\PreUpdateEventArgs;

use BackBee\BBApplication;
use BackBee\Event\Event;
use BackBee\NestedNode\Page;
use BackBee\NestedNode\Repository\PageRepository;
use BackBee\NestedNode\Section;

/**
 * Page events listener.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      k.golovin
 */
class PageListener
{
    /**
     * @var BackBee\BBApplication
     */
    protected $_application;

    public function __construct(BBApplication $application)
    {
        $this->_application = $application;
    }

    /**
     * @param \BackBee\Event\Event $event
     */
    public function onPostLoad(Event $event)
    {
        $page = $event->getTarget();

        if (!($page instanceof Page)) {
            return;
        }

        $isBbSessionActive = $this->_application->getBBUserToken() === null;

        $page->setUseUrlRedirect($isBbSessionActive);
    }

    public static function setSectionHasChildren($em, Section $section = null, $pageCountModifier = 0)
    {
        if ($section !== null) {
            $repo = $em->getRepository('BackBee\NestedNode\Page');
            $notDeletedDescendants = $repo->getNotDeletedDescendants($section->getPage(), 1, false, [], true, 0, 2);

            $section->setHasChildren((count($notDeletedDescendants) + $pageCountModifier) > 0);
            $em->getUnitOfWork()->recomputeSingleEntityChangeSet($em->getClassMetadata('BackBee\NestedNode\Section'), $section);
        }
    }

    /**
     * Occur on nestednode.page.preupdate events and nestednode.section.preupdate.
     *
     * @access public
     *
     * @param Event $event
     */
    public static function onPreUpdate(Event $event)
    {
        $page = $event->getTarget();
        $eventArgs = $event->getEventArgs();
        $updateParents = false;
        $new = $old = null;

        $pageCountModifier = -1;
        if ($eventArgs instanceof PreUpdateEventArgs) {
            if ($page instanceof Page && $eventArgs->hasChangedField('_section')) {
                $old = $eventArgs->getOldValue('_section');
                $new = $eventArgs->getNewValue('_section');

                if ($new->getUid() === $page->getUid()) {
                    return;
                }
                $updateParents = true;
            }

            if ($page instanceof Page && $eventArgs->hasChangedField('_state')) {
                if ($page->getParent() !== null) {
                    if ($eventArgs->getNewValue('_state') >= 4) {
                        $old = $page->getParent()->getSection();
                        if (!$page->isLeaf()) {
                            $pageCountModifier = 0;
                        }
                    } else {
                        $new = $page->getParent()->getSection();
                    }
                    $updateParents = true;
                }
            }

            if ($page instanceof Section && $eventArgs->hasChangedField('_parent')) {
                $old = $eventArgs->getOldValue('_parent');
                $new = $eventArgs->getNewValue('_parent');
                $updateParents = true;
            }

            if ($updateParents) {
                $em = $event->getApplication()->getEntityManager();

                self::setSectionHasChildren($em, $old, $pageCountModifier);
                self::setSectionHasChildren($em, $new, +1);
            }
        }
    }

    /**
     * Occur on nestednode.page.preupdate events and nestednode.section.preupdate.
     *
     * @access public
     *
     * @param Event $event
     */
    public static function onFlushPage(Event $event)
    {
        $em = $event->getApplication()->getEntityManager();
        $uow = $em->getUnitOfWork();
        $page = $event->getTarget();
        if ($uow->isScheduledForInsert($page) && $page->getParent() !== null && $page->getState() < 4) {
            self::setSectionHasChildren($em, $page->getParent()->getSection(), +1);
        }
    }
}
