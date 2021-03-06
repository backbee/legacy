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

use JMS\Serializer\DeserializationContext;
use JMS\Serializer\SerializationContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Exception\InsufficientAuthenticationException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

use BackBee\Controller\Controller;
use BackBee\Rest\Exception\ValidationException;
use BackBee\Rest\Formatter\FormatterInterface;

/**
 * Abstract class for an api controller.
 *
 * @category    BackBee
 *
 *
 * @author      k.golovin
 */
abstract class AbstractRestController extends Controller implements RestControllerInterface, FormatterInterface
{
    /**
     * @var \JMS\Serializer\Serializer
     */
    protected $serializer;

    /**
     * @access public
     */
    public function optionsAction($endpoint)
    {
        // TODO

        return array();
    }

    /*
     * Default formatter for a collection of objects
     *
     * Implements FormatterInterface::formatCollection($collection)
     */
    public function formatCollection($collection, $format = 'json')
    {
        $items = array();

        foreach ($collection as $item) {
            $items[] = $item;
        }

        $context = new SerializationContext();
        $context->setSerializeNull(true);
        $context->enableMaxDepthChecks(true);

        return $this->getSerializer()->serialize($items, 'json', $context);
    }

    /**
     * Serializes an object.
     *
     * Implements FormatterInterface::formatItem($item)
     *
     * @param mixed $item
     *
     * @return array
     */
    public function formatItem($item, $format = 'json')
    {
        $formatted = null;

        switch ($format) {
            case 'json':
                // serialize properties with null values
                $context = new SerializationContext();
                $context->setSerializeNull(true);
                $context->enableMaxDepthChecks(true);
                $formatted = $this->getSerializer()->serialize($item, 'json', $context);
                break;
            case 'jsonp':
                $callback = $this->getRequest()->query->get('jsonp.callback', 'JSONP.callback');

                // validate against XSS
                $validator = new \JsonpCallbackValidator();
                if (!$validator->validate($callback)) {
                    throw new BadRequestHttpException('Invalid JSONP callback value');
                }

                $context = new SerializationContext();
                $context->setSerializeNull(true);
                $json = $this->getSerializer()->serialize($item, 'json');

                $formatted = sprintf('/**/%s(%s)', $callback, $json);
                break;
            default:
                // any other format is not supported
                throw new \InvalidArgumentException(sprintf('Format not supported: %s', $format));
        }

        return $formatted;
    }

    /**
     * Deserialize data into Doctrine entity.
     *
     * @param string|mixed $item Either a valid Entity class name, or a Doctrine Entity object
     *
     * @return mixed
     */
    public function deserializeEntity(array $data, $entityOrClass)
    {
        $context = null;
        if (is_object($entityOrClass)) {
            $context = DeserializationContext::create();
            $context->attributes->set('target', $entityOrClass);
            $context->enableMaxDepthChecks(true);
            $entityOrClass = get_class($entityOrClass);
        }

        return $this->getSerializer()->deserialize(json_encode($data), $entityOrClass, 'json',  $context);
    }

    /**
     * Create a JsonResponse.
     *
     * @see JsonResponse::__construct()
     *
     * @param mixed   $data
     * @param integer $status
     * @param array   $headers
     *
     * @return JsonResponse
     */
    protected function createJsonResponse($data = null, $status = 200, $headers = array())
    {
        return new JsonResponse($data, $status, $headers);
    }

    /**
     * Create a RESTful response.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function createResponse($content = '', $statusCode = 200, $contentType = 'application/json')
    {
        if ('' === $content && 'application/json' === $contentType) {
            $content = '{}';
        }

        $response = new Response($content, $statusCode);
        $response->headers->set('Content-Type', $contentType);

        return $response;
    }

    /**
     * Returns a RedirectResponse to the given URL.
     *
     * @param string $url    The URL to redirect to
     * @param int    $status The status code to use for the Response
     *
     * @return RedirectResponse
     */
    protected function redirect($url, $status = 302)
    {
        return new RedirectResponse($url, $status);
    }

    /**
     * @param type $message
     *
     * @return type
     */
    protected function create404Response($message = null)
    {
        $response = $this->createResponse();
        $response->setStatusCode(404, $message);

        return $response;
    }

    /**
     * @return \JMS\Serializer\Serializer
     */
    protected function getSerializer()
    {
        $this->serializer = $this->getContainer()->get('serializer');

        return $this->serializer;
    }

    protected function createValidationException($field, $value, $message)
    {
        return new ValidationException(new ConstraintViolationList(array(
            new ConstraintViolation($message, $message, array(), $field, $field, $value),
        )));
    }

    /**
     * Same as isGranted() but throw exception if it is not instead of return false.
     *
     * @param string $attributes
     * @param mixed  $object
     *
     * @return boolean
     */
    protected function granted($attributes, $object = null, $message = 'Permission denied')
    {
        $security_context = $this->getApplication()->getSecurityContext();

        if (null !== $security_context->getACLProvider() && false === parent::isGranted($attributes, $object)) {
            throw new InsufficientAuthenticationException($message);
        }

        return true;
    }

    /**
     * @param string $name
     *
     * @return object|null
     */
    protected function getEntityFromAttributes($name)
    {
        return $this->getRequest()->attributes->get($name);
    }

    /**
     * Keep the order of contents by the list of uids
     *
     * @param array $uids
     * @param Paginator $contents
     *
     * @return null|Paginator
     */
    protected function sortByUids($uids, $contents)
    {
        $uids = (array) $uids;

        $orderedUids = [];
        foreach ($uids as $key => $uid) {
            $orderedUids[$uid] = $key;
        }

        $formattedResult = [];
        foreach ($contents as $content) {
            if (isset($orderedUids[$content->getUid()])) {
                $formattedResult[$orderedUids[$content->getUid()]] = $content;
            }
        }

        ksort($formattedResult);

        return $formattedResult;
    }
}
