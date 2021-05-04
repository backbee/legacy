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

namespace BackBee\Rest\Mapping\Driver;

use Doctrine\Common\Annotations\AnnotationReader;
use Metadata\ClassMetadata;
use Metadata\Driver\DriverInterface;
use BackBee\Rest\Mapping\ActionMetadata;

/**
 * The AnnotationDriver reads the mapping metadata from docblock annotations.
 *
 * @Annotation
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      k.golovin
 */
class AnnotationDriver implements DriverInterface
{
    /**
     * an annotation reader.
     *
     * @var Doctrine\Common\Annotations\AnnotationReader
     */
    private $reader;

    /**
     * AnnotationDriver constructor.
     *
     * @param AnnotationReader $reader an annotation reader
     */
    public function __construct(AnnotationReader $reader)
    {
        $this->reader = $reader;
    }

    /**
     * @param \ReflectionClass $class
     *
     * @return \Metadata\ClassMetadata
     */
    public function loadMetadataForClass(\ReflectionClass $class)
    {
        $classMetadata = new ClassMetadata($class->getName());

        foreach ($class->getMethods(\ReflectionMethod::IS_PUBLIC) as $reflectionMethod) {
            if (false === strpos($reflectionMethod->getName(), 'Action')) {
                continue;
            }

            if ($reflectionMethod->isAbstract()) {
                continue;
            }

            $methodMetadata = new ActionMetadata($class->getName(), $reflectionMethod->getName());

            $annotations = $this->reader->getMethodAnnotations($reflectionMethod);

            foreach ($annotations as $annotation) {
                if ($annotation instanceof \BackBee\Rest\Controller\Annotations\QueryParam) {
                    $data = array(
                        'name' => $annotation->name,
                        'key' => $annotation->key ? $annotation->key : $annotation->name,
                        'default' => $annotation->default,
                        'description' => $annotation->description,
                        'requirements' => $annotation->requirements,
                    );

                    $methodMetadata->queryParams[] = $data;
                } elseif ($annotation instanceof \BackBee\Rest\Controller\Annotations\RequestParam) {
                    $data = array(
                        'name' => $annotation->name,
                        'key' => $annotation->key ? $annotation->key : $annotation->name,
                        'default' => $annotation->default,
                        'description' => $annotation->description,
                        'requirements' => $annotation->requirements,
                    );

                    $methodMetadata->requestParams[] = $data;
                } elseif ($annotation instanceof \BackBee\Rest\Controller\Annotations\Pagination) {
                    $methodMetadata->default_start = $annotation->default_start;
                    $methodMetadata->default_count = $annotation->default_count;
                    $methodMetadata->max_count = $annotation->max_count;
                    $methodMetadata->min_count = $annotation->min_count;
                } elseif ($annotation instanceof \BackBee\Rest\Controller\Annotations\ParamConverter) {
                    $methodMetadata->param_converter_bag[] = $annotation;
                } elseif ($annotation instanceof \BackBee\Rest\Controller\Annotations\Security) {
                    $methodMetadata->security[] = $annotation;
                }
            }

            $classMetadata->addMethodMetadata($methodMetadata);
        }

        return $classMetadata;
    }
}
