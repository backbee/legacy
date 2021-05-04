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

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;

use BackBee\Rest\Controller\Annotations as Rest;

/**
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class WorkflowController extends AbstractRestController
{
    /**
     * @api {get} /layout/:group/permissions Get permissions (ACL)
     * @apiName getPermissionsAction
     * @apiGroup Layout
     * @apiVersion 0.2.0
     *
     * @apiPermission ROLE_API_USER
     *
     * @apiError NoAccessRight Invalid authentication information.
     * @apiError SiteNotFound No <strong>BackBee\\Site\\Site</strong> exists with uid <code>site_uid</code>.
     * @apiError GroupNotFound No <strong>BackBee\\Security\\Group</strong> exists with uid <code>group</code>
     *
     * @apiHeader {String} X-API-KEY User's public key.
     * @apiHeader {String} X-API-SIGNATURE Api signature generated for the request.
     *
     * @apiParam {Number} group Group id.
     * @apiParam {Number} site_uid Site uid.
     *
     * @apiSuccess {String} uid Id of layout.
     * @apiSuccess {String} label Label of layout.
     * @apiSuccess {String} class Classname of layout.
     * @apiSuccess {Array} rights Contains rights for the current group.
     *
     * @apiSuccessExample Success-Response:
     * HTTP/1.1 200 OK
     * {
     *      "uid": "e2c62ba6eddbb9ce14b589c0b46fd43d",
     *      "label": "Article",
     *      "class": "BackBee\\Site\\Layout",
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
        $parentClass = 'BackBee\Workflow\State';

        $data['parent'] = [
            'class' => $parentClass,
            'rights' => $aclManager->getPermissions($parentClass, $group)
        ];

        $data['objects'] = [];

        $objects = $this->getEntityManager()->getRepository($parentClass)->getWorkflowStatesWithLayout();

        foreach ($objects as $object){

            $data['objects'][] = [
                'uid' => $object->getUid(),
                'label' => $object->getLayout()->getLabel(),
                'rights' => $aclManager->getPermissions($object, $group)
            ];
        }

        return $this->createJsonResponse($data, 200);
    }
}
