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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\Constraints as Assert;

use BackBee\Bundle\AbstractBundleController;
use BackBee\Bundle\BundleControllerResolver;
use BackBee\Bundle\BundleInterface;
use BackBee\Rest\Controller\Annotations as Rest;
use BackBee\Rest\Patcher\EntityPatcher;
use BackBee\Rest\Patcher\Exception\InvalidOperationSyntaxException;
use BackBee\Rest\Patcher\Exception\UnauthorizedPatchOperationException;
use BackBee\Rest\Patcher\OperationSyntaxValidator;
use BackBee\Rest\Patcher\RightManager;

/**
 * REST API for application bundles.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      e.chau <eric.chau@lp-digital.fr>
 * @author      Mickaël Andrieu <mickael.andrieu@lp-digital.fr>
 * @author      Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class BundleController extends AbstractRestController
{
    /**
     * Returns a collection of declared bundles.
     *
     * @Rest\Security("is_fully_authenticated() & has_role('ROLE_API_USER')")
     */
    public function getCollectionAction()
    {
        $application = $this->getApplication();

        $bundles = [];
        
        foreach ($this->getApplication()->getBundles() as $bundle) {
            if ($this->isGranted('EDIT', $bundle) || ($bundle->isEnabled() && $this->isGranted('VIEW', $bundle))) {
                $bundles[] = $bundle;
            }
        }

        return $this->createJsonResponse($bundles, 200, array(
            'Content-Range' => '0-'.(count($bundles) - 1).'/'.count($bundles),
        ));
    }

    /**
     * Returns the bundle with id $id if it exists, else a 404 response will be generated.
     *
     * @param string $id the id of the bundle we are looking for
     *
     * @Rest\Security("is_fully_authenticated() & has_role('ROLE_API_USER')")
     */
    public function getAction($id)
    {
        $bundle = $this->getBundleById($id);

        try {
            $this->granted('EDIT', $bundle);
        } catch (\Exception $e) {
            if ($bundle->isEnabled()) {
                $this->granted('VIEW', $bundle);
            } else {
                throw $e;
            }
        }

        return $this->createJsonResponse($bundle);
    }

    /**
     * Patch the bundle.
     *
     * @Rest\RequestParam(name="0", description="Patch operations", requirements={
     *   @Assert\NotBlank(message="Request must contain at least one operation")
     * })
     *
     * @param string $id the id of the bundle we are looking for
     *
     * @Rest\Security("is_fully_authenticated() & has_role('ROLE_API_USER')")
     */
    public function patchAction($id, Request $request)
    {
        $bundle = $this->getBundleById($id);

        $this->granted('EDIT', $bundle);
        $operations = $request->request->all();

        try {
            (new OperationSyntaxValidator())->validate($operations);
        } catch (InvalidOperationSyntaxException $e) {
            throw new BadRequestHttpException('operation invalid syntax: '.$e->getMessage());
        }

        $entity_patcher = new EntityPatcher(new RightManager($this->getSerializer()->getMetadataFactory()));
        $entity_patcher->getRightManager()->addAuthorizationMapping($bundle, array(
            'category'        => array('replace'),
            'config_per_site' => array('replace'),
            'enable'          => array('replace'),
        ));

        try {
            $entity_patcher->patch($bundle, $operations);
        } catch (UnauthorizedPatchOperationException $e) {
            throw new BadRequestHttpException('Invalid patch operation: '.$e->getMessage());
        }

        $this->getApplication()->getContainer()->get('config.persistor')->persist(
            $bundle->getConfig(),
            null !== $bundle->getConfig()->getProperty('config_per_site')
                ? $bundle->getConfig()->getProperty('config_per_site')
                : false
        );

        return $this->createJsonResponse(null, 204);
    }

    /**
     * This method is the front controller of every bundles exposed actions.
     *
     * @param string $bundleName     name of bundle we want to reach its exposed actions
     * @param string $controllerName controller name
     * @param string $actionName     name of exposed action we want to reach
     * @param string $parameters     optionnal, action's parameters
     *
     * @return Response              Bundle Controller Response
     *
     * @Rest\Security("is_fully_authenticated() & has_role('ROLE_API_USER')")
     */
    public function accessBundleExposedRoutesAction($bundleName, $controllerName, $actionName, $parameters)
    {
        $bundle = $this->getBundleById($bundleName);

        $controller = (new BundleControllerResolver($this->getApplication()))->resolve($bundleName, $controllerName);

        if ($controller instanceof AbstractBundleController) {
            $controller->setBundle($bundle);
        }

        if (false === empty($parameters)) {
            $parameters = array_filter(explode('/', $parameters));
        }

        $response = call_user_func_array([$controller, $actionName], (array)$parameters);

        return is_object($response) && $response instanceof Response
            ? $response
            : $this->createJsonResponse($response)
        ;
    }

    /**
     * @see BackBee\Rest\Controller\ARestController::granted
     */
    protected function granted($attributes, $object = null, $message = 'Access denied')
    {
        try {
            parent::granted($attributes, $object);
        } catch (AccessDeniedException $e) {
            throw new AccessDeniedException(
                'Acces denied: no "'
                .(is_array($attributes) ? implode(', ', $attributes) : $attributes)
                .'" rights for bundle '.get_class($object).'.'
            );
        }

        return true;
    }

    /**
     * Returns a bundle by id.
     *
     * @param string $id
     *
     * @throws NotFoundHttpException is raise if no bundle was found with provided id
     *
     * @return BundleInterface
     */
    private function getBundleById($id)
    {
        if (null === $bundle = $this->getApplication()->getBundle($id)) {
            throw new NotFoundHttpException("No bundle exists with id `$id`");
        }

        return $bundle;
    }

    /**
     * @api {get} /bundle/:group/permissions Get permissions (ACL)
     * @apiName getPermissionsAction
     * @apiGroup Bundle
     * @apiVersion 0.2.0
     *
     * @apiPermission ROLE_API_USER
     *
     * @apiError NoAccessRight Invalid authentication information.
     * @apiError GroupNotFound No <strong>BackBee\\Security\\Group</strong> exists with uid <code>group</code>.
     *
     * @apiHeader {String} X-API-KEY User's public key.
     * @apiHeader {String} X-API-SIGNATURE Api signature generated for the request.
     *
     * @apiParam {Number} group Group id.
     *
     * @apiSuccess {String} uid Id of layout.
     * @apiSuccess {String} label Label of layout.
     * @apiSuccess {String} class Classname of layout.
     * @apiSuccess {Array} rights Contains rights for the current group.
     *
     * @apiSuccessExample Success-Response:
     * HTTP/1.1 200 OK
     * {
     *      "uid": "demo",
     *      "label": "DemoBundle",
     *      "class": "BackBee\\Bundle\\DemoBundle\\Demo",
     *      "rights": {
     *          "total": 527,
     *          "view": 1,
     *          "create": 1,
     *          "edit": 1,
     *          "delete": 1,
     *          "commit": 0,
     *          "publish": 1
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
        $data = [];
        $group = $request->attributes->get('group');
        $aclManager = $this->getContainer()->get('security.acl_manager');

        foreach ($this->getApplication()->getBundles() as $bundle) {

            if ($bundle->isEnabled() && true === array_key_exists('admin_entry_point', $bundle->getProperty())) {

                $data[] = [
                    'uid' => $bundle->getId(),
                    'label' => $bundle->getProperty()['name'],
                    'class' => $bundle->getType(),
                    'rights' => $aclManager->getPermissions($bundle->getType(), $group)
                ];
            }
        }

        return $this->createJsonResponse($data, 200);
    }
}
