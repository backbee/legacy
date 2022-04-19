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

use BackBee\ApplicationInterface;
use BackBee\Resources\ResourceManager;
use BackBee\Rest\Controller\Annotations as Rest;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * REST API for Resources
 *
 * @author f.kroockmann <florian.kroockmann@lp-digital.fr>
 * @author Mickaël Andrieu <mickael.andrieu@lp-digital.fr>
 * @author Djoudi Bensid <d.bensid@obione.eu>
 *
 * @SWG\Tag(name="Resource")
 */
class ResourceController extends AbstractRestController
{
    /**
     * @var ResourceManager
     */
    public $resourcesManager;

    /**
     * Constructor.
     *
     * @param \BackBee\ApplicationInterface      $application
     * @param \BackBee\Resources\ResourceManager $resourcesManager
     */
    public function __construct(ApplicationInterface $application, ResourceManager $resourcesManager)
    {
        $this->resourcesManager = $resourcesManager;
        parent::__construct($application);
    }

    /**
     * Upload file action
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @Rest\Security("is_fully_authenticated() & has_role('ROLE_API_USER')")
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function uploadAction(Request $request): JsonResponse
    {
        $files = $request->files;
        $data = [];

        if ($files->count() === 1) {
            foreach ($files as $file) {
                $data = $this->resourcesManager->doRequestUpload($file);
            }
        } elseif ($files->count() === 0) {
            $src = $request->request->get('src');
            $originalName = $request->request->get('originalname');
            if (null !== $src && null !== $originalName) {
                $data = $this->resourcesManager->doUpload($src, $originalName);
            } else {
                throw new NotFoundHttpException('No file to upload');
            }
        } else {
            throw new BadRequestHttpException('You can upload only one file by request');
        }

        return new JsonResponse($data, Response::HTTP_CREATED);
    }
}
