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
 * MediaController provide actions to BackBee medias routes (get and upload).
 *
 * @category    BackBee
 *
 * 
 * @author      e.chau <eric.chau@lp-digital.fr>
 */
class MediaController
{
    /**
     * Application this controller belongs to.
     *
     * @var BackBee\BBApplication
     */
    private $application;

    /**
     * MediaController's constructor.
     *
     * @param BBApplication $application
     */
    public function __construct(BBApplication $application)
    {
        $this->application = $application;
    }

    /**
     * Handles a media file request.
     *
     * @param string $filename The media file to provide
     *
     * @throws FrontControllerException
     *
     * @return Response
     */
    public function mediaAction($type, $filename, $includePath = array())
    {
        $includePath = array_merge(
            $includePath,
            array($this->application->getStorageDir(), $this->application->getMediaDir())
        );

        if (null !== $this->application->getBBUserToken()) {
            $includePath[] = $this->application->getTemporaryDir();
        }

        $matches = array();
        if (preg_match('/([a-f0-9]{3})\/([a-f0-9]{29})\/(.*)\.([^\.]+)/', $filename, $matches)) {
            $filename = $matches[1].'/'.$matches[2].'.'.$matches[4];
        } elseif (preg_match('/([a-f0-9]{4})([a-f0-9]{4})([a-f0-9]{4})([a-f0-9]{4})([a-f0-9]{4})([a-f0-9]{4})([a-f0-9]{4})([a-f0-9]{4})\/.*\.([^\.]+)/', $filename, $matches)) {
            $filename = $matches[1].$matches[2].$matches[3].$matches[4].$matches[5].$matches[6].$matches[7].$matches[8].'.'.$matches[9];
            File::resolveMediapath($filename, null, array('include_path' => $includePath));
        }

        File::resolveFilepath($filename, null, array('include_path' => $includePath));
        $this->application->info(sprintf('Handling image URL `%s`.', $filename));

        if (false === file_exists($filename) || false === is_readable($filename)) {
            $request = $this->application->getRequest();

            throw new FrontControllerException(sprintf(
                'The file `%s` can not be found (referer: %s).',
                $request->getHost().'/'.$request->getPathInfo(),
                $request->server->get('HTTP_REFERER')
            ), FrontControllerException::NOT_FOUND);
        }

        return $this->createMediaResponse($filename);
    }

    /**
     * Create Response object for media.
     *
     * @param string $filename valid filepath (file exists and readable)
     *
     * @return Response
     */
    private function createMediaResponse($filename)
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
