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

namespace BackBee\Controller;

use Symfony\Component\HttpFoundation\Response;
use BackBee\BBApplication;
use BackBee\Controller\Exception\FrontControllerException;
use BackBee\Util\MimeType;
use BackBee\Util\File\File;

/**
 * ResourceController expose action for BackBee resource routes.
 *
 * @category    BackBee
 *
 *
 * @author      e.chau <eric.chau@lp-digital.fr>
 */
class ResourceController
{
    /**
     * application this controller belongs to.
     *
     * @var BBApplication
     */
    private $application;

    /**
     * ResourceController's constructor.
     *
     * @param BBApplication $application
     */
    public function __construct(BBApplication $application)
    {
        $this->application = $application;
    }

    /**
     * Handles a resource file request.
     *
     * @param string $filename The resource file to provide
     *
     * @throws FrontControllerException
     *
     * @return Response
     */
    public function resourcesAction($filename, $base_dir = null)
    {
        if (null === $base_dir) {
            File::resolveFilepath($filename, null, array('include_path' => $this->application->getResourceDir()));
        } else {
            File::resolveFilepath($filename, null, array('base_dir' => $base_dir));
        }

        $this->application->info(sprintf('Handling resource URL `%s`.', $filename));

        if (false === file_exists($filename) || false === is_readable($filename)) {
            $request = $this->application->getRequest();

            throw new FrontControllerException(sprintf(
                'The file `%s` can not be found (referer: %s).',
                $request->getHost().'/'.$request->getPathInfo(),
                $request->server->get('HTTP_REFERER')
            ), FrontControllerException::NOT_FOUND);
        }

        return $this->createResourceResponse($filename);
    }

    /**
     * Hdandles classcontent thumbnail request, returns the right thumbnail if it exists, else the default one.
     *
     * @param string $filename
     *
     * @return Response
     */
    public function getClassContentThumbnailAction($filename)
    {
        $base_folder = $this->application->getContainer()->getParameter('classcontent_thumbnail.base_folder');
        $base_directories = array_map(function ($directory) use ($base_folder) {
            return str_replace(
                DIRECTORY_SEPARATOR.DIRECTORY_SEPARATOR,
                DIRECTORY_SEPARATOR,
                $directory.'/'.$base_folder
            );
        }, $this->application->getResourceDir());

        File::resolveFilepath($filename, null, array('include_path' => $base_directories));

        if (false === file_exists($filename)) {
            $filename = $this->getDefaultClassContentThumbnailFilepath($base_directories);
        }

        if (false === file_exists($filename) || false === is_readable($filename)) {
            $request = $this->application->getRequest();

            throw new FrontControllerException(sprintf(
                'The file `%s` can not be found (referer: %s).',
                $request->getHost().'/'.$request->getPathInfo(),
                $request->server->get('HTTP_REFERER')
            ), FrontControllerException::NOT_FOUND);
        }

        return $this->createResourceResponse($filename);
    }

    /**
     * Returns the default classcontent thumbnail filepath.
     *
     * @param array $base_directories list of every resources directories of current application
     *
     * @return string
     */
    private function getDefaultClassContentThumbnailFilepath(array $base_directories)
    {
        $filename = 'default_thumbnail.png';
        File::resolveFilepath($filename, null, array('include_path' => $base_directories));

        return $filename;
    }

    /**
     * Create Response object for resource.
     *
     * @param string $filename valid filepath (file exists and readable)
     *
     * @return Response
     */
    private function createResourceResponse($filename)
    {
        $response = new Response();

        $filestats = stat($filename);

        $response->headers->set('Content-Type', MimeType::getInstance()->guess($filename));
        $response->headers->set('Content-Length', $filestats['size']);

        $response->setCache(array(
            'etag'          => basename($filename),
            'last_modified' => new \DateTime('@'.$filestats['mtime']),
            'public'        => 'public',
        ));

        $response->setContent(file_get_contents($filename));

        return $response;
    }
}
