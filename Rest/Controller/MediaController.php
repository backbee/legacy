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

namespace BackBee\Rest\Controller;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\Exception\InvalidContentTypeException;
use BackBee\NestedNode\Media;
use BackBee\NestedNode\MediaFolder;
use BackBee\Rest\Controller\Annotations as Rest;

/**
 * Description of MediaController
 *
 * @author h.baptiste <harris.baptiste@lp-digital.fr>
 * @author Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class MediaController extends AbstractRestController
{
    /**
     * Creates an instance of MediaController.
     *
     * @param ContainerInterface|null $container
     * @internal param ContainerInterface $app
     */
    public function setContainer(ContainerInterface $container = null)
    {
        parent::setContainer($container);

        if ($this->getApplication()) {
            $mediaClasses = $this->getApplication()->getAutoloader()->glob('Media'.DIRECTORY_SEPARATOR.'*');
            foreach ($mediaClasses as $mediaClass) {
                class_exists($mediaClass);
            }
        }
    }

    /**
     * @param Request $request
     *
     * @Rest\Pagination(default_count=25, max_count=100)
     * @return Response
     */
    public function getCollectionAction(Request $request, $start, $count)
    {
        $mediafolder = null;
        $folderUid = $request->get('mediaFolder_uid', null);

        if (null === $folderUid) {
            $mediafolder = $this->getMediaFolderRepository()->getRoot();
        } else {
            $mediafolder = $this->getMediaFolderRepository()->find($folderUid);
        }

        if (null === $mediafolder) {
            throw new NotFoundHttpException('Cannot find a media folder');
        }

        $paginator = null;

        if ($request->query->has('content_uid')) {
            $paginator = $this->getCollectionByContent($request->query->get('content_uid'), $mediafolder);
        } else {
            $paginator = $this->getClassicCollection($request, $mediafolder, $start, $count);
        }

        $iterator = $paginator->getIterator();
        $results = [];
        while ($iterator->valid()) {

            if($this->isGranted('VIEW', $iterator->current()->getMediaFolder())){

                $results[] = $iterator->current();
            }

            $iterator->next();
        }

        $pager = $request->query->has('usePagination') ? $paginator : null;

        return $this->addRangeToContent(
            $this->createJsonResponse($this->mediaToJson($results)),
            $pager,
            $start,
            count($results)
        );
    }

    /**
     * Delete media.
     *
     * @param  mixed $id
     * @return Response
     * @throws BadRequestHttpException
     */
    public function deleteAction($id)
    {
        if (null === $media = $this->getMediaRepository()->find($id)) {
            throw new NotFoundHttpException(sprintf('Cannot find media with id `%s`.', $id));
        }

        $em = $this->getEntityManager();

        try {
            $em->getRepository('BackBee\ClassContent\AbstractClassContent')->deleteContent($media->getContent(), true);
            $em->remove($media);
            $em->flush();
        } catch (\Exception $e) {
            throw new BadRequestHttpException(sprintf('Error while deleting media `%s`: %s', $id, $e->getMessage()));
        }

        return $this->createJsonResponse(null, 204);
    }

    /**
     * Update media content's and folder
     */
    public function putAction($id, Request $request)
    {
        $mediaTitle = $request->get('title', 'Untitled media');
        $mediaFolderUid = $request->get('media_folder', null);
        $media = $this->getMediaRepository()->find($id);
        $currentMediaFoldierUid = $media->getMediaFolder()->getUid();

        if (null === $media) {
            throw new BadRequestHttpException(sprintf('Cannot find media with id `%s`.', $id));
        }

        $media->setTitle($mediaTitle);

        if ((null !== $mediaFolderUid) && ($mediaFolderUid !== $currentMediaFoldierUid)) {
            $mediaFolder = $this->getMediaFolderRepository()->find($mediaFolderUid);
            if (null !== $mediaFolder) {
                $media->setMediaFolder($mediaFolder);
            }
        }

        $this->autoCommitContent($media->getContent());
        $this->getEntityManager()->persist($media);
        $this->getEntityManager()->flush();

        return $this->createJsonResponse(null, 204);
    }

    /**
     * Post a new media.
     *
     * @param  Request $request
     * @return Response
     * @throws BadRequestHttpException
     */
    public function postAction(Request $request)
    {
        $contentUid = $request->request->get('content_uid');
        $contentType = $request->request->get('content_type', null);
        $mediaFolderUid = $request->request->get('folder_uid', null);
        $mediaTitle = $request->request->get('title', 'Untitled media');

        if (null === $mediaFolderUid) {
            $mediaFolder = $this->getMediaFolderRepository()->getRoot();
        } else {
            $mediaFolder = $this->getMediaFolderRepository()->find($mediaFolderUid);
        }

        if (null === $mediaFolder) {
            throw new NotFoundHttpException('Cannot find a media folder');
        }

        if (null !== $content = $this->getClassContentManager()->findOneByTypeAndUid($contentType, $contentUid)) {
            $this->autoCommitContent($content);
        }

        $media = new Media();
        $media->setContent($content);
        $media->setTitle($mediaTitle);
        $media->setMediaFolder($mediaFolder);

        $this->getEntityManager()->persist($media);
        $this->getEntityManager()->flush();

        return $this->createJsonResponse(null, 201, [
            'BB-RESOURCE-UID' => $media->getId(),
            'Location'        => $this->getApplication()->getRouting()->getUrlByRouteName(
                'bb.rest.media.get',
                [
                    'version' => $request->attributes->get('version'),
                    'uid'     => $media->getId(),
                ],
                '',
                false
            ),
        ]);
    }

    /**
     * Return an media repository.
     *
     * @return EntityRepository
     */
    private function getMediaRepository()
    {
        return $this->getEntityManager()->getRepository('BackBee\NestedNode\Media');
    }

    /**
     * Return an media folder repository.
     *
     * @return EntityRepository
     */
    private function getMediaFolderRepository()
    {
        return $this->getEntityManager()->getRepository('BackBee\NestedNode\MediaFolder');
    }

    /**
     * Get class content manager.
     *
     * @return mixed
     */
    private function getClassContentManager()
    {
        $manager = $this->getApplication()
                        ->getContainer()
                        ->get('classcontent.manager')
                        ->setBBUserToken($this->getApplication()->getBBUserToken());

        return $manager;
    }

    /**
     * Generate json.
     *
     * @param $collection
     * @return array
     */
    private function mediaToJson($collection)
    {
        $result = [];
        foreach ($collection as $media) {
            $content = $media->getContent();

            if (null !== $draft = $this->getClassContentManager()->getDraft($content)) {
                $content->setDraft($draft);
            }

            // we also need to load content's elements draft
            foreach ($content->getData() as $element) {
                if (null !== $draft = $this->getClassContentManager()->getDraft($element)) {
                    $element->setDraft($draft);
                }
            }

            $mediaJson = $media->jsonSerialize();
            $contentJson = $this->getClassContentManager()->jsonEncode($media->getContent());
            $mediaJson['image'] = $contentJson['image'];
            $result[] = $mediaJson;
        }

        return $result;
    }

    /**
     * Add range to content.
     *
     * @param   Response    $response
     * @param   array       $collection
     * @param   int         $offset
     * @param   int         $limit
     * @return  Response
     */
    private function addRangeToContent(Response $response, $collection, $offset, $limit)
    {
        $total = "*";
        if ($collection instanceof Paginator) {
            $total = count($collection);
        }

        $lastResult = $offset + $limit - 1;
        $lastResult = $lastResult < 0 ? 0 : $lastResult;
        $response->headers->set('Content-Range', "$offset-$lastResult/" . $total);

        return $response;
    }

    /**
     * Get classic collection.
     *
     * @param   Request     $request
     * @param   MediaFolder $mediaFolder
     * @param   int         $start
     * @param   int         $count
     * @return  mixed
     */
    private function getClassicCollection(Request $request, $mediaFolder, $start, $count)
    {
        $params = $request->query->all();
        $contentType =  $request->get('contentType', null);

        if (null !== $contentType) {
            try {
                $params['contentType'] = AbstractClassContent::getClassnameByContentType($contentType);
            } catch (InvalidContentTypeException $e) {
                throw new NotFoundHttpException(sprintf('Provided content type (:%s) is invalid.', $params['contentType']));
            }
        }

        return $this->getMediaRepository()->getMedias($mediaFolder, $params, '_modified', 'desc', [
            'start' => $start,
            'limit' => $count,
        ]);
    }

    /**
     * Get collection by content.
     *
     * @param   string      $contentUid
     * @param   MediaFolder $mediaFolder
     * @return  mixed
     */
    private function getCollectionByContent($contentUid, MediaFolder $mediaFolder)
    {
        $content = $this->getEntityManager()->find('BackBee\ClassContent\AbstractClassContent', $contentUid);

        if (null === $content) {
            throw new NotFoundHttpException("No content find with uid '{$contentUid}'");
        }

        return $this->getMediaRepository()->getMediasByContent($content, $mediaFolder);
    }

    /**
     * Auto commit content put or post in the library.
     *
     * @param AbstractClassContent $content
     */
    private function autoCommitContent(AbstractClassContent $content)
    {
        // Commit subelement of the Media content
        foreach ($content->getData() as $subcontent) {
            if (!($subcontent instanceof AbstractClassContent)) {
                continue;
            }

            $this->commit($subcontent);
        }

        // Commit the Media content itself
        $this->commit($content);
    }

    /**
     * Commit the content ignoring the execption throws if no draft available.
     *
     * @param AbstractClassContent $content
     */
    private function commit(AbstractClassContent $content)
    {
        try {
            $this->getClassContentManager()->commit($content);
        } catch (\Exception $ex) {
            // No draft available, skip it
        }
    }
}
