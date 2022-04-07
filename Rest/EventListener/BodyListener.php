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

namespace BackBee\Rest\EventListener;

use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

use BackBee\Event\Listener\AbstractPathEnabledListener;
use BackBee\Rest\Encoder\EncoderProviderInterface;

/**
 * Body listener/encoder.
 *
 * @category    BackBee
 *
 *
 * @author      k.golovin
 */
class BodyListener extends AbstractPathEnabledListener
{
    /**
     * @var EncoderProviderInterface
     */
    private $encoderProvider;

    /**
     * @var boolean
     */
    private $throwExceptionOnUnsupportedContentType;

    /**
     * Constructor.
     *
     * @param EncoderProviderInterface $encoderProvider                        Provider for encoders
     * @param boolean          $throwExceptionOnUnsupportedContentType
     * @param string           $path
     */
    public function __construct(EncoderProviderInterface $encoderProvider, $throwExceptionOnUnsupportedContentType = false)
    {
        $this->encoderProvider = $encoderProvider;
        $this->throwExceptionOnUnsupportedContentType = $throwExceptionOnUnsupportedContentType;
    }

    /**
     * Core request handler.
     *
     * @param GetResponseEvent $event The event
     *
     * @throws BadRequestHttpException
     * @throws UnsupportedMediaTypeHttpException
     */
    public function onRequest(GetResponseEvent $event)
    {
        $request = $event->getRequest();

        // skip if route does not match
        if (false === $this->isEnabled($request)) {
            return;
        }

        $content = $request->getContent();

        if ('' === $content) {
            // no content provided
            return;
        }

        if (!count($request->request->all())
            && in_array($request->getMethod(), array('POST', 'PUT', 'PATCH', 'DELETE'))
        ) {
            $contentType = $request->headers->get('Content-Type');

            $format = null === $contentType
                ? $request->getRequestFormat()
                : $request->getFormat($contentType);

            if ($format && !$this->encoderProvider->supports($format)) {
                if ($this->throwExceptionOnUnsupportedContentType) {
                    throw new UnsupportedMediaTypeHttpException("Request body format '$format' not supported");
                }

                return;
            }

            $decoder = $this->encoderProvider->getEncoder($format);

            if (!empty($content)) {
                $data = $decoder->decode($content, $format);

                if (is_array($data)) {
                    $request->request = new ParameterBag($data);
                } else {
                    throw new BadRequestHttpException('Invalid '.$format.' message received');
                }
            }
        }
    }
}
