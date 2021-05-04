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

namespace BackBee\Rest\Hydration;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

/**
 * The ObjectHydrator constructs an object graph out of a solr result set.
 */
class RestHydrator
{
    /**
     * @var EntityManager
     */
    protected $em;

    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    public function hydrateEntity($entity, array $values)
    {
        $classMetadata = $this->em->getClassMetadata(get_class($entity));

        foreach ($values as $fieldName => $value) {
            if ($classMetadata->hasField($fieldName)) {
                $fieldMappping = $classMetadata->getFieldMapping($fieldName);

                if (null === $fieldMappping) {
                    throw new HydrationException($fieldName);
                }

                $type = Type::getType($classMetadata->fieldMappings[$fieldName]['type']);
                $value = $type->convertToPHPValue($value, $this->em->getConnection()->getDatabasePlatform());

                $classMetadata->setFieldValue($entity, $fieldName, $value);
            } elseif (isset($classMetadata->associationMappings[$fieldName])) {
                $fieldMapping = $classMetadata->associationMappings[$fieldName];

                if (ClassMetadataInfo::MANY_TO_MANY === $fieldMapping['type']) {
                    // expecting an array of ids in $value
                    if (1 === count($fieldMapping['relationToTargetKeyColumns'])) {
                        $columnName = array_pop($fieldMapping['relationToTargetKeyColumns']);
                        $otherSideMapping = $this->em->getClassMetadata($fieldMapping['targetEntity']);

                        $value = $this->em->getRepository($fieldMapping['targetEntity'])->findBy(array(
                            $otherSideMapping->fieldNames[$columnName] => $value,
                        ));
                    }

                    $classMetadata->setFieldValue($entity, $fieldName, $value);
                } elseif (ClassMetadataInfo::MANY_TO_ONE === $fieldMapping['type']) {
                    // expecting an array of ids in $value
                    $value = $this->em->getRepository($fieldMapping['targetEntity'])->find($value);
                    $classMetadata->setFieldValue($entity, $fieldName, $value);
                }
            } else {
                throw new HydrationException($fieldName);
            }
        }
    }
}
