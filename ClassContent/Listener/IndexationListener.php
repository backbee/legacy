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

namespace BackBee\ClassContent\Listener;

use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\Indexation;
use BackBee\Event\Event;
use BackBee\Util\Doctrine\ScheduledEntities;
use Doctrine\ORM\EntityManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Listener to indexation events.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class IndexationListener implements EventSubscriberInterface
{
    /**
     * The current application instance.
     *
     * @var \BackBee\BBApplication
     */
    private static $_application;

    /**
     * The current entity manager.
     *
     * @var EntityManager
     */
    private static $em;

    /**
     * Array of content uids already treated.
     *
     * @var array
     */
    private static $_content_content_done = [];

    /**
     * Array of content uids already treated.
     *
     * @var array
     */
    private static $_site_content_done = [];

    /**
     * Updates the site-content indexes for the scheduled AbstractClassContent.
     *
     * @param array $contents_inserted
     * @param array $contents_removed
     */
    private static function updateIdxSiteContents(array $contents_inserted, array $contents_removed)
    {
        if (null === $site = self::$_application->getSite()) {
            return;
        }

        self::$em->getRepository('BackBee\ClassContent\Indexes\IdxContentContent')
                ->replaceIdxSiteContents($site, array_diff($contents_inserted, self::$_site_content_done))
                ->removeIdxSiteContents($site, $contents_removed);

        self::$_site_content_done = array_merge(self::$_site_content_done, $contents_inserted);
    }

    /**
     * Updates the content-content indexes for the scheduled AbstractClassContent.
     *
     * @param array $contents_saved
     * @param array $contents_removed
     */
    private static function updateIdxContentContents(array $contents_saved, array $contents_removed)
    {
        if (null === self::$_application->getSite()) {
            return;
        }

        self::$em->getRepository('BackBee\ClassContent\Indexes\IdxContentContent')
                ->replaceIdxContentContents(array_diff($contents_saved, self::$_content_content_done))
                ->removeIdxContentContents($contents_removed);

        self::$_content_content_done = array_merge(self::$_content_content_done, $contents_saved);
    }

    /**
     * Subscriber to nestednode.page.onflush event
     *     - Replace page-content and site-content indexes if
     *       the Page target is inserted or updated
     *     - Remove page-content and site-content if the Page target is deleted.
     *
     * @param Event $event
     */
    public static function onFlushPage(Event $event)
    {
    }

    /**
     * Returns the current application.
     *
     * @param Event $event
     *
     * @return \BackBee\BBApplication
     */
    private static function getApplication(Event $event)
    {
        if (null === self::$_application) {
            if (null !== $event->getDispatcher()) {
                self::$_application = $event->getDispatcher()->getApplication();
            }
        }

        return self::$_application;
    }

    /**
     * Returns the current entity manager.
     *
     * @param Event $event
     *
     * @return EntityManager
     */
    private static function getEntityManager(Event $event)
    {
        if (null === self::$em) {
            if (null !== self::getApplication($event)) {
                self::$em = self::getApplication($event)->getEntityManager();
            }
        }

        return self::$em;
    }

    /**
     * Updates every indexed value assocatied to the contents flushed.
     *
     * @param Event $event
     */
    public static function onFlushContent(Event $event)
    {
        if (null === self::getEntityManager($event)) {
            return;
        }

        $contents_inserted = ScheduledEntities::getScheduledAClassContentInsertions(self::$em, true, true);
        $contents_updated = ScheduledEntities::getScheduledAClassContentUpdates(self::$em, true, true);
        $contents_deleted = ScheduledEntities::getSchedulesAClassContentDeletions(self::$em, true);

        // Updates content-content indexes
        self::updateIdxContentContents(array_merge($contents_inserted, $contents_updated), $contents_deleted);

        // Updates site-content indexes
        self::updateIdxSiteContents($contents_inserted, $contents_deleted);

        $content = $event->getTarget();
        if (!($content instanceof AbstractClassContent)) {
            return;
        }
        $dispatcher = $event->getDispatcher();
        $application = $dispatcher->getApplication();
        if (null === $application) {
            return;
        }

        $uow = self::$em->getUnitOfWork();

        if ($uow->isScheduledForInsert($content) || $uow->isScheduledForUpdate($content)) {
            self::$em->getRepository('BackBee\ClassContent\Indexes\OptContentByModified')
                    ->replaceOptContentTable($content);

            self::indexContent($content);
            self::updateSuperContentIndexes($content);
        } elseif ($uow->isScheduledForDelete($content)) {
            self::$em
                ->getRepository('BackBee\ClassContent\Indexes\OptContentByModified')
                ->removeOptContentTable($content)
            ;

            foreach ($content->getIndexation() as $index) {
                self::$em->remove($index);
                self::$em
                    ->getUnitOfWork()
                    ->computeChangeSet(self::$em->getClassMetadata('BackBee\ClassContent\Indexation'), $index)
                ;
            }
        }
    }

    /**
     * Indexes $content according to the class content property `indexation`.
     * 
     * @param AbstractClassContent $content The content to index.
     */
    private static function indexContent(AbstractClassContent $content)
    {
        if (!self::hasIndexedElements($content)) {
            return;
        }

        foreach ($content->getProperty('indexation') as $indexedElement) {
            $indexedElement = (array) $indexedElement;
            list($value, $owner) = self::getIndexValue($content, $indexedElement[0]);

            $callback = array_key_exists(1, $indexedElement) ? $indexedElement[1] : null;
            if (null !== $callback) {
                $value = self::applyCallbacks($value, (array) $callback);
            }

            if (null !== $value && null !== $owner) {
                $index = new Indexation($content, $indexedElement[0], $owner, $value, serialize($callback));
                self::$em->getRepository('BackBee\ClassContent\Indexation')->save($index);
            }
        }
    }

    /**
     * Reindexes parent content of $content.
     * 
     * @param AbstractClassContent $content The content to index.
     */
    private static function updateSuperContentIndexes(AbstractClassContent $content)
    {
        $alreadyIndexed = self::$em->getRepository('BackBee\ClassContent\Indexation')->findBy(['_owner' => $content]);
        foreach ($alreadyIndexed as $index) {
            if (null === $field = $index->getField()) {
                continue;
            }

            list($value, $owner) = self::getIndexValue($index->getContent(), $field);

            $callback = unserialize($index->getCallback());
            if (null !== $callback) {
                $value = self::applyCallbacks($value, (array) $callback);
            }

            if (null !== $value) {
                $index->setValue($value);
                self::$em->getRepository('BackBee\ClassContent\Indexation')->save($index);
            }
        }
    }

    /**
     * Returns the indexed value.
     * 
     * @param  AbstractClassContent $content      The content flushed.
     * @param  string               $indexedField The parameter to index.
     * 
     * @return array                              An array of the value and the content owner.
     */
    private static function getIndexValue(AbstractClassContent $content, $indexedField)
    {
        if ('@' === substr($indexedField, 0, 1)) {
            // parameter indexation
            return self::getParamValue($content, substr($indexedField, 1));
        } else {
            // element indexation
            return self::getContentValue($content, $indexedField);
        }
    }

    /**
     * Returns TRUE if something has to be indexed for $content.
     * 
     * @param  AbstractClassContent $content The content to index.
     * 
     * @return boolean
     */
    private static function hasIndexedElements(AbstractClassContent $content)
    {
        return is_array($content->getProperty()) && array_key_exists('indexation', $content->getProperty());
    }

    /**
     * Returns the indexed parameter value.
     * 
     * @param  AbstractClassContent $content      The content flushed.
     * @param  string               $indexedParam The parameter to index.
     * 
     * @return array                              An array of the parameter value and the content owner.
     */
    private static function getParamValue(AbstractClassContent $content, $indexedParam)
    {
        $value = (array) $content->getParamValue($indexedParam);
        $owner = $content;

        return [implode(',', $value), $owner];
    }

    /**
     * Returns the indexed value of an element.
     * 
     * @param  AbstractClassContent $content        The content flushed.
     * @param  string               $indexedElement The parameter to index.
     * 
     * @return array                                An array of the element value and the content owner.
     */
    private static function getContentValue(AbstractClassContent $content, $indexedElement)
    {
        $value = $owner = $content;
        $elements = explode('->', $indexedElement);
        foreach ($elements as $element) {
            $value = $value->getData($element);
            if (!$value instanceof AbstractClassContent) {
                break;
            }

            if (!self::$em->contains($value)) {
                $value = self::$em->find(get_class($value), $value->getUid());
            }

            $owner = $value;
        }

        return [$value, $owner];
    }

    /**
     * Applies callbacks to value.
     * 
     * @param  mixed $value     The value on which apply callbacks.
     * @param  array $callbacks An array of callable function.
     * 
     * @return mixed            The new value.
     */
    private static function applyCallbacks($value, array $callbacks)
    {
        foreach ($callbacks as $callback) {
            $value = call_user_func($callback, $value);
        }

        return $value;
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents()
    {
        return [
            'classcontent.onflush'    => 'onFlushContent',
            'nestednode.page.onflush' => 'onFlushPage',
        ];
    }
}
