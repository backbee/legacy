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

use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\Category;
use BackBee\ClassContent\Exception\InvalidContentTypeException;
use BackBee\Rest\Controller\Annotations as Rest;
use BackBee\Rest\Patcher\Exception\InvalidOperationSyntaxException;
use BackBee\Rest\Patcher\Exception\UnauthorizedPatchOperationException;
use BackBee\Rest\Patcher\OperationSyntaxValidator;
use BackBee\Rest\Patcher\PatcherInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * ClassContent API Controller.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      e.chau <eric.chau@lp-digital.fr>
 * @author      d.bensid <djoudi.bensid@lp-digital.fr>
 */
class ClassContentController extends AbstractRestController
{
    /**
     * @var BackBee\ClassContent\ClassContentManager
     */
    private $manager;


    private $lastRequestIterator;

    /**
     * Returns category's datas if $id is valid.
     *
     * @param string $id category's id
     *
     * @return Response
     *
     * @Rest\Security("is_fully_authenticated() & has_role('ROLE_API_USER')")
     */

    public function getCategoryAction($id)
    {
        $category = $this->getCategoryManager()->getCategory($id);
        if (null === $category) {
            throw new NotFoundHttpException("Classcontent's category `$id` not found.");
        }

        return $this->createJsonResponse($category);
    }

    /**
     * Returns every availables categories datas.
     *
     * @return Response
     *
     * @Rest\Security("is_fully_authenticated() & has_role('ROLE_API_USER')")
     */
    public function getCategoryCollectionAction()
    {
        $application = $this->getApplication();
        $cache = $application->getContainer()->get('cache.control');
        $cacheId = md5('classcontent_categories_' . $application->getContext() . '_' . $application->getEnvironment());

        if (!$application->isDebugMode() && false !== $value = $cache->load($cacheId)) {
            $categories = json_decode($value, true);
        } else {
            $categories = [];
            foreach ($this->getCategoryManager()->getCategories() as $id => $category) {
                $categories[] = array_merge(['id' => $id], $category->jsonSerialize());
            }

            $cache->save($cacheId, json_encode($categories));
        }

        return $this->addContentRangeHeadersToResponse($this->createJsonResponse($categories), $categories, 0);
    }

    /**
     * Returns collection of classcontent associated to category and according to provided criterias.
     *
     * @return Symfony\Component\HttpFoundation\Response
     *
     * @Rest\Pagination(default_count=25, max_count=100)
     * @Rest\Security("is_fully_authenticated() & has_role('ROLE_API_USER')")
     */
    public function getCollectionAction($start, $count, Request $request)
    {
        $contents = [];
        $format = $this->getFormatParam();
        $response = $this->createJsonResponse();
        $categoryName = $request->query->get('category', null);
        $usePagination = $request->query->get('usePagination', true);
        if (AbstractClassContent::JSON_DEFINITION_FORMAT === $format) {
            $response->setData($contents = $this->getClassContentDefinitionsByCategory($categoryName));
            $start = 0;
        } else {
            if (null !== $categoryName) {
                $contents = $this->getClassContentByCategory($categoryName, $start, $count);
            } else {
                $classnames = $this->getClassContentManager()->getAllClassContentClassnames();
                $contents = $this->findContentsByCriteria($classnames, $start, $count);
            }

            $data = $this->getClassContentManager()->jsonEncodeCollection($contents, $this->getFormatParam());
            $response->setData($data);
        }

        return $this->addContentRangeHeadersToResponse($response, $contents, $start, (boolean)$usePagination);
    }

    /**
     * Returns collection of classcontent associated to $type and according to provided criterias.
     *
     * @param string $type
     *
     * @return Symfony\Component\HttpFoundation\Response
     *
     * @Rest\Pagination(default_count=25, max_count=100)
     * @Rest\Security("is_fully_authenticated() & has_role('ROLE_API_USER')")
     */
    public function getCollectionByTypeAction($type, $start, $count)
    {
        $classname = AbstractClassContent::getClassnameByContentType($type);
        $contents = $this->findContentsByCriteria((array)$classname, $start, $count);
        $response = $this->createJsonResponse(
            $this->getClassContentManager()->jsonEncodeCollection(
                $contents,
                $this->getFormatParam()
            )
        );

        return $this->addContentRangeHeadersToResponse($response, $contents, $start);
    }

