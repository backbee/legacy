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

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Acl\Domain\ObjectIdentity;
use Symfony\Component\Security\Acl\Domain\UserSecurityIdentity;
use Symfony\Component\Security\Acl\Exception\Exception;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

use BackBee\Rest\Controller\Annotations as Rest;
use BackBee\Rest\Exception\ValidationException;

/**
 * User Controller.
 *
 * @category    BackBee
 *
 *
 * @author      k.golovin
 * @author      Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class AclController extends AbstractRestController
{
    /**
     * Get all records.
     *
     * @Rest\QueryParam(name = "group_id", description="Security Group ID", requirements = {
     *  @Assert\NotBlank(message="Group ID cannot be empty")
     * })
     * @Rest\QueryParam(name = "object_id", description="Object ID", requirements = {
     *  @Assert\NotBlank(message="Object ID cannot be empty")
     * })
     * @Rest\QueryParam(name = "object_class", description="Object Class name", requirements = {
     *  @Assert\NotBlank(message="Object Class cannot be empty")
     * })
     * @Rest\QueryParam(name = "mask", description="Permission Mask", requirements = {
     *  @Assert\NotBlank(message="Mask must be provided"),
     *  @Assert\Type(type="integer", message="Mask must be an integer"),
     * })
     *
     * @Rest\Security("is_fully_authenticated() & has_role('ROLE_API_USER')")
     */
    public function getEntryCollectionAction(Request $request)
    {
        $aclProvider = $this->getApplication()->getSecurityContext()->getACLProvider();

        /* @var $aclProvider \Symfony\Component\Security\Acl\Dbal\AclProvider */
        $aclProvider->findAcls();

        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('g')
            ->from('BackBee\Security\Group', 'g')
        ;

        if ($request->request->get('site_uid')) {
            $site = $this->getEntityManager()->getRepository('BackBee\Site\Site')
                ->find($request->request->get('site_uid'))
            ;

            if (!$site) {
                throw $this->createValidationException(
                    'site_uid',
                    $request->request->get('site_uid'),
                    'Site is not valid: '.$request->request->get('site_uid')
                );
            }

            $qb->leftJoin('g._site', 's')
                ->andWhere('s._uid = :site_uid')
                ->setParameter('site_uid', $site->getUid())
            ;
        }

        $groups = $qb->getQuery()->getResult();

        return new Response($this->formatCollection($groups));
    }

    /**
     * @Rest\Security("is_fully_authenticated() & has_role('ROLE_API_USER')")
     */
    public function getClassCollectionAction(Request $request)
    {
        $sql = 'SELECT * FROM acl_classes';

        $results = $this->getEntityManager()->getConnection()->fetchAll($sql);

        return new Response(json_encode($results));
    }

    /**
     * @Rest\RequestParam(name = "group_id", description="Security Group ID", requirements = {
     *  @Assert\NotBlank(message="Group ID cannot be empty")
     * })
     *
     * @Rest\RequestParam(name = "object_class", description="Object Class name", requirements = {
     *  @Assert\NotBlank(message="Object Class cannot be empty")
     * })
     *
     * @Rest\RequestParam(name = "mask", description="Permission Mask", requirements = {
     *  @Assert\NotBlank(message="Mask must be provided"),
     *  @Assert\Type(type="integer", message="Mask must be an integer"),
     * })
     *
     * @Rest\Security("is_fully_authenticated() & has_role('ROLE_API_USER')")
     */
    public function postClassAceAction(Request $request)
    {
        $objectIdentity = new ObjectIdentity('class', $request->request->get('object_class'));

        $aclProvider = $this->getApplication()->getSecurityContext()->getACLProvider();

        $aclManager = $this->getContainer()->get("security.acl_manager");
        $acl = $aclManager->getAcl($objectIdentity);

        $securityIdentity = new UserSecurityIdentity($request->request->get('group_id'), 'BackBee\Security\Group');

        // grant owner access
        $acl->insertClassAce($securityIdentity, $request->request->get('mask'));

        $aclProvider->updateAcl($acl);

        $aces = $acl->getClassAces();

        $ace = $aces[0];
        /* @var $ace \Symfony\Component\Security\Acl\Domain\Entry */

        $data = [
            'id' => $ace->getId(),
            'mask' => $ace->getMask(),
            'group_id' => $ace->getSecurityIdentity()->getUsername(),
            'object_class' => $ace->getAcl()->getObjectIdentity()->getType(),
        ];

        return new Response(json_encode($data), 201);
    }

    /**
     * @Rest\RequestParam(name = "group_id", description="Security Group ID", requirements = {
     *  @Assert\NotBlank(message="Group ID cannot be empty")
     * })
     *
     * @Rest\RequestParam(name = "object_class", description="Object Class name", requirements = {
     *  @Assert\NotBlank(message="Object Class cannot be empty")
     * })
     *
     * @Rest\RequestParam(name = "object_id", description="Object ID", requirements = {
     *  @Assert\NotBlank(message="Object ID cannot be empty")
     * })
     *
     * @Rest\RequestParam(name = "mask", description="Permission Mask", requirements = {
     *  @Assert\NotBlank(message="Mask must be provided")
     * })
     *
     * @Rest\Security("is_fully_authenticated() & has_role('ROLE_API_USER')")
     *
     * @param Request $request
     * @return Response
     */
    public function postObjectAceAction(Request $request)
    {
        $objectIdentity = new ObjectIdentity($request->request->get('object_id'), $request->request->get('object_class'));
        $aclProvider = $this->getApplication()->getSecurityContext()->getACLProvider();

        $aclManager = $this->getContainer()->get("security.acl_manager");

        $acl = $aclManager->getAcl($objectIdentity);

        $securityIdentity = new UserSecurityIdentity($request->request->get('group_id'), 'BackBee\Security\Group');

        // grant owner access
        $acl->insertObjectAce($securityIdentity, intval($request->request->get('mask')));

        $aclProvider->updateAcl($acl);

        $aces = $acl->getObjectAces();

        $ace = $aces[0];
        /* @var $ace \Symfony\Component\Security\Acl\Domain\Entry */

        $data = [
            'id' => $ace->getId(),
            'mask' => $ace->getMask(),
            'group_id' => $ace->getSecurityIdentity()->getUsername(),
            'object_class' => $ace->getAcl()->getObjectIdentity()->getType(),
            'object_id' => $ace->getAcl()->getObjectIdentity()->getIdentifier(),
        ];

        return new Response(json_encode($data), 201);
    }

    /**
     * Bulk permissions create/update.
     *
     * @Rest\Security("is_fully_authenticated() & has_role('ROLE_API_USER')")
     * @param Request $request
     * @return Response
     */
    public function postPermissionMapAction(Request $request)
    {
        $permissionMap = $request->request->all();
        $aclManager = $this->getContainer()->get("security.acl_manager");

        $violations = new ConstraintViolationList();

        // Bulk permissions
        foreach ($permissionMap as $i => $objectMap) {

            // Create object identity with id and class
            if (!isset($objectMap['object_class'])) {
                $violations->add(
                    new ConstraintViolation(
                        "Object class not supplied",
                        "Object class not supplied",
                        [],
                        sprintf('%s[object_class]', $i),
                        sprintf('%s[object_class]', $i),
                        null
                    )
                );
                continue;
            }

            $objectClass = $objectMap['object_class'];
            $objectId = null;

            if (!class_exists($objectClass)) {
                $violations->add(
                    new ConstraintViolation(
                        "Class $objectClass doesn't exist",
                        "Class $objectClass doesn't exist",
                        [],
                        sprintf('%s[object_class]', $i),
                        sprintf('%s[object_class]', $i),
                        $objectClass
                    )
                );
                continue;
            }

            $objectIdentity = null;

            if (isset($objectMap['object_id'])) {
                $objectId = $objectMap['object_id'];
                // object scope
                $objectIdentity = new ObjectIdentity($objectId, $objectClass);
            } else {
                // class scope
                $objectIdentity = new ObjectIdentity('all', $objectClass);
            }

            // Create user security identity
            if (!isset($objectMap['sid'])) {
                $violations->add(
                    new ConstraintViolation(
                        "Security ID not supplied",
                        "Security ID not supplied",
                        [],
                        sprintf('%s[sid]', $i),
                        sprintf('%s[sid]', $i),
                        null
                    )
                );
                continue;
            }

            $sid = $objectMap['sid'];
            $securityIdentity = new UserSecurityIdentity($sid, 'BackBee\Security\Group');

            // Clean aces.
            if ($objectId) {

                try{
                    $aclManager->deleteObjectAce($objectIdentity, $securityIdentity);
                }
                catch (\Exception $e) { /* Do nothing */ }

            } else {

                try{
                    $aclManager->deleteClassAce($objectIdentity, $securityIdentity);
                }
                catch (\Exception $e) { /* Do nothing */ }
            }

            // Mask
            if (!isset($objectMap['mask'])) continue;

            // Grant
            if ($objectId) {

                $aclManager->insertOrUpdateObjectAce($objectIdentity, $securityIdentity, intval($objectMap['mask']));

            } else {

                $aclManager->insertOrUpdateClassAce($objectIdentity, $securityIdentity, intval($objectMap['mask']));
            }
        }

        if (count($violations) > 0) {
            throw new ValidationException($violations);
        }

        return new Response('', 204);
    }

    /**
     * @Rest\RequestParam(name = "object_class", description="Object Class name", requirements = {
     *  @Assert\NotBlank(message="Object Class cannot be empty")
     * })
     *
     * @param string|int $sid
     *
     * @Rest\Security("is_fully_authenticated() & has_role('ROLE_API_USER')")
     * @return Response
     */
    public function deleteClassAceAction($sid, Request $request)
    {
        $aclManager = $this->getContainer()->get("security.acl_manager");
        $securityIdentity = new UserSecurityIdentity($sid, 'BackBee\Security\Group');
        $objectIdentity = new ObjectIdentity('class', $request->request->get('object_class'));

        try {
            $aclManager->deleteClassAce($objectIdentity, $securityIdentity);
        } catch (\InvalidArgumentException $ex) {
            throw $this->createValidationException(
                'object_class',
                $request->request->get('object_class'),
                sprintf("Class ace doesn't exist for class %s", $request->request->get('object_class'))
            );
        }

        return new Response('', 204);
    }

    /**
     * @Rest\RequestParam(name = "object_class", description="Object Class name", requirements = {
     *  @Assert\NotBlank(message="Object Class cannot be empty")
     * })
     *
     * @Rest\RequestParam(name = "object_id", description="Object Identifier", requirements = {
     *  @Assert\NotBlank(message="Object Identifier cannot be empty")
     * })
     *
     * @param string|int $sid
     *
     * @Rest\Security("is_fully_authenticated() & has_role('ROLE_API_USER')")
     * @return Response
     */
    public function deleteObjectAceAction($sid, Request $request)
    {
        $aclManager = $this->getContainer()->get("security.acl_manager");
        $securityIdentity = new UserSecurityIdentity($sid, 'BackBee\Security\Group');
        $objectClass = $request->request->get('object_class');

        $objectIdentity = new ObjectIdentity($request->request->get('object_id'), $objectClass);

        try {
            $aclManager->deleteClassAce($objectIdentity, $securityIdentity);
        } catch (\InvalidArgumentException $ex) {
            throw $this->createValidationException(
                'object',
                $request->request->get('object_class'),
                sprintf("Object ace doesn't exist for %s::%s", $objectClass, $request->request->get('object_id'))
            );
        }

        return new Response('', 204);
    }

    /**
     * @Rest\Security("is_fully_authenticated() & has_role('ROLE_API_USER')")
     */
    public function getMaskCollectionAction()
    {
        $aclManager = $this->getContainer()->get("security.acl_manager");

        $data = $aclManager->getPermissionCodes();

        return new Response(json_encode($data), 200);
    }

    /**
     * @api {post} /acl/:group/page/:uid Update permissions page
     * @apiName postPermissionsPageAction
     * @apiGroup Acl
     * @apiVersion 0.2.0
     *
     * @apiPermission ROLE_API_USER
     *
     * @apiError NoAccessRight Invalid authentication information.
     * @apiError GroupNotFound No <strong>BackBee\\Security\\Group</strong> exists with uid <code>group</code>.
     * @apiError PageNotFound No <strong>BackBee\\NestedNode\\Page</strong> exists with uid <code>uid</code>.
     *
     * @apiHeader {String} X-API-KEY User's public key.
     * @apiHeader {String} X-API-SIGNATURE Api signature generated for the request.
     *
     * @apiParam {Number} group Group id.
     * @apiParam {Number} uid Page uid.
     *
     * @apiSuccessExample Success-Response:
     * HTTP/1.1 200 OK
     */

    /**
     * Update permission
     *
     * @Rest\ParamConverter(name="group", id_name = "group", class="BackBee\Security\Group")
     * @Rest\ParamConverter(name="uid", class="BackBee\NestedNode\Page")
     *
     * @Rest\Security("is_fully_authenticated() & has_role('ROLE_API_USER')")
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function postPermissionsPageAction(Request $request)
    {
        $em = $this->getApplication()->getEntityManager();
        $page = $request->attributes->get('uid');
        $group = $request->attributes->get('group');
        $aclManager = $this->getContainer()->get('security.acl_manager');

        if(true === $page->isRoot()) {

            $objectIdentity = new ObjectIdentity('all', $page->getType());

            try{
                $aclManager->deleteClassAce($objectIdentity, $group);
            }
            catch(\Exception $e){ /* do nothing */ }

            if($request->request->has('mask')) {

                $aclManager->insertOrUpdateClassAce($objectIdentity, $group, $request->request->get('mask'));
            }

        } else {

            $pages = $em->getRepository('BackBee\NestedNode\Page')->findBy(array('_url' => $page->getUrl()));

            foreach ($pages as $page) {

                try{
                    $aclManager->deleteObjectAce($page, $group);
                }
                catch(\Exception $e){ /* do nothing */ }

                if($request->request->has('mask')) {

                    $aclManager->insertOrUpdateObjectAce($page, $group, $request->request->get('mask'));
                }
            }
        }

        return $this->createJsonResponse([], 200);
    }

    /**
     * @api {delete} /acl/:group/clear/:uid Clear permissions for an page
     * @apiName clearPermissionsPageAction
     * @apiGroup Acl
     * @apiVersion 0.2.0
     *
     * @apiPermission ROLE_API_USER
     *
     * @apiError NoAccessRight Invalid authentication information.
     * @apiError GroupNotFound No <strong>BackBee\\Security\\Group</strong> exists with uid <code>group</code>.
     * @apiError PageNotFound No <strong>BackBee\\NestedNode\\Page</strong> exists with uid <code>uid</code>.
     *
     * @apiHeader {String} X-API-KEY User's public key.
     * @apiHeader {String} X-API-SIGNATURE Api signature generated for the request.
     *
     * @apiParam {Number} group Group id.
     *
     * @apiSuccessExample Success-Response:
     * HTTP/1.1 200 OK
     */
    /**
     * Clear permissions for an page
     *
     * @Rest\ParamConverter(name="uid", class="BackBee\NestedNode\Page")
     *
     * @Rest\Security("is_fully_authenticated() & has_role('ROLE_API_USER')")
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function clearPermissionsPageAction(Request $request)
    {
        $page = $request->attributes->get('uid');
        $group = $request->attributes->get('group');

        $aclManager = $this->getContainer()->get('security.acl_manager');
        $securityIdentity = new UserSecurityIdentity($group, 'BackBee\Security\Group');

        try{

            if(true === $page->isRoot()){

                try{
                    $aclManager->deleteClassAce(new ObjectIdentity('all', $page->getType()), $securityIdentity);
                }
                catch(\Exception $e){
                    // do nothing
                }
            }
            else{
                $aclManager->deleteObjectAce($page, $securityIdentity);
            }
        }
        catch (\Exception $e){
            // do nothing
        }

        return $this->createJsonResponse([], 200);
    }
}
