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

namespace BackBee\Cache;

use BackBee\Cache\Validator\ValidatorInterface;
use BackBee\DependencyInjection\ContainerInterface;
use BackBee\Exception\InvalidArgumentException;

/**
 * CacheValidator allows you to validate a set of requirements before starting cache process; every
 * validator must implements BackBee\Cache\Validator\ValidatorInterface
 * you are free provide your own validator and put it in any groups you want.
 *
 * @category    BackBee
 *
 *
 * @author      e.chau <eric.chau@lp-digital.fr>
 */
class CacheValidator
{
    const VALIDATOR_SERVICE_TAG = 'cache.validator';

    /**
     * List of every declared validators sorted by group.
     *
     * @var array
     */
    private $validators;

    /**
     * constructor.
     *
     * @param ContainerInterface $container the container from where we get every validators
     */
    public function __construct(ContainerInterface $container)
    {
        $this->validators = array();
        foreach (array_keys($container->findTaggedServiceIds(self::VALIDATOR_SERVICE_TAG)) as $service_id) {
            $this->addValidator($container->get($service_id));
        }
    }

    /**
     * Allows you to add validator.
     *
     * @param ValidatorInterface $validator the validator we want to add
     */
    public function addValidator(ValidatorInterface $validator)
    {
        foreach ((array) $validator->getGroups() as $group_name) {
            if (false === array_key_exists($group_name, $this->validators)) {
                $this->validators[$group_name] = array();
            }

            $this->validators[$group_name][] = $validator;
        }
    }

    /**
     * It will invoke every validators of provided group name to define if current set is valid or not.
     *
     * @param string $group_name the validator group name to use
     * @param mixed  $object
     *
     * @return boolean return true if every validators return true, else false
     */
    public function isValid($group_name, $object = null)
    {
        if (false === $this->isValidGroup($group_name)) {
            throw new InvalidArgumentException("$group_name is not a valid cache validator group.");
        }

        $is_valid = true;
        foreach ($this->validators[$group_name] as $validator) {
            if (false === $validator->isValid($object)) {
                $is_valid = false;
                break;
            }
        }

        return $is_valid;
    }

    /**
     * Define if provided group name is associated to any validators or not.
     *
     * @return boolean true if the provided group name is associated to one validator atleast, else false
     */
    public function isValidGroup($group_name)
    {
        return array_key_exists($group_name, $this->validators);
    }
}
