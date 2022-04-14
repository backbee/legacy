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

namespace BackBee\Resources;

use BackBee\ApplicationInterface;
use BackBee\Rest\Controller\Event\ValidateFileUploadEvent;
use BackBee\Util\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Resource manager.
 *
 * @author Djoudi Bensid <d.bensid@obione.eu>
 */
class ResourceManager
{
    /**
     * @var ApplicationInterface
     */
    public $application;

    /**
     * Constructor.
     */
    public function __construct(ApplicationInterface $application)
    {
        $this->application = $application;
    }

    /**
     * Upload file from the request
     *
     * @param null|\Symfony\Component\HttpFoundation\File\UploadedFile $file
     *
     * @return array $data Retrieve into the content of response
     *
     * @throws BadRequestHttpException The file is too big
     */
    public function doRequestUpload(?UploadedFile $file): array
    {
        $tmpDirectory = $this->application->getTemporaryDir();
        $data = [];

        if (null !== $file) {
            if ($file->isValid()) {
                if ($file->getClientSize() <= $file->getMaxFilesize()) {
                    $data = $this->buildData($file->getClientOriginalName(), $file->guessExtension());
                    $file->move($tmpDirectory, $data['filename']);
                    $data['size'] = round($file->getClientSize() / 1024, 2);
                    if ($imageInfo = @getimagesize($data['path'])) {
                        if (isset($imageInfo[0], $imageInfo[1])) {
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
     * @param string $src base64
     * @param string $originalName
     *
     * @return array $data
     */
    public function doUpload(string $src, string $originalName): array
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
     * @param string $originalName
     * @param string $extension
     *
     * @return array $data
     */
    public function buildData(string $originalName, string $extension): array
    {
        $tmpDirectory = $this->application->getTemporaryDir();
        $fileName = md5($originalName . uniqid('', true)) . '.' . $extension;

        return [
            'originalname' => $originalName,
            'path' => $tmpDirectory . DIRECTORY_SEPARATOR . $fileName,
            'filename' => $fileName,
        ];
    }
}
