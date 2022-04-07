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

namespace BackBee\Rest\Controller;

use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\Constraints as Assert;

use BackBee\Exception\InvalidArgumentException;
use BackBee\NestedNode\MediaFolder;
use BackBee\Rest\Controller\Annotations as Rest;
use BackBee\Rest\Patcher\EntityPatcher;
use BackBee\Rest\Patcher\Exception\InvalidOperationSyntaxException;
use BackBee\Rest\Patcher\Exception\UnauthorizedPatchOperationException;
use BackBee\Rest\Patcher\OperationSyntaxValidator;
use BackBee\Rest\Patcher\RightManager;
use BackBee\Util\StringUtils;

/**
 * Description of MediaFolderController
 *
 * @author      h.baptiste <harris.baptiste@lp-digital.fr>
 * @author      Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class MediaFolderController extends AbstractRestController
{
    /**
     * Get collection of media folder
     *
     * @return Response
     *
     * @Rest\Pagination(default_count=100, max_count=200)
     *
     * @Rest\ParamConverter(
     *   name="parent", id_name="parent_uid", id_source="query", class="BackBee\NestedNode\MediaFolder", required=false
     * )
     * @Rest\Security("is_fully_authenticated() & has_role('ROLE_API_USER')")
     */
    public function getCollectionAction($start, MediaFolder $parent = null)
    {
        $mediaFolders = [];

        $results = $this->getMediaFolderRepository()->getMediaFolders($parent, [
            'field' => '_leftnode',
            'dir'   => 'asc',
        ]);

        foreach ($results as $folder) {

            if($this->isGranted('VIEW', $folder) || true === $folder->isRoot()){

                $mediaFolders[] = $folder;
            }
        }

        return $this->addRangeToContent($this->createJsonResponse($mediaFolders), $mediaFolders, $start);
    }

    /**
     * @param MediaFolder $mediaFolder
     * @return Response
     *
     * @Rest\ParamConverter(name="mediaFolder", class="BackBee\NestedNode\MediaFolder")
     * @Rest\Security("is_fully_authenticated() & has_role('ROLE_API_USER')")
     */
    public function getAction(MediaFolder $mediaFolder)
    {
        if (!$this->isGranted('VIEW', $mediaFolder)) {
            throw new AccessDeniedHttpException(sprintf('You are not authorized to view %s MediaFolder.', $mediaFolder->getTitle()));
        }

        return $this->createJsonResponse($mediaFolder);
    }

    /**
     * @return Response
     *
     * @Rest\RequestParam(name="title", description="media title", requirements={
     *      @Assert\NotBlank()
     * })
     * @Rest\ParamConverter(name="mediaFolder", class="BackBee\NestedNode\MediaFolder")
     * @Rest\Security("is_fully_authenticated() & has_role('ROLE_API_USER')")
     */
    public function putAction(MediaFolder $mediaFolder, Request $request)
    {
        if (!$this->isGranted('EDIT', $mediaFolder)) {
            throw new AccessDeniedHttpException(sprintf('You are not authorized to edit %s MediaFolder.', $mediaFolder->getTitle()));
        }

        $parentId = $request->get('parent_uid', null);
        if (null === $parentId) {
            $parent = $this->getMediaFolderRepository()->getRoot();
        } else {
            $parent = $this->getMediaFolderRepository()->find($parentId);
        }

        $title = trim($request->request->get('title'));

        if ($this->mediaFolderAlreadyExists($title, $parent)) {
            throw new BadRequestHttpException(sprintf('A MediaFolder named %s already exists.', $title));
        }

        $mediaFolder->setTitle($title);

        $this->getEntityManager()->persist($mediaFolder);
        $this->getEntityManager()->flush();

        return $this->createJsonResponse(null, 204);
    }

    /**
     * @return Response
     *
     * @Rest\ParamConverter(name="mediaFolder", class="BackBee\NestedNode\MediaFolder")
     * @Rest\Security("is_fully_authenticated() & has_role('ROLE_API_USER')")
     */
    public function deleteAction(MediaFolder $mediaFolder)
    {
        if (!$this->isGranted('DELETE', $mediaFolder)) {
            throw new AccessDeniedHttpException(sprintf('You are not authorized to delete %s MediaFolder.', $mediaFolder->getTitle()));
        }

        if ($mediaFolder->isRoot()) {
            throw new BadRequestHttpException('Cannot remove the root node of the MediaFolder.');
        }

        $response = new Response('', 204);
        if (0 === (int) $this->getMediaRepository()->countMedias($mediaFolder)) {
            $this->getMediaFolderRepository()->delete($mediaFolder);
        } else {
            $response = new Response(sprintf('MediaFolder `%s` is not empty.', $mediaFolder->getTitle()), 500);
        }

        return $response;
    }

    /**
     * Create a media folder
     * and if a parent is provided added has its last child
     *
     * @param MediaFolder $mediaFolder
     *
     * @Rest\RequestParam(name="title", description="media title", requirements={
     *   @Assert\NotBlank()
     * })
     * @Rest\ParamConverter(
     *   name="parent", id_name="parent_uid", id_source="request", class="BackBee\NestedNode\MediaFolder", required=false
     * )
     * @Rest\Security("is_fully_authenticated() & has_role('ROLE_API_USER')")
     */
    public function postAction(Request $request, $parent = null)
    {
        $title = trim($request->request->get('title'));

        if (!$this->isGranted('CREATE', $parent)) {
            throw new AccessDeniedHttpException(sprintf('You are not authorized to create %s MediaFolder.', $title));
        }

        try {
            $uid = $request->request->get('uid', null);
            if (null !== $uid) {
                $mediaFolder = $this->getMediaFolderRepository()->find($uid);
                $mediaFolder->setTitle($title);
            } else {
                $mediaFolder = new MediaFolder();
                $mediaFolder->setUrl($request->request->get('url', StringUtils::urlize($title)));
                $mediaFolder->setTitle($title);
                if (null === $parent) {
                    $parent = $this->getMediaFolderRepository()->getRoot();
                }

                if ($this->mediaFolderAlreadyExists($title, $parent)) {
                    throw new BadRequestHttpException(sprintf(
                        'A MediaFolder named `%s` already exists in `%s`.',
                        $title,
                        $parent->getTitle()
                    ));
                }

                $mediaFolder->setParent($parent);
                $this->getMediaFolderRepository()->insertNodeAsLastChildOf($mediaFolder, $parent);
            }

            $this->getEntityManager()->persist($mediaFolder);
            $this->getEntityManager()->flush();

            $response = $this->createJsonResponse(null, 201, [
                'BB-RESOURCE-UID' => $mediaFolder->getUid(),
                'Location'        => $this->getApplication()->getRouting()->getUrlByRouteName(
                    'bb.rest.media-folder.get',
                    [
                        'version' => $request->attributes->get('version'),
                        'uid'     => $mediaFolder->getUid(),
                    ],
                    '',
                    false
                ),
            ]);
        } catch (\Exception $e) {
            $response = $this->createResponse(sprintf('Internal server error: %s', $e->getMessage()));
        }

        return $response;
    }

    /**
     * @param  MediaFolder $mediaFolder
     * @param  Request     $request
     * @return Response
     *
     * @Rest\ParamConverter(name="mediaFolder", class="BackBee\NestedNode\MediaFolder")
     * @Rest\Security("is_fully_authenticated() & has_role('ROLE_API_USER')")
     */
    public function patchAction(MediaFolder $mediaFolder, Request $request)
    {
        if (!$this->isGranted('EDIT', $mediaFolder)) {
            throw new AccessDeniedHttpException(sprintf('You are not authorized to edit %s MediaFolder.', $mediaFolder->getTitle()));
        }

        $operations = $request->request->all();
        try {
            (new OperationSyntaxValidator())->validate($operations);
        } catch (InvalidOperationSyntaxException $e) {
            throw new BadRequestHttpException('operation invalid syntax: ' . $e->getMessage());
        }

        $this->patchSiblingAndParentOperation($mediaFolder, $operations);
        $entityPatcher = new EntityPatcher(new RightManager($this->getSerializer()->getMetadataFactory()));
        try {
            $entityPatcher->patch($mediaFolder, $operations);
        } catch (UnauthorizedPatchOperationException $e) {
            throw new BadRequestHttpException('Invalid patch operation: '.$e->getMessage());
        }

        $this->getEntityManager()->flush();
        return $this->createJsonResponse(null, 204);
    }

    private function getMediaFolderRepository()
    {
        return $this->getEntityManager()->getRepository('BackBee\NestedNode\MediaFolder');
    }

    private function patchSiblingAndParentOperation(MediaFolder $mediaFolder, &$operations)
    {
        $sibling_operation = null;
        $parent_operation = null;

        foreach ($operations as $key => $operation) {
            $op = array('key' => $key, 'op' => $operation);
            if ('/sibling_uid' === $operation['path']) {
                $sibling_operation = $op;
            } elseif ('/parent_uid' === $operation['path']) {
                $parent_operation = $op;
            }
        }

        if (null !== $sibling_operation || null !== $parent_operation) {
            if ($mediaFolder->isRoot()) {
                throw new BadRequestHttpException('Cannot move root node of a site.');
            }
            try {
                if (null !== $sibling_operation) {
                    unset($operations[$sibling_operation['key']]);

                    $sibling = $this->getMediaFolderByUid($sibling_operation['op']['value']);
                    $this->getMediaFolderRepository()->moveAsPrevSiblingOf($mediaFolder, $sibling);
                } elseif (null !== $parent_operation) {
                    unset($operations[$parent_operation['key']]);

                    $parent = $this->getMediaFolderByUid($parent_operation['op']['value']);
                    $this->getMediaFolderRepository()->moveAsLastChildOf($mediaFolder, $parent);
                }
            } catch (InvalidArgumentException $e) {
                throw new BadRequestHttpException(sprintf('Invalid node move action: %s', $e->getMessage()));
            }
        }
    }

    private function getMediaRepository()
    {
        return $this->getEntityManager()->getRepository('BackBee\NestedNode\Media');
    }

    private function getMediaFolderByUid($uid)
    {
        if (null === $mediaFolder = $this->getMediaFolderRepository()->find($uid)) {
            throw new NotFoundHttpException("Unable to find mediaFolder with uid `$uid`");
        }

        return $mediaFolder;
    }

    private function mediaFolderAlreadyExists($title, MediaFolder $parent)
    {
        $folderExists = false;
        $medialFolder = $this->getMediaFolderRepository()->findOneBy([
            '_title'  => trim($title),
            '_parent' => $parent,
        ]);

        if (null !== $medialFolder) {
            $folderExists = true;
        }

        return $folderExists;
    }

    private function addRangeToContent(Response $response, $collection, $start)
    {
        $count = count($collection);
        if ($collection instanceof Paginator) {
            $count = count($collection->getIterator());
        }

        $lastResult = $start + $count - 1;
        $lastResult = $lastResult < 0 ? 0 : $lastResult;
        $response->headers->set('Content-Range', "$start-$lastResult/" . count($collection));

        return $response;
    }

    /**
     * Get folder ancestors
     * @param Folder $folder the folder we want to get its ancestors
     * @return \Symfony\Component\HttpFoundation\Response
     * @Rest\ParamConverter(name="folder", class="BackBee\NestedNode\MediaFolder")
     */
    public function getAncestorsAction(MediaFolder $folder)
    {
        $ancestors = $this->getMediaFolderRepository()->getAncestors($folder);

        return $this->createJsonResponse($ancestors);
    }

    /**
     * @api {get} /media-folder/:group/permissions Get permissions (ACL)
     * @apiName getPermissionsAction
     * @apiGroup Media Folder
     * @apiVersion 0.2.0
     *
     * @apiPermission ROLE_API_USER
     *
     * @apiError NoAccessRight Invalid authentication information.
     * @apiError GroupNotFound No <strong>BackBee\\Security\\Group</strong> exists with uid <code>group</code>
     *
     * @apiHeader {String} X-API-KEY User's public key.
     * @apiHeader {String} X-API-SIGNATURE Api signature generated for the request.
     *
     * @apiParam {Number} group Group id.
     *
     * @apiSuccess {String} uid Id of media folder.
     * @apiSuccess {String} label Label of media folder.
     * @apiSuccess {String} class Classname of media folder.
     * @apiSuccess {Array} rights Contains rights for the current group.
     *
     * @apiSuccessExample Success-Response:
     * HTTP/1.1 200 OK
     * {
     *      "uid": "62933a2951ef01f4eafd9bdf4d3cd2f0",
     *      "label": "Mediacenter",
     *      "class": "BackBee\\NestedNode\\MediaFolder",
     *      "rights": {
     *          "total": 3,
     *          "view": 1,
     *          "create": 1,
     *          "edit": 0,
     *          "delete": 0,
     *          "commit": 0,
     *          "publish": 0
     *      }
     * }
     */

    /**
     * Get permissions (ACL)
     *
     * @Rest\ParamConverter(name="group", id_name = "group", class="BackBee\Security\Group")
     *
     * @Rest\Security("is_fully_authenticated() & has_role('ROLE_API_USER')")
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getPermissionsAction(Request $request)
    {
        $group = $request->attributes->get('group');
        $aclManager = $this->getContainer()->get('security.acl_manager');
        $parentClass = 'BackBee\NestedNode\MediaFolder';

        $mediaFolders = $this->getMediaFolderRepository()->getMediaFolderByLevels([1]);

        $data['parent'] = [
            'class' => $parentClass,
        ];

        foreach ($mediaFolders as $folder){

            $data['objects'][] = [
                'uid' => (false === $folder->isRoot()) ? $folder->getUid() : '',
                'label' => $folder->getTitle(),
                'rights' => $aclManager->getPermissions($folder, $group)
            ];
        }

        return $this->createJsonResponse($data, 200);
    }
}
