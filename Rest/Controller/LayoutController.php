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
use Symfony\Component\Validator\Constraints as Assert;

use BackBee\Rest\Controller\Annotations as Rest;
use BackBee\Site\Layout;
use BackBee\Site\Site;

/**
 * @category    BackBee
 *
 *
 * @author      e.chau <eric.chau@lp-digital.fr>
 * @author      Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class LayoutController extends AbstractRestController
{
    /**
     * Returns every workflow states associated to provided layout.
     *
     * @param Layout $layout
     *
     * @return JsonResponse
     *
     * @Rest\ParamConverter(name="layout", class="BackBee\Site\Layout")
     */
    public function getWorkflowStateAction(Layout $layout)
    {
        $layout_states = $this->getApplication()->getEntityManager()
            ->getRepository('BackBee\Workflow\State')
            ->getWorkflowStatesForLayout($layout)
        ;

        $states = array(
            'online'  => array(),
            'offline' => array(),
        );

        foreach ($layout_states as $state) {
            if (0 < $code = $state->getCode()) {
                $states['online'][$code] = array(
                    'label' => $state->getLabel(),
                    'code'  => '1_'.$code,
                );
            } else {
                $states['offline'][$code] = array(
                    'label' => $state->getLabel(),
                    'code'  => '0_'.$code,
                );
            }
        }

        $translator = $this->getApplication()->getContainer()->get('translator');

        $states = array_merge(
            array('0' => array('label' => $translator->trans('offline'), 'code' => '0')),
            $states['offline'],
            array('1' => array('label' => $translator->trans('online'), 'code' => '1')),
            $states['online']
        );

        return $this->createJsonResponse(array_values($states), 200, array(
            'Content-Range' => '0-'.(count($states) - 1).'/'.count($states),
        ));
    }

    /**
     * @Rest\ParamConverter(
     *   name="site", id_name="site_uid", id_source="query", class="BackBee\Site\Site", required=false
     * )
     */
    public function getCollectionAction(Request $request)
    {
        $qb = $this->getEntityManager()
            ->getRepository('BackBee\Site\Layout')
            ->createQueryBuilder('l')
            ->select('l, st')
            ->orderBy('l._label', 'ASC')
            ->leftJoin('l._states', 'st')
        ;

        if (null !== ($site = $request->attributes->get('site'))) {
            $qb->select('l, st, si')
                ->innerJoin('l._site', 'si', 'WITH', 'si._uid = :site_uid')
                ->setParameter('site_uid', $site->getUid())
            ;
        } else {
            $qb->select('l, st')
                ->andWhere('l._site IS NULL')
            ;
        }

        $layouts = $qb->getQuery()->getResult();

        $response = $this->createJsonResponse(null, 200, array(
            'Content-Range' => '0-'.(count($layouts) - 1).'/'.count($layouts),
        ));

        $response->setContent($this->formatCollection($layouts));

        return $response;
    }

    /**
     * @Rest\ParamConverter(name="layout", class="BackBee\Site\Layout")
     * @Rest\Security("is_fully_authenticated() & has_role('ROLE_API_USER') & is_granted('VIEW', layout)")
     */
    public function getAction(Layout $layout)
    {
        $response = $this->createJsonResponse();
        $response->setContent($this->formatItem($layout));

        return $response;
    }

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
     * @Rest\ParamConverter(name="site", id_name="site_uid", id_source="query", class="BackBee\Site\Site")
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
        $site = $request->query->get('site_uid');
        $aclManager = $this->getContainer()->get('security.acl_manager');

        $parentClass = 'BackBee\Site\Layout';

        $data['parent'] = [
            'class' => $parentClass,
            'rights' => $aclManager->getPermissions($parentClass, $group)
        ];

        $params = ($site instanceof Site) ? array('_site' => $site) : [];
        $objects = $this->getEntityManager()->getRepository($parentClass)->findBy($params, ['_label' => 'ASC']);

        foreach ($objects as $object){

            $data['objects'][] = [
                'uid' => $object->getUid(),
                'label' => $object->getLabel(),
                'rights' => $aclManager->getPermissions($object, $group)
            ];
        }

        return $this->createJsonResponse($data, 200);
    }
}
