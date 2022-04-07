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

use Metadata\MetadataFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\Validator;

use BackBee\Event\Listener\AbstractPathEnabledListener;

/**
 * Pagination listener.
 *
 * @category    BackBee
 *
 *
 * @author      e.chau <eric.chau@lp-digital.fr>
 */
class ParamConverterListener extends AbstractPathEnabledListener
{
    /**
     * @var Metadata\MetadataFactory
     */
    private $metadataFactory;

    /**
     * @var Symfony\Component\Validator\Validator
     */
    private $validator;

    /**
     * Constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct(MetadataFactory $metadataFactory, Validator $validator)
    {
        $this->metadataFactory = $metadataFactory;
        $this->validator = $validator;
    }

    /**
     * Controller.
     *
     * @param FilterControllerEvent $event The event
     */
    public function onKernelController(FilterControllerEvent $event)
    {
        $request = $this->request = $event->getRequest();
        if (false === $this->isEnabled()) {
            return;
        }

        $controller = $event->getController();

        $metadata = $this->getControllerActionMetadata($controller);
        if (null === $metadata || 0 === count($metadata->param_converter_bag)) {
            // no annotations defined for this controller
            return;
        }

        foreach ($metadata->param_converter_bag as $param_converter) {
            $class = $param_converter->class;
            $bag = $param_converter->id_source;
            $unique_identifier = $request->$bag->get($param_converter->id_name, null);
            $entity = null;
            try {
                if (null === $unique_identifier) {
                    throw new \InvalidArgumentException(
                        'Unable to find identifier with provided attribute: '.$param_converter->id_name
                    );
                }
            } catch (\InvalidArgumentException $e) {
                if (true === $param_converter->required) {
                    throw $e;
                }
            }

            if (null !== $unique_identifier) {
                $entity = $event->getKernel()->getApplication()->getEntityManager()->find($class, $unique_identifier);
                if (null === $entity) {
                    throw new NotFoundHttpException("No `$class` exists with uid `$unique_identifier`.");
                }
            }

            $request->attributes->set($param_converter->name, $entity);
        }
    }

    /**
     * @param mixed $controller
     *
     * @return \BackBee\Rest\Mapping\ActionMetadata
     */
    protected function getControllerActionMetadata($controller)
    {
        $controllerClass = get_class($controller[0]);

        $metadata = $this->metadataFactory->getMetadataForClass($controllerClass);

        $controllerMetadata = $metadata->getOutsideClassMetadata();

        $action_metadatas = null;
        if (array_key_exists($controller[1], $controllerMetadata->methodMetadata)) {
            $action_metadatas = $controllerMetadata->methodMetadata[$controller[1]];
        }

        return $action_metadatas;
    }
}
