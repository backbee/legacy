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

namespace BackBee\ClassContent\Listener;

use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\ContentSet;
use BackBee\ClassContent\Element\File as ElementFile;
use BackBee\ClassContent\Element\Image as ElementImage;
use BackBee\ClassContent\Revision;
use BackBee\Event\Event;
use BackBee\Security\Token\BBUserToken;
use BackBee\Util\File\File;

/**
 * Listener to ClassContent events :
 *    - classcontent.onflush: occurs when a classcontent entity is mentioned for current flush.
 *
 * @category    BackBee
 *
 *
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class RevisionListener
{
    /**
     * @param Event $event
     */
    public static function onRemoveContent(Event $event)
    {
        $content = $event->getTarget();
        if (!($content instanceof AbstractClassContent)) {
            return;
        }

        $dispatcher = $event->getDispatcher();
        if (null === $dispatcher) {
            return;
        }

        $application = $dispatcher->getApplication();
        if (null === $application) {
            return;
        }

        $em = $application->getEntityManager();

        $revisions = $em->getRepository('BackBee\ClassContent\Revision')->getRevisions($content);
        foreach ($revisions as $revision) {
            $revision->setContent(null);
            $revision->setState(Revision::STATE_DELETED);
        }
    }

    /**
     * Occurs on classcontent.onflush events.
     *
     * @param Event $event
     */
    public static function onFlushContent(Event $event)
    {
        $content = $event->getTarget();
        $application = $event->getApplication();

        $token = $application->getSecurityContext()->getToken();
        if (!($token instanceof BBUserToken)) {
            return;
        }

        $em = $application->getEntityManager();
        $uow = $em->getUnitOfWork();

        if ($uow->isScheduledForInsert($content) && AbstractClassContent::STATE_NEW === $content->getState()) {
            if (null === $draft = $content->getDraft()) {
                $draft = $em->getRepository('BackBee\ClassContent\Revision')->checkout($content, $token);
            }

            $content->setDraft($draft);
            $em->persist($draft);
            $uow->computeChangeSet($em->getClassMetadata('BackBee\ClassContent\Revision'), $draft);
        } elseif ($uow->isScheduledForDelete($content)) {
            $revisions = $em->getRepository('BackBee\ClassContent\Revision')->getRevisions($content);
            foreach ($revisions as $revision) {
                $revision->setContent(null);
                $revision->setState(Revision::STATE_DELETED);
                $uow->computeChangeSet($em->getClassMetadata('BackBee\ClassContent\Revision'), $revision);
            }
        }
    }

    /**
     * Occurs on revision.onflush events.
     *
     * @param Event $event
     */
    public static function onFlushElementFile(Event $event)
    {
        $revision = $event->getTarget();
        $content = $revision->getContent();

        if (!($content instanceof ElementFile) || !is_file($content->path)) {
            return;
        }

        $application = $event->getApplication();
        $em = $application->getEntityManager();
        $uow = $em->getUnitOfWork();

        if ($uow->isScheduledForDelete($content)) {
            return;
        }

        $fileRepository = $em->getRepository('BackBee\ClassContent\Element\File');
        $fileRepository->setDirectories($application);

        $fileRepository->commitFile($content);

        $moveFrom = $content->path;
        File::resolveFilepath($moveFrom, null, array('base_dir' => $application->getMediaDir()));

        if (file_exists($moveFrom)) {
            $content->setParam('stat', json_encode(stat($moveFrom)));

            if ($content instanceof ElementImage) {
                list($width, $height) = getimagesize($moveFrom);

                $content->setParam('width', $width);
                $content->setParam('height', $height);
            }
        }

        $uow->recomputeSingleEntityChangeSet($em->getClassMetadata('BackBee\ClassContent\Revision'), $revision);
    }

    /**
     * Occurs on classcontent.onflush events.
     *
     * @param Event $event
     */
    public static function onPostLoad(Event $event)
    {
        $revision = $event->getTarget();
        $dispatcher = $event->getDispatcher();
        $application = $dispatcher->getApplication();

        if (null !== $application) {
            $em = $application->getEntityManager();
            $revision
                ->setEntityManager($em)
                ->setToken($application->getBBUserToken())
            ;

            if (null === $revision->getContent()) {
                $db = $em->getConnection();
                $stmt = $db->executeQuery(
                    'SELECT `content_uid`, `classname` FROM `revision` WHERE `uid` = ?',
                    [$revision->getUid()]
                );

                $items = $stmt->fetchAll();
                if ($items) {
                    foreach ($items as $item) {
                        $content = $em->find(Revision::getFullClassname($item['classname']), $item['content_uid']);
                        if ($content) {
                            $revision->setContent($content);
                        }
                    }
                }
            }
        }

        $revision->postLoad();
    }

    /**
     * Occurs on classcontent.prerender events.
     *
     * @param Event $event
     */
    public static function onPrerenderContent(Event $event)
    {
        $dispatcher = $event->getDispatcher();
        if (null === $application = $dispatcher->getApplication()) {
            return;
        }
        if (null === $token = $application->getBBUserToken()) {
            return;
        }

        $renderer = $event->getEventArgs();
        if (!is_a($renderer, 'BackBee\Renderer\AbstractRenderer')) {
            return;
        }

        $content = $renderer->getObject();
        if (!is_a($content, 'BackBee\ClassContent\AbstractClassContent')) {
            return;
        }

        $em = $application->getEntityManager();
        if (null !== $revision = $em->getRepository('BackBee\ClassContent\Revision')->getDraft($content, $token)) {
            $content->setDraft($revision);
            $application->debug(sprintf(
                'Revision found for `%s` content and `%s` user',
                $content->getUid(),
                $token->getUsername()
            ));
        }

        if (!($content instanceof ContentSet)) {
            foreach ($content->getData() as $key => $subcontent) {
                if (null === $subcontent) {
                    $contenttype = $content->getAcceptedType($key);
                    if (0 === strpos($contenttype, 'BackBee\ClassContent\\')) {
                        if (null === $content->getDraft()) {
                            $revision = $em
                                ->getRepository('BackBee\ClassContent\Revision')
                                ->getDraft($content, $token, true)
                            ;
                            $content->setDraft($revision);
                        }
                        $content->$key = new $contenttype();
                    }
                }
            }
        }
    }
}
