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

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator;

use BackBee\Event\Listener\AbstractPathEnabledListener;
use BackBee\Rest\Exception\ValidationException;

/**
 * Request validation listener.
 *
 * @category    BackBee
 *
 *
 * @author      k.golovin
 */
class ValidationListener extends AbstractPathEnabledListener
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * Constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Core request handler.
     *
     * @param GetResponseEvent $event The event
     *
     * @throws BadRequestHttpException
     * @throws UnsupportedMediaTypeHttpException
     */
    public function onKernelController(FilterControllerEvent $event)
    {
        $controller = $event->getController();
        $request = $event->getRequest();
        $metadata = $this->getControllerActionMetadata($controller);

        if (null !== $metadata) {
            $violations = new ConstraintViolationList();

            if (0 < count($metadata->queryParams)) {
                // set defaults
                $this->setDefaultValues($metadata->queryParams, $request->query);

                $queryViolations = $this->validateParams($this->container->get('validator'), $metadata->queryParams, $request->query);
                $violations->addAll($queryViolations);
            }

            if (0 < count($metadata->requestParams)) {
                // set defaults
                $this->setDefaultValues($metadata->requestParams, $request->request);

                $requestViolations = $this->validateParams($this->container->get('validator'), $metadata->requestParams, $request->request);
                $violations->addAll($requestViolations);
            }

            $violationParam = $this->getViolationsParameterName($metadata);

            if (null !== $violationParam) {
                // if action has an argument for violations, pass it
                $request->attributes->set($violationParam, $violations);
            } elseif (0 < count($violations)) {
                // if action has no arg for violations and there is at least one, throw an exception
                throw new ValidationException($violations);
            }
        }
    }

    /**
     * @param \Metadata\ClassHierarchyMetadata|\Metadata\MergeableClassMetadata $metadata
     *
     * @return string|null
     */
    protected function getViolationsParameterName($metadata)
    {
        foreach ($metadata->reflection->getParameters() as $param) {
            if ($param->getClass() && $param->getClass()->implementsInterface('Symfony\Component\Validator\ConstraintViolationListInterface')) {
                return $param->getName();
            }
        }

        return;
    }

    /**
     * Set default values.
     *
     * @param array                                          $params
     * @param \Symfony\Component\HttpFoundation\ParameterBag $values
     */
    protected function setDefaultValues(array $params, ParameterBag $values)
    {
        foreach ($params as $param) {
            if (!array_key_exists('default', $param) || null === $param['default']) {
                continue;
            }

            if (false === $values->has($param['name'])) {
                $values->set($param['name'], $param['default']);
            }
        }
    }

    /**
     * Validate params.
     *
     * @param \Symfony\Component\Validator\Validator         $validator
     * @param array                                          $params
     * @param \Symfony\Component\HttpFoundation\ParameterBag $values
     *
     * @return \Symfony\Component\Validator\ConstraintViolationList
     */
    protected function validateParams(Validator $validator, array $params, ParameterBag $values)
    {
        $violations = new ConstraintViolationList();
        foreach ($params as $param) {
            if (empty($param['requirements'])) {
                continue;
            }
            $value = $values->get($param['name']);

            $paramViolations = $validator->validateValue($value, $param['requirements']);

            // add missing data
            foreach ($paramViolations as $violation) {
                $extendedViolation = new ConstraintViolation(
                    $violation->getMessage(),
                    $violation->getMessageTemplate(),
                    $violation->getMessageParameters(),
                    $violation->getRoot(),
                    $param['name'].$violation->getPropertyPath(),
                    $violation->getInvalidValue(),
                    $violation->getMessagePluralization(),
                    $violation->getCode()
                );

                $violations->add($extendedViolation);
            }
        }

        return $violations;
    }

    /**
     * @param mixed $controller
     *
     * @return \Metadata\ClassHierarchyMetadata|\Metadata\MergeableClassMetadata
     */
    protected function getControllerActionMetadata($controller)
    {
        $controllerClass = get_class($controller[0]);

        $metadata = $this->container->get('rest.metadata.factory')->getMetadataForClass($controllerClass);

        $controllerMetadata = $metadata->getOutsideClassMetadata();

        $action_metadatas = null;
        if (array_key_exists($controller[1], $controllerMetadata->methodMetadata)) {
            $action_metadatas = $controllerMetadata->methodMetadata[$controller[1]];
        }

        return $action_metadatas;
    }
}
