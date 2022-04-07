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

namespace BackBee\Serializer\Naming;

use JMS\Serializer\Metadata\PropertyMetadata;
use JMS\Serializer\Naming\PropertyNamingStrategyInterface;
use BackBee\Doctrine\Registry;

/**
 * Doctrine property naming strategy.
 *
 * Uses Doctrine's annotations
 *
 * @category    BackBee
 *
 * 
 * @author      k.golovin
 */
class DoctrinePropertyNamingStrategy implements PropertyNamingStrategyInterface
{
    /**
     * @var BackBee\Doctrine\Registry
     */
    private $doctrine;

    private $delegate;

    private static $metadataCache = array();

    public function __construct(Registry $doctrine, PropertyNamingStrategyInterface $namingStrategy)
    {
        $this->doctrine = $doctrine;
        $this->delegate = $namingStrategy;
    }

    public function translateName(PropertyMetadata $property)
    {
        $metadata = $this->getDoctrineMetadata($property);

        if (isset($metadata->columnNames[$property->name])) {
            return $metadata->columnNames[$property->name];
        }

        return $this->delegate->translateName($property);
    }

    protected function getDoctrineMetadata(PropertyMetadata $property)
    {
        if (!isset(self::$metadataCache[$property->class])) {
            $em = $this->doctrine->getManagerForClass($property->class);
            /* @var $em  \Doctrine\Common\Persistence\ObjectManager */
            self::$metadataCache[$property->class] = $em->getClassMetadata($property->class);
        }

        return self::$metadataCache[$property->class];
    }
}
