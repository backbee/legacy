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

namespace BackBee\MetaData;

/**
 * A set of metadata to be associated to a page.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class MetaDataBag implements \IteratorAggregate, \Countable, \JsonSerializable
{
    /**
     * The array of metadata.
     *
     * @var array
     */
    private $metadatas = [];

    /**
     * Adds a new matadata to the bag.
     *
     * @param  MetaData $metadata
     *
     * @return MetaDataBag
     */
    public function add(MetaData $metadata)
    {
        $this->metadatas[$metadata->getName()] = $metadata;

        return $this;
    }

    /**
     * Checks if a metadata exists with the given name.
     *
     * @param  string $name
     *
     * @return Boolean
     */
    public function has($name)
    {
        return array_key_exists($name, $this->metadatas);
    }

    /**
     * Returns the metadata associated to $name or NULL if it doesn't exist.
     *
     * @param  string $name
     *
     * @return MetaData|NULL
     */
    public function get($name)
    {
        return ($this->has($name)) ? $this->metadatas[$name] : null;
    }

    /**
     * Returns the number of attributes.
     *
     * @return int
     */
    public function count()
    {
        return count($this->metadatas);
    }

    /**
     * Returns an iterator for attributes.
     *
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->metadatas);
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        $metadatas = array();
        if (is_array($this->metadatas)) {
            foreach ($this->metadatas as $meta) {
                $attributes = [];

                foreach ($meta->jsonSerialize() as $metadata) {
                    if ('name' !== $metadata['attr']) {
                        $attributes[$metadata['attr']] = $metadata['value'];
                    }
                }

                $metadatas[$meta->getName()] = $attributes;
            }
        }

        return $metadatas;
    }

    /**
     * @param  \stdClass $object
     *
     * @return MetaDataBag
     *
     * @deprecated since version 1.0
     */
    public function fromStdClass(\stdClass $object)
    {
        foreach (get_object_vars($object) as $name => $metadata) {
            if ($this->has($name)) {
                foreach ($metadata as $attribute) {
                    if (false === $attribute->iscomputed) {
                        $this->get($name)->setAttribute($attribute->attr, $attribute->value);
                    }
                }
            }
        }

        return clone $this;
    }
}
