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
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Acl\Domain\Entry;
use Symfony\Component\Security\Acl\Domain\UserSecurityIdentity;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\InsufficientAuthenticationException;
use Symfony\Component\Validator\Constraints as Assert;

use BackBee\AutoLoader\Exception\ClassNotFoundException;
use BackBee\ClassContent\AbstractClassContent;
use BackBee\Exception\InvalidArgumentException;
use BackBee\NestedNode\Page;
use BackBee\Rest\Controller\Annotations as Rest;
use BackBee\Rest\Exception\NotModifiedException;
use BackBee\Rest\Patcher\EntityPatcher;
use BackBee\Rest\Patcher\Exception\InvalidOperationSyntaxException;
use BackBee\Rest\Patcher\Exception\UnauthorizedPatchOperationException;
use BackBee\Rest\Patcher\OperationSyntaxValidator;
use BackBee\Rest\Patcher\RightManager;
use BackBee\Security\Acl\Permission\MaskBuilder;
use BackBee\Site\Layout;
use BackBee\Workflow\State;

/**
 * Page Controller.
 *
 * @category    BackBee
 *
 *
 * @author      e.chau <eric.chau@lp-digital.fr>
 * @author      Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class PageController extends AbstractRestController
{
    /**
     * Returns page entity available status.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getAvailableStatusAction()
    {
        return $this->createJsonResponse(Page::$STATES);
    }

    /**
     * Get page's metadatas.
     *
     * @param Page $page the page we want to get its metadatas
     *
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @Rest\ParamConverter(name="page", class="BackBee\NestedNode\Page")
     */
    public function getMetadataAction(Page $page)
    {
        $metadata = null !== $page->getMetaData() ? $page->getMetaData()->jsonSerialize() : array();
        if (empty($metadata)) {
            $metadata = $this->application->getContainer()->get('nestednode.metadata.resolver')->resolve($page);
        }

        return $this->createJsonResponse($metadata);
    }

    /**
     * Get page ancestors
     * @param Page $page the page we want to get its ancestors
     * @return \Symfony\Component\HttpFoundation\Response
     * @Rest\ParamConverter(name="page", class="BackBee\NestedNode\Page")
     */
    public function getAncestorsAction(Page $page)
    {
        $ancestors = $this->getPageRepository()->getAncestors($page);

        return $this->createResponse($this->formatCollection($ancestors));
    }

    /**
     * Update page's metadatas.
     *
     * @param Page    $page    the page we want to update its metadatas
     * @param Request $request
     *
     * @return Symfony\Component\HttpFoundation\Response
     *
     * @Rest\ParamConverter(name="page", class="BackBee\NestedNode\Page")
     */
    public function putMetadataAction(Page $page, Request $request)
    {
        $metadatas = $this->application
                ->getContainer()
                ->get('nestednode.metadata.resolver')
                ->resolve($page);

        foreach ($request->request->all() as $name => $attributes) {
            if ($metadatas->has($name)) {
                foreach ($attributes as $attr_name => $attr_value) {
                    if ($attr_value !== $metadatas->get($name)->getAttribute($attr_name)) {
                        $metadatas->get($name)->setAttribute($attr_name, $attr_value, null, false);
                    }
                }
            }
        }

        $page->setMetaData($metadatas);
        $this->getApplication()->getEntityManager()->flush();

        return $this->createJsonResponse(null, 204);
    }

    /**
     * Get collection of page entity.
     *
     * Version 1
     *  - without params return current root
     *  - parent_uid return first level before the parent page
     *
     * Version 2
     *  - without params return all pages
     *  - `parent_uid` return all pages available before the nested level
     *  - `root` return current root
     *  - `level_offset` permit to choose the depth ex: `parent_uid=oneuid&level_offset=1` equals version 1 parent_uid parameter
     *  - `has_children` return only pages they have children
     *  - new available filter params:
     *    - `title` (is a like method)
     *    - `layout_uid`
     *    - `site_uid`
     *    - `created_before`
     *    - `created_after`
     *    - `modified_before`
     *    - `modified_after`
     *
     * @return Symfony\Component\HttpFoundation\Response
     *
     * @Rest\Pagination(default_count=25, max_count=100)
     *
     * @Rest\QueryParam(name="parent_uid", description="Parent Page UID")
     *
     * @Rest\QueryParam(name="order_by", description="Page order by", requirements={
     *   @Assert\Type(type="array", message="An array containing at least 1 column name to order by must be provided"),
     *   @Assert\All({
     *     @Assert\Choice(choices = {"asc", "desc"}, message="order direction is not valid")
     *   })
     * })
     *
     * @Rest\QueryParam(name="state", description="Page State", requirements={
     *   @Assert\Type(type="array", message="An array containing at least 1 state must be provided"),
     *   @Assert\All({
     *     @Assert\Choice(choices = {"0", "1", "2", "3", "4"}, message="State is not valid")
     *   })
     * })
     *
     * @Rest\ParamConverter(
     *   name="parent", id_name="parent_uid", id_source="query", class="BackBee\NestedNode\Page", required=false
     * )
     */
    public function getCollectionAction(Request $request, $start, $count, Page $parent = null)
    {
        $response = null;
        $contentUid = $request->query->get('content_uid', null);
        $contentType = $request->query->get('content_type', null);

        if (null !== $contentUid && null !== $contentType) {
            $response = $this->doGetCollectionByContent($contentType, $contentUid);
        } elseif ((null === $contentUid && null !== $contentType) || (null !== $contentUid && null === $contentType)) {
            throw new BadRequestHttpException(
                'To get page collection by content, you must provide `content_uid` and `content_type` as query parameters.'
            );
        } elseif ($request->attributes->get('version') == 1) {
            $response = $this->doClassicGetCollectionVersion1($request, $start, $count, $parent);
        } else {
            $response = $this->doClassicGetCollection($request, $start, $count, $parent);
        }

        return $response;
    }

    /**
     * Get page by uid.
     *
     * @param string $uid the unique identifier of the page we want to retrieve
     *
     * @return Symfony\Component\HttpFoundation\Response
     *
     * @Rest\ParamConverter(name="page", class="BackBee\NestedNode\Page")
     * @Rest\Security(expression="is_granted('VIEW', page)")
     */
    public function getAction(Page $page)
    {
        return $this->createResponse($this->formatItem($page));
    }

    /**
     * Create a page.
     *
     * @Rest\RequestParam(name="title", description="Page title", requirements={
     *   @Assert\Length(min=3, minMessage="Title must contain at least 3 characters"),
     *   @Assert\NotBlank()
     * })
     *
     * @Rest\ParamConverter(
     *   name="layout", id_name="layout_uid", id_source="request", class="BackBee\Site\Layout", required=true
     * )
     * @Rest\ParamConverter(
     *   name="parent", id_name="parent_uid", id_source="request", class="BackBee\NestedNode\Page", required=false
     * )
     * @Rest\ParamConverter(
     *   name="source", id_name="source_uid", id_source="query", class="BackBee\NestedNode\Page", required=false
     * )
     * @Rest\ParamConverter(
     *   name="workflow", id_name="workflow_uid", id_source="request", class="BackBee\Workflow\State", required=false
     * )
     *
     * @Rest\Security(expression="is_granted('VIEW', layout)")
     */
    public function postAction(Layout $layout, Request $request, Page $parent = null)
    {
        if (null !== $parent) {
            $this->granted('EDIT', $parent);
        }

        $builder = $this->getApplication()->getContainer()->get('pagebuilder');
        $builder->setLayout($layout);

        if (null !== $parent) {
            $builder->setParent($parent);
            $builder->setRoot($parent->getRoot());
            $builder->setSite($parent->getSite());

            if ($this->isFinal($parent)) {
                return $this->createFinalResponse($parent->getLayout());
            }
        } else {
            $builder->setSite($this->getApplication()->getSite());
        }

        $requestRedirect = $request->request->get('redirect');
        $redirect = ($requestRedirect === '' || $requestRedirect === null) ? null : $requestRedirect;

        $builder->setTitle($request->request->get('title'));
        $builder->setUrl($request->request->get('url', null));
        $builder->setState($request->request->get('state'));
        $builder->setTarget($request->request->get('target'));
        $builder->setRedirect($redirect);
        $builder->setAltTitle($request->request->get('alttitle'));
        $builder->setPublishing(
            null !== $request->request->get('publishing')
                ? new \DateTime(date('c', $request->request->get('publishing')))
                : null
        );

        $builder->setArchiving(
            null !== $request->request->get('archiving')
                ? new \DateTime(date('c', $request->request->get('archiving')))
                : null
        );

        try {
            $page = $builder->getPage();

            $this->trySetPageWorkflowState($page, $this->getEntityFromAttributes('workflow'));
            $this->granted('CREATE', $page);

            if (null !== $page->getParent()) {
                $this->getEntityManager()
                        ->getRepository('BackBee\NestedNode\Page')
                        ->insertNodeAsFirstChildOf($page, $page->getParent());
            }

            $this->getEntityManager()->persist($page);
            $this->getEntityManager()->flush($page);
        } catch (\Exception $e) {
            return $this->createResponse('Internal server error: '.$e->getMessage(), 500);
        }

        return $this->createJsonResponse('', 201, array(
            'Location' => $this->getApplication()->getRouting()->getUrlByRouteName(
                'bb.rest.page.get',
                array(
                    'version' => $request->attributes->get('version'),
                    'uid'     => $page->getUid(),
                ),
                '',
                false
            ),
        ));
    }

    private function createFinalResponse(Layout $layout)
    {
        return $this->createResponse('Can\'t create children of ' . $layout->getLabel() . ' layout', 403);
    }

    /**
     * Check if the page is final
     *
     * @param  Page|null $page [description]
     * @return boolean         [description]
     */
    private function isFinal(Page $page = null)
    {
        $result = false;
        if (null !== $page) {
            $layout = $page->getLayout();
            if (null !== $layout && $layout->isFinal()) {
                $result = true;
            }
        }

        return $result;
    }

    /**
     * Update page.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @Rest\RequestParam(name="title", description="Page title", requirements={
     *   @Assert\NotBlank(message="title is required")
     * })
     * @Rest\RequestParam(name="url", description="page url", requirements={
     *   @Assert\NotBlank(message="url is required")
     * })
     * @Rest\RequestParam(name="target", description="page target", requirements={
     *   @Assert\NotBlank(message="target is required")
     * })
     * @Rest\RequestParam(name="state", description="page state", requirements={
     *   @Assert\NotBlank(message="state is required")
     * })
     * @Rest\RequestParam(name="publishing", description="Publishing flag", requirements={
     *   @Assert\Type(type="digit", message="The value should be a positive number")
     * })
     * @Rest\RequestParam(name="archiving", description="Archiving flag", requirements={
     *   @Assert\Type(type="digit", message="The value should be a positive number")
     * })
     *
     * @Rest\ParamConverter(name="page", class="BackBee\NestedNode\Page")
     * @Rest\ParamConverter(name="layout", id_name="layout_uid", class="BackBee\Site\Layout", id_source="request")
     * @Rest\ParamConverter(
     *   name="parent", id_name="parent_uid", class="BackBee\NestedNode\Page", id_source="request", required=false
     * )
     * @Rest\ParamConverter(
     *   name="workflow", id_name="workflow_uid", id_source="request", class="BackBee\Workflow\State", required=false
     * )
     * @Rest\Security(expression="is_granted('EDIT', page)")
     * @Rest\Security(expression="is_granted('VIEW', layout)")
     */
    public function putAction(Page $page, Layout $layout, Request $request, Page $parent = null)
    {

        $page->setLayout($layout);
        $this->trySetPageWorkflowState($page, $this->getEntityFromAttributes('workflow'));

        $requestRedirect = $request->request->get('redirect');
        $redirect = ($requestRedirect === '' || $requestRedirect === null) ? null : $requestRedirect;

        $page->setTitle($request->request->get('title'))
            ->setUrl($request->request->get('url'))
            ->setTarget($request->request->get('target'))
            ->setState($request->request->get('state'))
            ->setRedirect($redirect)
            ->setAltTitle($request->request->get('alttitle', null))
        ;

        if ($parent !== null) {

            $page->setParent($parent);
            if ($this->isFinal($parent)) {
                return $this->createFinalResponse($parent->getLayout());
            }
        }

        if ($request->request->has('publishing')) {
            $publishing = $request->request->get('publishing');
            $page->setPublishing(null !== $publishing ? new \DateTime(date('c', $publishing)) : null);
        }

        if ($request->request->has('archiving')) {
            $archiving = $request->request->get('archiving');
            $page->setArchiving(null !== $archiving ? new \DateTime(date('c', $archiving)) : null);
        }

        if (true === $page->isOnline(true)) {
            $this->granted('PUBLISH', $page);
        }

        $this->getEntityManager()->flush($page);

        return $this->createJsonResponse(null, 204);
    }

    /**
     * Update page collecton.
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     *
     * @Rest\Security("is_fully_authenticated() & has_role('ROLE_API_USER')")
     */
    public function putCollectionAction(Request $request)
    {
        $result = [];
        foreach ($request->request->all() as $data) {
            if (!isset($data['uid'])) {
                throw new BadRequestHttpException('uid is missing.');
            }

            try {
                $page = $this->getEntityManager()->getRepository('BackBee\NestedNode\Page')->find($data['uid']);

                $this->granted('EDIT', $page);
                if (isset($data['state'])) {
                    $this->granted('PUBLISH', $page);
                }
                $this->updatePage($page, $data);

                $result[] = [
                    'uid'        => $page->getUid(),
                    'statusCode' => 200,
                    'message'    => 'OK',
                ];
            } catch (NotModifiedException $e) {
                $result[] = [
                    'uid'        => $data['uid'],
                    'statusCode' => 304,
                    'message'    => $e->getMessage(),
                ];
            } catch (AccessDeniedException $e) {
                $result[] = [
                    'uid'        => $data['uid'],
                    'statusCode' => 401,
                    'message'    => $e->getMessage(),
                ];
            } catch (\Exception $e) {
                if ($e instanceof BadRequestHttpException || $e instanceof InsufficientAuthenticationException) {
                    $result[] = [
                        'uid'        => $data['uid'],
                        'statusCode' => 403,
                        'message'    => $e->getMessage(),
                    ];
                } else {
                    $result[] = [
                        'uid'        => $data['uid'],
                        'statusCode' => 500,
                        'message'    => $e->getMessage(),
                    ];
                }
            }
        }

        $this->getEntityManager()->flush();

        return $this->createJsonResponse($result);
    }

    private function updatePage(Page $page, $data)
    {
        if (isset($data['state'])) {
            $this->updatePageState($page, $data['state']);
        }
        if (isset($data['parent_uid'])) {
            $repo = $this->getEntityManager()->getRepository('BackBee\NestedNode\Page');
            $parent = $repo->find($data['parent_uid']);

            if (null !== $parent) {
                $layout = $parent->getLayout();
                if ($layout !== null && $layout->isFinal()) {
                    throw new BadRequestHttpException('Can\'t create children of ' . $layout->getLabel() . ' layout');
                }
            }

            $this->moveAsFirstChildOf($page, $parent);
        }
    }

    private function updatePageState(Page $page, $state)
    {
        if ($state === 'online') {
            if (!$page->isOnline(true)) {
                $page->setState($page->getState() + 1);
            } else {
                throw new NotModifiedException();
            }
        } elseif ($state === 'offline') {
            if ($page->isOnline(true)) {
                $page->setState($page->getState() - 1);
            } else {
                throw new NotModifiedException();
            }
        } elseif ($state === 'restore') {
            if ($page->isDeleted()) {
                $page->setState(0);
            } else {
                throw new NotModifiedException();
            }
        } elseif ($state === 'delete') {
            if ($page->getState() >= 4) {
                $this->hardDelete($page);
            } else {
                $this->granted('DELETE', $page);
                $page->setState(4);
            }
        }
    }

    /**
     * Moves the pages in trash.
     *
     * @param  Page $page The page to be moved in trash.
     *
     * @throws BadRequestHttpException Occures if $page is a root.
     */
    private function softDelete(Page $page)
    {
        if ($page->isRoot()) {
            throw new BadRequestHttpException('Cannot remove root page of a site.');
        }

        $this->granted('DELETE', $page);
        $this->granted('EDIT', $page->getParent()); // user must have edit permission on parent

        if ($page->isOnline(true)) {
            $this->granted('PUBLISH', $page); // user must have publish permission on the page
        }

        $this->getPageRepository()->toTrash($page);
    }

    /**
     * Remove page from the database.
     *
     * @param  Page $page The page to be removeed.
     *
     * @throws BadRequestHttpException Occures if $page is not in trash or is a root.
     */
    private function hardDelete(Page $page)
    {
        if (!$page->isDeleted()) {
            throw new BadRequestHttpException('Page is not in trash, cannot remove it.');
        }

        if (true === $page->isRoot()) {
            throw new BadRequestHttpException('Cannot remove root page of a site.');
        }

        $this->granted('DELETE', $page);

        $this->getPageRepository()->deletePage($page);
    }

    /**
     * Patch page.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @Rest\RequestParam(name="0", description="Patch operations", requirements={
     *   @Assert\NotBlank(message="Request must contain at least one operation")
     * })
     *
     * @Rest\ParamConverter(name="page", class="BackBee\NestedNode\Page")
     * @Rest\Security(expression="is_granted('EDIT', page)")
     */
    public function patchAction(Page $page, Request $request)
    {
        $operations = $request->request->all();

        try {
            (new OperationSyntaxValidator())->validate($operations);
        } catch (InvalidOperationSyntaxException $e) {
            throw new BadRequestHttpException('operation invalid syntax: '.$e->getMessage());
        }

        $entity_patcher = new EntityPatcher(new RightManager($this->getSerializer()->getMetadataFactory()));
        $entity_patcher->getRightManager()->addAuthorizationMapping($page, array(
            'publishing' => array('replace'),
            'archiving' => array('replace')
        ));

        $this->patchStateOperation($page, $operations);
        $this->patchSiblingAndParentOperation($page, $operations);

        try {
            $entity_patcher->patch($page, $operations);
        } catch (UnauthorizedPatchOperationException $e) {
            throw new BadRequestHttpException('Invalid patch operation: '.$e->getMessage());
        }

        if (true === $page->isOnline(true)) {
            $this->granted('PUBLISH', $page);
        }

        $this->getEntityManager()->flush();

        return $this->createJsonResponse(null, 204);
    }

    /**
     * Delete page.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @Rest\ParamConverter(name="page", class="BackBee\NestedNode\Page")
     */
    public function deleteAction(Page $page)
    {
        if ($page->isDeleted()) {
            $this->hardDelete($page);
        } else {
            $this->softDelete($page);
        }

        $this->getEntityManager()->flush();

        return $this->createJsonResponse(null, 204);
    }

    /**
     * Delete page collecton.
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     *
     * @Rest\Security("is_fully_authenticated() & has_role('ROLE_API_USER')")
     */
    public function deleteCollectionAction(Request $request)
    {
        if (null === $uids = $request->get('uids', null)) {
            throw new BadRequestHttpException('uid is missing.');
        }

        $result = [];
        $statusCode = 204;
        $pages = $this->getPageRepository()->findBy(['_uid' => $uids]);
        foreach ($pages as $page) {
            try {
                if ($page->isDeleted()) {
                    $this->hardDelete($page);
                } else {
                    $this->softDelete($page);
                }

                $result[] = [
                    'uid'        => $page->getUid(),
                    'statusCode' => 204,
                    'message'    => 'OK',
                ];
            } catch (AccessDeniedException $e) {
                $result[] = [
                    'uid'        => $page->getUid(),
                    'statusCode' => 401,
                    'message'    => $e->getMessage(),
                ];
                $statusCode = ($statusCode < 401) ? 401 : $statusCode;
            } catch (\Exception $e) {
                if ($e instanceof BadRequestHttpException || $e instanceof InsufficientAuthenticationException) {
                    $result[] = [
                        'uid'        => $page->getUid(),
                        'statusCode' => 403,
                        'message'    => $e->getMessage(),
                    ];
                    $statusCode = ($statusCode < 403) ? 403 : $statusCode;
                } else {
                    $result[] = [
                        'uid'        => $page->getUid(),
                        'statusCode' => 500,
                        'message'    => $e->getMessage(),
                    ];
                    $statusCode = ($statusCode < 500) ? 500 : $statusCode;
                }
            }
        }

        $this->getEntityManager()->flush();

        return $this->createJsonResponse($result, $statusCode);
    }

    /**
     * Clone a page.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @Rest\RequestParam(name="title", description="Cloning page new title", requirements={
     *   @Assert\Length(min=3, minMessage="Title must contains atleast 3 characters"),
     *   @Assert\NotBlank
     * })
     *
     * @Rest\ParamConverter(name="source", class="BackBee\NestedNode\Page")
     * @Rest\ParamConverter(
     *   name="parent", id_name="parent_uid", id_source="request", class="BackBee\NestedNode\Page", required=false
     * )
     * @Rest\ParamConverter(
     *   name="sibling", id_name="sibling_uid", id_source="request", class="BackBee\NestedNode\Page", required=false
     * )
     *
     * @Rest\Security(expression="is_granted('CREATE', source)")
     */
    public function cloneAction(Page $source, Page $parent = null, $sibling = null, Request $request)
    {
        // user must have view permission on chosen layout
        $this->granted('VIEW', $source->getLayout());

        if (null !== $sibling) {
            $parent = $sibling->getParent();
        } elseif (null === $parent) {
            $parent = $source->getParent();
        }

        if (null !== $parent) {
            $this->granted('EDIT', $parent);
        } else {
            $this->granted('EDIT', $this->getApplication()->getSite());
        }

        $page = $this->getPageRepository()->duplicate(
            $source,
            $request->request->get('title'),
            $parent,
            true,
            $this->getApplication()->getBBUserToken()
        );

        $this->getApplication()->getEntityManager()->persist($page);
        $this->getApplication()->getEntityManager()->flush();

        if (null !== $sibling) {
            $this->getPageRepository()->moveAsPrevSiblingOf($page, $sibling);
        }

        return $this->createJsonResponse(null, 201, [
            'Location' => $this->getApplication()->getRouting()->getUrlByRouteName(
                'bb.rest.page.get',
                [
                    'version' => $request->attributes->get('version'),
                    'uid'     => $page->getUid(),
                ],
                '',
                false
            ),
            'BB-PAGE-URL' => $page->getUrl()
        ]);
    }

    /**
     * Getter for page entity repository.
     *
     * @return \BackBee\NestedNode\Repository\PageRepository
     */
    private function getPageRepository()
    {
        return $this->getEntityManager()->getRepository('BackBee\NestedNode\Page');
    }

    /**
     * Returns every pages that contains provided classcontent.
     *
     * @param string $contentType
     * @param string $contentUid
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    private function doGetCollectionByContent($contentType, $contentUid)
    {
        $content = null;
        $classname = AbstractClassContent::getClassnameByContentType($contentType);
        $em = $this->getApplication()->getEntityManager();

        try {
            $content = $em->find($classname, $contentUid);
        } catch (ClassNotFoundException $e) {
            throw new NotFoundHttpException("No classcontent found with provided type (:$contentType)");
        }

        if (null === $content) {
            throw new NotFoundHttpException("No `$classname` exists with uid `$contentUid`");
        }

        $pages = $em->getRepository("BackBee\ClassContent\AbstractClassContent")->findPagesByContent($content);

        $response = $this->createResponse($this->formatCollection($pages));
        if (0 < count($pages)) {
            $response->headers->set('Content-Range', '0-'.(count($pages) - 1).'/'.count($pages));
        }

        return $response;
    }

    /**
     * Returns pages collection by doing classic selection and by applying filters provided in request
     * query parameters.
     *
     * @param Request   $request
     * @param integer   $start
     * @param integer   $count
     * @param Page|null $parent
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    private function doClassicGetCollectionVersion1(Request $request, $start, $count, Page $parent = null)
    {
        $qb = $this->getPageRepository()
                    ->createQueryBuilder('p');
        $orderBy = [
            '_position' => 'ASC',
            '_leftnode' => 'ASC',
        ];
        if (null !== $request->query->get('order_by', null)) {
            foreach ($request->query->get('order_by') as $key => $value) {
                if ('_' !== $key[0]) {
                    $key = '_' . $key;
                }
                $orderBy[$key] = $value;
            }
        }
        if (null === $parent) {
            $qb->andSiteIs($this->getApplication()->getSite())
                    ->andParentIs(null);
        } else {
            $this->granted('VIEW', $parent);
            $qb->andIsDescendantOf($parent, true, 1, $orderBy, $count, $start);
        }
        if (null !== $state = $request->query->get('state', null)) {
            $qb->andStateIsIn((array) $state);
        }

        return $this->paginateClassicCollectionAction($qb, $start, $count);
    }

    /**
     * Returns pages collection by doing classic selection and by applying filters provided in request
     * query parameters.
     *
     * @param Request   $request
     * @param integer   $start
     * @param integer   $count
     * @param Page|null $parent
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    private function doClassicGetCollection(Request $request, $start, $count, Page $parent = null)
    {
        $qb = $this->getPageRepository()
                    ->createQueryBuilder('p');

        if (null !== $parent) {
            $this->granted('VIEW', $parent);
            if ($request->query->get('search_action') == 1) {
                $qb->andIsDescendantOf($parent, true, null, $this->getOrderCriteria($request->query->get('order_by', null)), $count, $start);
            } else {
                // Ordering is disabled for descendants, BackBee takes care of that
                $qb->andIsDescendantOf($parent, true, $request->query->get('level_offset', 1), $this->getOrderCriteria(null, $qb), $count, $start);
            }
        } else {
            if ($request->query->has('site_uid')) {
                $site = $this->getSiteRepository()->find($request->query->get('site_uid'));
                if (!$site) {
                    throw new BadRequestHttpException(sprintf("There is no site with uid: %s", $request->query->get('site_uid')));
                }
                $qb->andSiteIs($site);
            } else {
                $qb->andSiteIs($this->getApplication()->getSite());
            }

            if ($request->query->has('root')) {
                $qb->andParentIs(null);
            }

            $qb->addMultipleOrderBy($this->getOrderCriteria($request->query->get('order_by', null)));
        }

        if ($request->query->has('has_children')) {
            $qb->andIsSection();
            $qb->andWhere($qb->getSectionAlias().'._has_children = 1');
        }

        if (null !== $state = $request->query->get('state', null)) {
            $qb->andStateIsIn((array) $state);
        }

        if (null !== $title = $request->query->get('title', null)) {
            $qb->andWhere($qb->expr()->like($qb->getAlias().'._title', $qb->expr()->literal('%'.$title.'%')));
        }

        if (null !== $layout = $request->query->get('layout_uid', null)) {
            $qb->andWhere($qb->getAlias().'._layout = :layout')->setParameter('layout', $layout);
        }

        if (null !== $createdBefore = $request->query->get('created_before', null)) {
            $createdBeforeParam = new \DateTime('@' . $createdBefore);
            $qb->andWhere($qb->getAlias().'._created < :created_before')->setParameter('created_before', $createdBeforeParam);
        }

        if (null !== $createdAfter = $request->query->get('created_after', null)) {
            $createdAfterParam = new \DateTime('@' . $createdAfter);
            $qb->andWhere($qb->getAlias().'._created > :created_after')->setParameter('created_after', $createdAfterParam);
        }

        if (null !== $modifiedBefore = $request->query->get('modified_before', null)) {
            $modifiedBeforeParam = new \DateTime('@' . $modifiedBefore);
            $qb->andWhere($qb->getAlias().'._modified < :modified_before')->setParameter('modified_before', $modifiedBeforeParam);
        }

        if (null !== $modifiedAfter = $request->query->get('modified_after', null)) {
            $modifiedAfterParam = new \DateTime('@' . $modifiedAfter);
            $qb->andWhere($qb->getAlias().'._modified > :modified_after')->setParameter('modified_after', $modifiedAfterParam);
        }

        return $this->paginateClassicCollectionAction($qb, $start, $count);
    }

    /**
     * Getter for page entity repository.
     *
     * @return \BackBee\NestedNode\Site\Site
    */
    private function getSiteRepository()
    {
        return $this->getEntityManager()->getRepository('BackBee\Site\Site');
    }

    /**
     * Computes order criteria for collection.
     *
     * @param  array|null $requestedOrder
     *
     * @return array
     */
    private function getOrderCriteria(array $requestedOrder = null, $qb = null)
    {
        if (!empty($requestedOrder)) {
            $orderBy = [];
            foreach ($requestedOrder as $key => $value) {
                if ('_' !== $key[0]) {
                    $key = '_' . $key;
                }

                $orderBy[$key] = $value;
            }
        } else {
            $orderBy['_position'] = 'ASC';
            if ($qb) {
                $orderBy[ $qb->getSectionAlias().'._has_children'] = 'DESC';
            }
            $orderBy['_leftnode'] = 'ASC';
        }

        return $orderBy;
    }

    private function paginateClassicCollectionAction($qb, $start, $count)
    {
        $results = new Paginator($qb->setFirstResult($start)->setMaxResults($count));
        $count = 0;
        foreach ($results as $row) {
            $count++;
        }

        $result_count = $start + $count - 1; // minus 1 because $start starts at 0 and not at 1
        $response = $this->createResponse($this->formatCollection($results));
        if (0 < $count) {
            $response->headers->set('Content-Range', "$start-$result_count/".count($results));
        }

        return $response;
    }

    /**
     * Page workflow state setter.
     *
     * @param Page  $page
     * @param State $workflow
     */
    private function trySetPageWorkflowState(Page $page, State $workflow = null)
    {
        $page->setWorkflowState(null);
        if (null !== $workflow) {
            if (null === $workflow->getLayout() || $workflow->getLayout()->getUid() === $page->getLayout()->getUid()) {
                $page->setWorkflowState($workflow);
            }
        }
    }

    /**
     * Custom patch process for Page's state property.
     *
     * @param Page  $page
     * @param array $operations passed by reference
     */
    private function patchStateOperation(Page $page, array &$operations)
    {
        $stateOp = null;
        $isHiddenOp = null;
        foreach ($operations as $key => $operation) {
            $op = [
                'key' => $key,
                'op' => $operation
            ];
            if ('/state' === $operation['path']) {
                $stateOp = $op;
            } elseif ('/is_hidden' === $operation['path']) {
                $isHiddenOp = $op;
            }
        }

        if ($page->isOnline(true)) {
            $this->granted('PUBLISH', $page);
        }

        if (null !== $stateOp) {
            unset($operations[$stateOp['key']]);
            $states = explode('_', $stateOp['op']['value']);
            if (in_array($state = (int) array_shift($states), Page::$STATES)) {
                $page->setState($state | ($page->getState() & Page::STATE_HIDDEN ? Page::STATE_HIDDEN : 0));
            }

            if ($code = (int) array_shift($states)) {
                $workflowState = $this->getApplication()->getEntityManager()
                    ->getRepository('BackBee\Workflow\State')
                    ->findOneBy([
                        '_code'   => $code,
                        '_layout' => $page->getLayout(),
                    ])
                ;

                if (null !== $workflowState) {
                    $page->setWorkflowState($workflowState);
                }
            }
        }

        if (null !== $isHiddenOp) {
            unset($operations[$isHiddenOp['key']]);

            $isHidden = (boolean) $isHiddenOp['op']['value'];
            if ($isHidden && !($page->getState() & Page::STATE_HIDDEN)) {
                $page->setState($page->getState() | Page::STATE_HIDDEN);
            } elseif (!$isHidden && ($page->getState() & Page::STATE_HIDDEN)) {
                $page->setState($page->getState() ^ Page::STATE_HIDDEN);
            }
        }
    }

    /**
     * Custom patch process for Page's sibling or parent node.
     *
     * @param Page  $page
     * @param array $operations passed by reference
     */
    private function patchSiblingAndParentOperation(Page $page, array &$operations)
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
            if ($page->isRoot()) {
                throw new BadRequestHttpException('Cannot move root node of a site.');
            }

            if ($page->isOnline(true)) {
                $this->granted('PUBLISH', $page); // user must have publish permission on the page
            }
        }

        try {
            if (null !== $sibling_operation) {
                unset($operations[$sibling_operation['key']]);

                $sibling = $this->getPageByUid($sibling_operation['op']['value']);
                $this->granted('EDIT', $sibling->getParent());

                $this->getPageRepository()->moveAsPrevSiblingOf($page, $sibling);
            } elseif (null !== $parent_operation) {
                unset($operations[$parent_operation['key']]);

                $parent = $this->getPageByUid($parent_operation['op']['value']);
                if ($this->isFinal($parent)) {
                    throw new BadRequestHttpException('Can\'t create children of ' . $parent->getLayout()->getLabel() . ' layout');
                }

                $this->moveAsFirstChildOf($page, $parent);
            }
        } catch (InvalidArgumentException $e) {
            throw new BadRequestHttpException('Invalid node move action: '.$e->getMessage());
        }
    }

    /**
     * Moves $page as first child of $parent
     *
     * @param Page      $page
     * @param Page|null $parent
     *
     * @throws BadRequestHttpException Raises if $parent is null
     */
    private function moveAsFirstChildOf(Page $page, Page $parent = null)
    {
        if (null === $parent) {
            throw new BadRequestHttpException('Parent uid doesn\'t exists');
        }

        $this->granted('EDIT', $parent);

        if (!$parent->hasMainSection()) {
            $this->getPageRepository()->saveWithSection($parent);
        }

        $this->getPageRepository()->moveAsFirstChildOf($page, $parent);
    }

    /**
     * Retrieves page entity with provided uid.
     *
     * @param string $uid
     *
     * @return Page
     *
     * @throws NotFoundHttpException raised if page not found with provided uid
     */
    private function getPageByUid($uid)
    {
        if (null === $page = $this->getApplication()->getEntityManager()->find('BackBee\NestedNode\Page', $uid)) {
            throw new NotFoundHttpException("Unable to find page with uid `$uid`");
        }

        return $page;
    }

    /**
     * @api {get} /page/:group/permissions/:uid Get permissions (ACL)
     * @apiName getPermissionsAction
     * @apiGroup Page
     * @apiVersion 0.2.0
     *
     * @apiPermission ROLE_API_USER
     *
     * @apiError NoAccessRight Invalid authentication information.
     * @apiError GroupNotFound No <strong>BackBee\\Security\\Group</strong> exists with uid <code>group</code>
     * @apiError PageNotFound No <strong>BackBee\\NestedNode\\Page</strong> exists with uid <code>uid</code>.
     *
     * @apiHeader {String} X-API-KEY User's public key.
     * @apiHeader {String} X-API-SIGNATURE Api signature generated for the request.
     *
     * @apiParam {Number} group Group id.
     * @apiParam {Number} uid Page uid.
     *
     * @apiSuccess {String} uid Id of page.
     * @apiSuccess {Array} rights Contains rights for the current group.
     *
     * @apiSuccessExample Success-Response:
     * HTTP/1.1 200 OK
     * {
     *      "uid": "e2c62ba6eddbb9ce14b589c0b46fd43d",
     *      "rights": {
     *          "total": 15,
     *          "view": 1,
     *          "create": 1,
     *          "edit": 1,
     *          "delete": 1,
     *          "commit": 0,
     *          "publish": 0
     *      }
     * }
     */

    /**
     * Get permissions (ACL)
     *
     * @Rest\ParamConverter(name="uid", class="BackBee\NestedNode\Page")
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
        $page = $request->attributes->get('uid');

        $aclManager = $this->getContainer()->get('security.acl_manager');
        $rights = $aclManager->getPermissionsByPage($page, $group);

        $data = [
            'uid' => $page->getUid(),
            'url' => $page->getUrl(),
            'class' => $page->getType(),
            'rights' => $rights,
        ];

        if(false === $page->isRoot()){
            $rightsRoot = $aclManager->getPermissionsByPage($page->getRoot(), $group);
            $data['isEditable'] = (empty($rightsRoot)) ? 0 : 1;
        }

        return $this->createJsonResponse($data, 200);
    }
}
