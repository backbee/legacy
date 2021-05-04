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

namespace BackBee\Bundle\Registry;

use Symfony\Component\Security\Acl\Model\DomainObjectInterface;
use BackBee\Bundle\Registry;

/**
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author n.dufreche <nicolas.dufreche@lp-digital.fr>
 */
class Builder
{
    /**
     * @var boolean
     */
    private $is_registry_entity = false;
    /**
     * @var string
     */
    private $classname;
    /**
     * @var mixed
     */
    private $entity;
    /**
     * @var array
     */
    private $registries;

    /**
     * build the entity from the registry.
     */
    private function buildEntity()
    {
        $classname = $this->classname;
        if (class_exists($classname) && $this->isRegistryEntity(new $classname())) {
            $this->entity = new $classname();
            $this->buildEntityClass();
        } else {
            $this->entity = new \stdClass();
            $this->buildStdClass();
        }
    }

    /**
     * Set the entity.
     *
     * @param mixed $entity
     *
     * @return self
     */
    public function setEntity($entity)
    {
        if ($this->isRegistryEntity(get_class($entity))) {
            $this->classname = get_class($entity);
        }
        $this->entity = $entity;

        return $this;
    }

    /**
     * return the entity.
     *
     * @return mixed
     */
    public function getEntity()
    {
        if (!$this->entity) {
            $this->buildEntity();
        }

        return $this->entity;
    }

    /**
     * Add registries elements.
     *
     * @param array $registries
     *
     * @return self
     */
    public function setRegistries(array $registries, $classname)
    {
        $this->classname = $classname;
        $this->registries = $registries;

        return $this;
    }

    /**
     * return the registries elements as array.
     *
     * @return array
     */
    public function getRegistries()
    {
        if (!$this->registries) {
            $this->buildRegistries();
        }

        return $this->registries;
    }

    /**
     * Automatique entity builder from the registries elements.
     */
    private function buildEntityClass()
    {
        foreach ($this->registries as $registry) {
            if ($registry->getKey() === 'identifier') {
                $this->entity->setObjectIdentifier($registry->getValue());
            } else {
                $this->entity->setObjectProperty($registry->getKey(), $registry->getValue());
            }
        }
    }

    /**
     * Automatique stdClass builder from the registries elements.
     */
    private function buildStdClass()
    {
        foreach ($this->registries as $registry) {
            $this->entity->{$registry->getKey()} = $registry->getValue();
        }
    }

    /**
     * Automatique registry builder from the current entity.
     */
    private function buildRegistries()
    {
        if (is_object($this->entity) && $this->isRegistryEntity($this->entity)) {
            $this->buildRegistryFromObject();
        } else {
            throw new \InvalidArgumentException(sprintf(
                'Cannot build registries: current entity must be an instance of %s, current `%s`.',
                'BackBee\Bundle\Registry\RegistryEntityInterface',
                gettype($this->entity)
            ));
        }

        foreach ($this->registries as $registry) {
            $this->entity->{$registry->getKey()} = $registry->getValue();
        }
    }

    /**
     * Automatique registry builder from the current entity object.
     */
    private function buildRegistryFromObject()
    {
        if (!($this->entity instanceof DomainObjectInterface)) {
            throw new \Exception('EntityRegistry have to implement DomainObjectInterface', 1);
        }
        $this->classname = get_class($this->entity);

        $identifier = new Registry();
        $this->registries[] = $identifier->setType($this->classname)->setKey('identifier')->setValue($this->entity->getObjectIdentifier());

        foreach ($this->entity->getObjectProperties() as $key => $value) {
            $registry = new Registry();
            $this->registries[] = $registry->setType($this->classname)->setKey($key)->setValue($value)->setScope($this->entity->getObjectIdentifier());
        }
    }

    /**
     * Identify if the current element is IEntityRegistry.
     *
     * @return boolean
     */
    public function isRegistryEntity($class = null)
    {
        if (!is_null($class)) {
            $this->is_registry_entity = ($class instanceof RegistryEntityInterface);
        }

        return $this->is_registry_entity;
    }
}