    /**
     * Get classcontent.
     *
     * @param string $type type of the class content (ex: Element/text)
     * @param string $uid  identifier of the class content
     *
     * @return Symfony\Component\HttpFoundation\Response
     *
     * @Rest\QueryParam(name="mode", description="The render mode to use")
     * @Rest\QueryParam(name="page_uid", description="The page to set to application's renderer before rendering")
     *
     * @Rest\Security("is_fully_authenticated() & has_role('ROLE_API_USER')")
     */
    public function getAction($type, $uid, Request $request)
    {
        $this->granted('VIEW', $content = $this->getClassContentByTypeAndUid($type, $uid, true));

        $response = null;
        if (in_array('text/html', $request->getAcceptableContentTypes())) {
            if (false != $pageUid = $request->query->get('page_uid')) {
                if (null !== $page = $this->getEntityManager()->find('BackBee\NestedNode\Page', $pageUid)) {
                    $this->getApplication()->getRenderer()->setCurrentPage($page);
                }
            }

            $mode = $request->query->get('mode', null);
            $response = $this->createResponse(
                $this->getApplication()->getRenderer()->render($content, $mode),
                200,
                'text/html'
            );
        } else {
            $response = $this->createJsonResponse();
            $response->setData($this->getClassContentManager()->jsonEncode($content, $this->getFormatParam()));
        }

        return $response;
    }

