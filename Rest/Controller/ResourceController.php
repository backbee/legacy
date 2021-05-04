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

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use BackBee\Rest\Controller\Annotations as Rest;
use BackBee\Rest\Controller\Event\ValidateFileUploadEvent;
use BackBee\Util\File\File;

/**
 * REST API for Resources
 *
 * @category    BackBee
 * @package     BackBee\Rest
 * @copyright   Lp digital system
 * @author      f.kroockmann <florian.kroockmann@lp-digital.fr>
 * @author      Mickaël Andrieu <mickael.andrieu@lp-digital.fr>
 */
class ResourceController extends AbstractRestController
{
    /**
     * Upload file action
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws NotFoundHttpException No file in the request
     * @throws BadRequestHttpException Only on file can be upload
     * @Rest\Security("is_fully_authenticated() & has_role('ROLE_API_USER')")
     */
    public function uploadAction(Request $request)
    {
        $files = $request->files;
        $data = [];

        if ($files->count() === 1) {
            foreach ($files as $file) {
                $data = $this->doRequestUpload($file);
                break;
            }
        } else {
            if ($files->count() === 0) {
                $src = $request->request->get('src');
                $originalName = $request->request->get('originalname');
                if (null !== $src && null !== $originalName) {
                    $data = $this->doUpload($src, $originalName);
                } else {
                    throw new NotFoundHttpException('No file to upload');
                }
            } else {
                throw new BadRequestHttpException('You can upload only one file by request');
            }
        }

        return $this->createJsonResponse($data, 201);
    }

    /**
     * Upload file from the request
     *
     * @param  UploadedFile $file
     * @return Array $data Retrieve into the content of response
     * @throws BadRequestHttpException The file is too big
     */
    private function doRequestUpload(UploadedFile $file)
    {
        $tmpDirectory = $this->getApplication()->getTemporaryDir();
        $data = [];

        if (null !== $file) {
            if ($file->isValid()) {
                if ($file->getClientSize() <= $file->getMaxFilesize()) {
                    $data = $this->buildData($file->getClientOriginalName(), $file->guessExtension());
                    $file->move($tmpDirectory, $data['filename']);
                    $data['size'] = round($file->getClientSize() / 1024, 2);
                    if ($imageInfo = @getimagesize( $data['path'])) {
                        if (isset($imageInfo[0]) && isset($imageInfo[1])) {
                            $data['width'] = $imageInfo[0];
                            $data['height'] = $imageInfo[1];
                        }
                    } else {
                        $data['width'] = 0;
                        $data['height'] = 0;
                    }
                } else {
                    throw new BadRequestHttpException('Too big file, the max file size is ' . $file->getMaxFilesize());
                }
            } else {
                throw new BadRequestHttpException($file->getErrorMessage());
            }
        }

        return $data;
    }

    /**
     * Upload file from a base64
     *
     * @param String $src base64
     * @param String $originalName
     * @return Array $data
     */
    private function doUpload($src, $originalName)
    {
        $data = $this->buildData($originalName, File::getExtension($originalName, false));
        file_put_contents($data['path'], base64_decode($src));

        $this->application->getEventDispatcher()->dispatch(
            ValidateFileUploadEvent::EVENT_NAME,
            new ValidateFileUploadEvent($data['path'])
        );

        return $data;
    }

    /**
     * Build data for retrieve into the content of response
     *
     * @param String $originalName
     * @param String $extension
     * @return Array $data
     */
    private function buildData($originalName, $extension)
    {
        $tmpDirectory = $this->getApplication()->getTemporaryDir();
        $fileName = md5($originalName . uniqid('', true)) . '.' . $extension;

        $data = [
            'originalname' => $originalName,
            'path'         => $tmpDirectory . DIRECTORY_SEPARATOR . $fileName,
            'filename'     => $fileName
        ];

        return $data;
    }
}
