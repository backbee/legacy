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
 * A metadata.
 *
 * Metadata instance is composed by a name and a set of key/value attributes.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class MetaData implements \IteratorAggregate, \Countable, \JsonSerializable
{

    /**
     * The name of the metadata.
     *
     * @var string
     */
    private $name;

    /**
     * An array of attributes.
     *
     * @var array
     */
    private $attributes = [];

    /**
     * The scheme to compute for dynamic attributes.
     *
     * @var array
     */
    private $scheme = [];

    /**
     * The attributes to be computed.
     *
     * @var array
     */
    private $is_computed = [];

    /**
     * Class constructor.
     *
     * @param string $name
     */
    public function __construct($name = null)
    {
        if (null !== $name) {
            $this->setName($name);
        }
    }

    /**
     * Retuns the name of the metadata.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Sets the name of the metadata.
     *
     * @param  string $name
     *
     * @return MetaData
     * @throws \InvalidArgumentException Occurs if $name if not a valid string.
     */
    public function setName($name)
    {
        if (!preg_match('/[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/', $name)) {
            throw new \InvalidArgumentException(sprintf('Invalid name for metadata: `%s`.', $name));
        }

        $this->name = $name;

        return $this;
    }

    /**
     * Checks if the provided attribute exists for this metadata.
     *
     * @param  string $attribute The attribute looked for.
     *
     * @return boolean Returns TRUE if the attribute is defined for the metadata, FALSE otherwise.
     * @codeCoverageIgnore
     */
    public function hasAttribute($attribute)
    {
        return array_key_exists($attribute, $this->attributes);
    }

    /**
     * Returns the value of the attribute.
     *
     * @param  string $attribute The attribute looked for
     * @param  string $default   Optional, the default value return if attribute does not exist
     *
     * @return string
     */
    public function getAttribute($attribute, $default = '')
    {
        return ($this->hasAttribute($attribute)) ? $this->attributes[$attribute] : $default;
    }

    /**
     * Sets the value of the attribute.
     *
     * @param  string                    $attribute
     * @param  string                    $scheme
     *
     * @return MetaData
     */
    public function setAttribute($attribute, $value, $scheme = null, $isComputed = false)
    {
        $this->attributes[$attribute] = $value;
        $this->scheme[$attribute] = $scheme;
        $this->is_computed[$attribute] = (true === $isComputed);

        return $this;
    }

    /**
     * Is the attribute is computed?
     *
     * @param  string $attribute
     *
     * @return boolean
     */
    public function isComputed($attribute)
    {
        $attr = $this->getAttribute($attribute);

        return empty($attr) || $this->is_computed[$attribute];
    }

    /**
     * Returns the number of attributes.
     *
     * @return int
     */
    public function count()
    {
        return count($this->attributes);
    }

    /**
     * Returns an iterator for attributes.
     *
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->attributes);
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        $attributes = [];
        foreach ($this->attributes as $attribute => $value) {
            $attr = [
                'attr'  => $attribute,
                'value' => $value,
            ];

            $attributes[] = $attr;
        }

        return $attributes;
    }
}