    /**
     * Creates class content according to provided type.
     *
     * @param string  $type
     * @param Request $request
     *
     * @return Response
     *
     * @Rest\Security("is_fully_authenticated() & has_role('ROLE_API_USER')")
     */
    public function postAction(string $type, Request $request): Response
    {
        $classname = AbstractClassContent::getClassnameByContentType($type);
        $content = new $classname();
        $this->granted('CREATE', $content);

        $response = $this->createJsonResponse(
            $content->jsonSerialize(),
            Response::HTTP_CREATED,
            [
                'BB-RESOURCE-UID' => $content->getUid(),
                'Location' => $this->getApplication()->getRouting()->getUrlByRouteName(
                    'bb.rest.classcontent.get',
                    [
                        'version' => $request->attributes->get('version'),
                        'type' => $type,
                        'uid' => $content->getUid(),
                    ],
                    '',
                    false
                ),
            ]
        );

        try {
            $this->getEntityManager()->persist($content);
            $content->setDraft($this->getClassContentManager()->getDraft($content, true));
            $this->getEntityManager()->flush();
        } catch (Exception $exception) {
            $response = new JsonResponse(
                sprintf(
                    'Unable to post content with error: %s',
                    $exception->getMessage()
                ),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        $data = $request->request->all();

        if (0 < count($data)) {
            try {
                $data = array_merge(
                    $data,
                    [
                        'type' => $type,
                        'uid' => $content->getUid(),
                    ]
                );

                $this->updateClassContent($type, $data['uid'], $data);
                $this->getEntityManager()->flush();
            } catch (Exception $exception) {
                $response = new JsonResponse(
                    sprintf(
                        'Unable to post content with error: %s',
                        $exception->getMessage()
                    ),
                    Response::HTTP_INTERNAL_SERVER_ERROR
                );
            }
        }

        return $response;
    }

    /**
     * Updates classcontent's elements and parameters.
     *
     * @param string  $type type of the class content (ex: Element/text)
     * @param string  $uid  identifier of the class content
     * @param Request $request
     *
     * @return JsonResponse
     *
     * @Rest\Security("is_fully_authenticated() & has_role('ROLE_API_USER')")
     */
    public function putAction(string $type, string $uid, Request $request): JsonResponse
    {
        try {
            $content = $this->updateClassContent($type, $uid, $request->request->all());
            $this->getEntityManager()->flush();
        } catch (Exception $exception) {
            return new JsonResponse(
                sprintf(
                    'Unable to put content with error: %s',
                    $exception->getMessage()
                ),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return $this->createJsonResponse($content->jsonSerialize(), Response::HTTP_NO_CONTENT);
    }

    /**
     * Updates collection of classcontent elements and parameters.
     *
     * @param Request $request
     *
     * @return Symfony\Component\HttpFoundation\JsonResponse
     *
     * @Rest\Security("is_fully_authenticated() & has_role('ROLE_API_USER')")
     */
    public function putCollectionAction(Request $request)
    {
        $result = [];
        foreach ($request->request->all() as $data) {
            if (!isset($data['type']) || !isset($data['uid'])) {
                throw new BadRequestHttpException("type and/or uid is missing.");
            }

            try {
                $content = $this->updateClassContent($data['type'], $data['uid'], $data);
                $this->granted('VIEW', $content);
                $this->granted('EDIT', $content);

                $result[] = [
                    'uid' => $content->getUid(),
                    'type' => $content->getContentType(),
                    'statusCode' => 200,
                    'message' => 'OK',
                ];
            } catch (AccessDeniedException $e) {
                $result[] = [
                    'uid' => $data['uid'],
                    'type' => $data['type'],
                    'statusCode' => 401,
                    'message' => $e->getMessage(),
                ];
            } catch (Exception $e) {
                $result[] = [
                    'uid' => $data['uid'],
                    'type' => $data['type'],
                    'statusCode' => 500,
                    'message' => $e->getMessage(),
                ];
            }
        }

        $this->getEntityManager()->flush();

        return $this->createJsonResponse($result);
    }

    /**
     * Delete a class content.
     *
     * @param string $type type of the class content (ex: Element/text)
     * @param string $uid  identifier of the class content
     *
     * @return JsonResponse
     *
     * @Rest\Security("is_fully_authenticated() & has_role('ROLE_API_USER')")
     */
    public function deleteAction(string $type, string $uid): JsonResponse
    {
        $this->granted('DELETE', $content = $this->getClassContentByTypeAndUid($type, $uid));

        try {
            $this->getEntityManager()->getRepository(AbstractClassContent::class)->deleteContent(
                $content
            );
            $this->getEntityManager()->flush();
        } catch (Exception $exception) {
            return $this->createJsonResponse(
                sprintf(
                    'Unable to delete content with parameters: type: %s and uid: %s. Here is the error: %s',
                    $type,
                    $uid,
                    $exception->getMessage()
                ),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return $this->createJsonResponse(null, 204);
    }

    /**
     * ClassContent's draft getter.
     *
     * @param string $type type of the class content (ex: Element/text)
     * @param string $uid  identifier of the class content
     *
     * @return Symfony\Component\HttpFoundation\JsonResponse
     *
     * @Rest\Security("is_fully_authenticated() & has_role('ROLE_API_USER')")
     */
    public function getDraftAction($type, $uid)
    {
        $this->granted('VIEW', $content = $this->getClassContentByTypeAndUid($type, $uid));

        return $this->createJsonResponse($this->getClassContentManager()->getDraft($content));
    }

    /**
     * Returns all drafts of current user.
     *
     * @return Symfony\Component\HttpFoundation\JsonResponse
     *
     * @Rest\Security("is_fully_authenticated() & has_role('ROLE_API_USER')")
     */
    public function getDraftCollectionAction()
    {
        $contents = $this->getEntityManager()
            ->getRepository('BackBee\ClassContent\Revision')
            ->getAllDrafts($this->getApplication()->getBBUserToken());

        $contents = $this->sortDraftCollection($contents);

        return $this->addContentRangeHeadersToResponse($this->createJsonResponse($contents), $contents, 0);
    }

    /**
     * Updates a classcontent's draft.
     *
     * @param string $type type of the class content (ex: Element/text)
     * @param string $uid  identifier of the class content
     *
     * @return Symfony\Component\HttpFoundation\JsonResponse
     *
     * @Rest\Security("is_fully_authenticated() & has_role('ROLE_API_USER')")
     */
    public function putDraftAction($type, $uid, Request $request)
    {
        $this->updateClassContentDraft($type, $uid, $request->request->all());
        $this->getEntityManager()->flush();

        return $this->createJsonResponse(null, 204);
    }

    /**
     * Set draft of a content to a specific revision
     *
     * @param string  $type      Type of the class content (ex: Element/text)
     * @param string  $uid       Unique identifier of the class content
     * @param Request $request   The current request, parameter `revision` will be
     *                           looked for. Possible values for `revision` are:
     *                           * negative value: revert to current revision decrease from value.
     *                           * empty value: revert to last committed revision.
     *                           * positive value: revert to specific revision if exists.
     *
     * @return Symfony\Component\HttpFoundation\JsonResponse
     *
     * @Rest\Security("is_fully_authenticated() & has_role('ROLE_API_USER')")
     */
    public function patchDraftAction($type, $uid, Request $request)
    {
        $operations = $request->request->all();
        try {
            (new OperationSyntaxValidator())->validate($operations);
        } catch (InvalidOperationSyntaxException $e) {
            throw new BadRequestHttpException('operation invalid syntax: ' . $e->getMessage());
        }

        $this->granted('EDIT', $content = $this->getClassContentByTypeAndUid($type, $uid));

        foreach ($operations as $operation) {
            if (
                PatcherInterface::REPLACE_OPERATION !== $operation['op'] ||
                '/revision' !== $operation['path']
            ) {
                throw new UnauthorizedPatchOperationException($content, $operation['path'], $operation['op']);
            }

            $revision = (int)$operation['value'];
            if (0 >= $revision) {
                $revision += $content->getRevision();
            }

            try {
                $this->getClassContentManager()->revertToRevision($content, $revision);
                $this->getEntityManager()->flush();
            } catch (\InvalidArgumentException $e) {
                throw new NotFoundHttpException(
                    sprintf('Unknown revision %d for content %s.', $revision, $content->getObjectIdentifier()), $e
                );
            }
        }

        return $this->createJsonResponse(null, 204);
    }

    /**
     * Updates collection of classcontents' drafts.
     *
     * @param Request $request
     *
     * @return Symfony\Component\HttpFoundation\JsonResponse
     *
     * @Rest\Security("is_fully_authenticated() & has_role('ROLE_API_USER')")
     */
    public function putDraftCollectionAction(Request $request)
    {
        $result = [];
        foreach ($request->request->all() as $data) {
            if (!isset($data['type']) || !isset($data['uid'])) {
                throw new BadRequestHttpException("type and/or uid is missing.");
            }

            try {
                $content = $this->updateClassContentDraft($data['type'], $data['uid'], $data);
                $result[] = [
                    'uid' => $content->getUid(),
                    'type' => $content->getContentType(),
                    'statusCode' => 200,
                    'message' => 'OK',
                ];
            } catch (AccessDeniedException $e) {
                $result[] = [
                    'uid' => $data['uid'],
                    'type' => $data['type'],
                    'statusCode' => 401,
                    'message' => $e->getMessage(),
                ];
            } catch (Exception $e) {
                $result[] = [
                    'uid' => $data['uid'],
                    'type' => $data['type'],
                    'statusCode' => 500,
                    'message' => $e->getMessage(),
                ];
            }
        }

        $this->getEntityManager()->flush();

        return $this->createJsonResponse($result);
    }

    /**
     * Getter of classcontent category manager.
     *
     * @return BackBee\ClassContent\CategoryManager
     */
    private function getCategoryManager()
    {
        return $this->getContainer()->get('classcontent.category_manager');
    }

    /**
     * Returns ClassContentManager.
     *
     * @return BackBee\ClassContent\ClassContentManager
     */
    private function getClassContentManager()
    {
        if (null === $this->manager) {
            $this->manager = $this->getApplication()->getContainer()->get('classcontent.manager')
                ->setBBUserToken($this->getApplication()->getBBUserToken());
        }

        return $this->manager;
    }

    /**
     * Sorts the provided array that contains current logged user's drafts.
     *
     * @param array $drafts
     *
     * @return array
     */
    private function sortDraftCollection(array $drafts)
    {
        $sortedDrafts = [];
        $filteredDrafts = [];

        foreach ($drafts as $draft) {
            if (null === $draft->getContent()) {
                continue;
            }

            $sortedDrafts[$draft->getContent()->getUid()] = [$draft->getContent()->getUid() => $draft];
        }

        foreach ($drafts as $draft) {
            foreach ($draft->getContent()->getData() as $key => $element) {
                if (
                    !is_int($key)
                    && $element instanceof AbstractClassContent
                    && in_array($element->getUid(), array_keys($sortedDrafts))
                ) {
                    $elementUid = $element->getUid();
                    $sortedDrafts[$draft->getContent()->getUid()][$key] = $sortedDrafts[$elementUid][$elementUid];
                }
            }
        }

        foreach ($sortedDrafts as $key => $data) {
            foreach ($data as $elementName => $draft) {
                if ($key === $elementName) {
                    continue;
                }

                if (isset($sortedDrafts[$draft->getContent()->getUid()])) {
                    $sortedDrafts[$key][$elementName] = &$sortedDrafts[$draft->getContent()->getUid()];
                    $filteredDrafts[$draft->getContent()->getUid()] = false;
                }
            }
        }

        return array_filter(array_merge($sortedDrafts, $filteredDrafts));
    }

    /**
     * Updates and returns content and its draft according to provided data.
     *
     * @param string $type
     * @param string $uid
     * @param array  $data
     *
     * @return AbstractClassContent
     */
    private function updateClassContent($type, $uid, $data)
    {
        $this->granted('EDIT', $content = $this->getClassContentByTypeAndUid($type, $uid, true, true));
        $this->getClassContentManager()->update($content, $data);

        return $content;
    }

    /**
     * Commits or reverts content's draft according to provided data.
     *
     * @param string $type
     * @param string $uid
     * @param array  $data
     *
     * @return AbstractClassContent
     */
    private function updateClassContentDraft($type, $uid, $data)
    {
        $this->granted('VIEW', $content = $this->getClassContentByTypeAndUid($type, $uid));
        $this->granted('EDIT', $content);

        $operation = $data['operation'];
        if (!in_array($operation, ['commit', 'revert'])) {
            throw new BadRequestHttpException(sprintf('%s is not a valid operation for update draft.', $operation));
        }

        $this->getClassContentManager()->$operation($content, $data);

        return $content;
    }

    /**
     * Returns classcontent datas if couple (type;uid) is valid.
     *
     * @param string $type
     * @param string $uid
     *
     * @return AbstractClassContent
     */
    private function getClassContentByTypeAndUid($type, $uid, $hydrateDraft = false, $checkoutOnMissing = false)
    {
        $content = null;

        try {
            $content = $this->getClassContentManager()->findOneByTypeAndUid(
                $type,
                $uid,
                $hydrateDraft,
                $checkoutOnMissing
            );
        } catch (InvalidContentTypeException $e) {
            throw new NotFoundHttpException(sprintf('Provided content type (:%s) is invalid.', $type));
        }

        if (null === $content) {
            throw new NotFoundHttpException(sprintf('Cannot find `%s` with uid `%s`.', $type, $uid));
        }

        return $content;
    }

    /**
     * Returns classcontent by category.
     *
     * @param string  $name category's name
     * @param integer $start
     * @param integer $count
     *
     * @return null|Paginator
     */
    private function getClassContentByCategory($name, $start, $count)
    {
        return $this->findContentsByCriteria($this->getClassContentClassnamesByCategory($name), $start, $count);
    }

    /**
     * Returns all classcontents classnames that belong to provided category.
     *
     * @param string $name The category name
     *
     * @return array
     */
    private function getClassContentClassnamesByCategory($name)
    {
        try {
            return $this->getCategoryManager()->getClassContentClassnamesByCategory($name);
        } catch (\InvalidArgumentException $e) {
            throw new NotFoundHttpException($e->getMessage());
        }
    }

    /**
     * Returns classcontent data with definition format (AbstractContent::JSON_DEFAULT_FORMAT). If category name
     * is provided it will returns every classcontent definition that belongs to this category, else it
     * will returns all classcontents definitions.
     *
     * @param string|null $name the category's name or null
     *
     * @return array
     */
    private function getClassContentDefinitionsByCategory($name = null)
    {
        $application = $this->getApplication();
        $cache = $application->getContainer()->get('cache.control');
        $cacheId = md5(
            'classcontent_definitions_' . $application->getContext() . '_' . $application->getEnvironment() . $name
        );

        if (!$application->isDebugMode() && false !== $value = $cache->load($cacheId)) {
            $definitions = json_decode($value, true);
        } else {
            $classnames = [];
            if (null === $name) {
                $classnames = $this->getClassContentManager()->getAllClassContentClassnames();
            } else {
                $classnames = $this->getClassContentClassnamesByCategory($name);
            }

            $definitions = [];
            foreach ($classnames as $classname) {
                $definitions[] = $this->getClassContentManager()->jsonEncode(
                    (new $classname()),
                    AbstractClassContent::JSON_DEFINITION_FORMAT
                );
            }

            $cache->save($cacheId, json_encode($definitions));
        }

        return $definitions;
    }

    /**
     * Find classcontents by provided classnames, criterias from request, provided start and count.
     *
     * @param array   $classnames
     * @param integer $start
     * @param integer $count
     *
     * @return null|Paginator
     */
    private function findContentsByCriteria(array $classnames, $start, $count)
    {
        $criterias = array_merge(
            [
                'only_online' => false,
                'site_uid' => $this->getApplication()->getSite()->getUid(),
            ],
            $this->getRequest()->query->all()
        );

        $criterias['only_online'] = (boolean)$criterias['only_online'];
        $preserveOrder = isset($criterias['preserve_order']) ? (boolean)$criterias['preserve_order'] : false;

        $order_infos = [
            'column' => isset($criterias['order_by']) ? $criterias['order_by'] : '_modified',
            'direction' => isset($criterias['order_direction']) ? $criterias['order_direction'] : 'desc',
        ];

        $pagination = ['start' => $start, 'limit' => $count];

        unset($criterias['order_by']);
        unset($criterias['order_direction']);

        $criterias['contentIds'] = array_filter(explode(',', $this->getRequest()->query->get('uids', '')));

        unset($criterias['uids']);
        unset($criterias['preserve_order']);

        $contents = $this->getEntityManager()
            ->getRepository('BackBee\ClassContent\AbstractClassContent')
            ->findContentsBySearch($classnames, $order_infos, $pagination, $criterias);

        if ($preserveOrder === true) {
            $contents = $this->sortByUids($criterias['contentIds'], $contents);
        }

        foreach ($contents as $content) {
            $content->setDraft($this->getClassContentManager()->getDraft($content));
        }

        return $contents;
    }

    /**
     * Returns AbstractContent valid json format by looking at request query parameter and if no format found,
     * it fallback to AbstractContent::JSON_DEFAULT_FORMAT.
     *
     * @return integer One of AbstractContent::$jsonFormats:
     *                 JSON_DEFAULT_FORMAT | JSON_DEFINITION_FORMAT | JSON_CONCISE_FORMAT | JSON_INFO_FORMAT
     */
    private function getFormatParam()
    {
        $validFormats = array_keys(AbstractClassContent::$jsonFormats);
        $queryParamsKey = array_keys($this->getRequest()->query->all());
        $format = ($collection = array_intersect($validFormats, $queryParamsKey))
            ? array_shift($collection)
            : $validFormats[AbstractClassContent::JSON_DEFAULT_FORMAT];

        return AbstractClassContent::$jsonFormats[$format];
    }

    /**
     * Add 'Content-Range' parameters to $response headers.
     *
     * @param Response $response   the response object
     * @param mixed    $collection collection from where we extract Content-Range data
     * @param integer  $start      the start value
     */
    private function addContentRangeHeadersToResponse(Response $response, $collection, $start, $usePagination = true)
    {
        $total = '*';
        if ($collection instanceof Paginator) {
            $resultCount = count($collection->getIterator());
        } else {
            $resultCount = count($collection);
        }

        if ($usePagination) {
            $total = count($collection);
        }

        $lastResult = $start + $resultCount - 1;
        $lastResult = $lastResult < 0 ? 0 : $lastResult;
        $response->headers->set('Content-Range', "$start-$lastResult/" . $total);

        return $response;
    }

    /**
     * @api               {get} /classcontent/:group/permissions Get permissions (ACL)
     * @apiName           getPermissionsAction
     * @apiGroup          ClassContent
     * @apiVersion        0.2.0
     *
     * @apiPermission     ROLE_API_USER
     *
     * @apiError          NoAccessRight Invalid authentication information.
     * @apiError          GroupNotFound No <strong>BackBee\\Security\\Group</strong> exists with uid <code>group</code>.
     *
     * @apiHeader {String} X-API-KEY User's public key.
     * @apiHeader {String} X-API-SIGNATURE Api signature generated for the request.
     *
     * @apiParam {Number} group Group id.
     *
     * @apiSuccess {String} id Id of category.
     * @apiSuccess {String} name  Name of category.
     * @apiSuccess {Array} contents Contains every blocks which has current category name.
     *
     * @apiSuccessExample Success-Response:
     * HTTP/1.1 200 OK
     * {
     *      "id": "article",
     *      "name": "Article",
     *      "contents": [
     *          {
     *              "visible": true,
     *              "label": "Article",
     *              "description": "An article contains a title, an author, an abstract, a primary image and a body",
     *              "type": "Article/Article",
     *              "thumbnail": "http://backbee-cms.local/resources/img/contents/Article/Article.png",
     *              "rights": {
     *                  "total": 0,
     *                  "view": 0,
     *                  "create": 0,
     *                  "edit": 0,
     *                  "delete": 0,
     *                  "commit": 0,
     *                  "publish": 0
     *              }
     *          }
     *      ]
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
     *
     * @return JsonResponse
     */
    public function getPermissionsAction(Request $request)
    {
        $group = $request->attributes->get('group');
        $aclManager = $this->getContainer()->get('security.acl_manager');
        $parentClass = 'BackBee\ClassContent\AbstractClassContent';

        $categories['parent'] = [
            'class' => $parentClass,
            'rights' => $aclManager->getPermissions($parentClass, $group),
        ];

        $allClassContentClassNames = $this->getClassContentManager()->getAllClassContentClassnames();

        foreach ($this->getCategoryManager()->getCategories() as $id => $category) {

            foreach ($category->getBlocks() as $key => $block) {

                $className = AbstractClassContent::getClassnameByContentType($block->type);
                $block->rights = $aclManager->getPermissions($className, $group);
                $block->type = $className;

                $allClassContentClassNames = array_diff($allClassContentClassNames, [$className]);
            }

            if (false === empty($category->getBlocks())) {

                $categories['objects'][] = array_merge(['id' => $id], $category->jsonSerialize());
            }
        }

        $otherCategory = new Category('Other');

        foreach ($allClassContentClassNames as $class) {

            $content = new $class();

            if (false === $content->isElementContent()) {

                if (null === $content->getProperty('name')) {

                    $content->setProperty('name', $content->getContentType());
                }

                $otherCategory->addBlock($content);
            }
        }

        $categories['objects'][] = array_merge(['id' => 'other'], $otherCategory->jsonSerialize());

        return $this->createJsonResponse($categories, 200);
    }
}
