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

namespace BackBee\ClassContent;

use Doctrine\ORM\Mapping as ORM;

/**
 * Indexation entry for content.
 *
 * @category    BackBee
 *
 *
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 *
 * @ORM\Entity(repositoryClass="BackBee\ClassContent\Repository\IndexationRepository")
 * @ORM\Table(name="indexation",indexes={
 *     @ORM\Index(name="IDX_OWNER", columns={"owner_uid"}),
 *     @ORM\Index(name="IDX_CONTENT", columns={"content_uid"}),
 *     @ORM\Index(name="IDX_VALUE", columns={"value"}),
 *     @ORM\Index(name="IDX_SEARCH", columns={"field", "value"})
 * })
 */
class Indexation
{
    /**
     * The indexed content.
     *
     * @var string
     *
     * @ORM\Id
     * @ORM\ManyToOne(targetEntity="BackBee\ClassContent\AbstractClassContent", inversedBy="_indexation", fetch="EXTRA_LAZY")
     * @ORM\JoinColumn(name="content_uid", referencedColumnName="uid")
     */
    protected $_content;

    /**
     * The indexed field of the content.
     *
     * @var string
     *
     * @ORM\Id
     * @ORM\Column(type="string", name="field")
     */
    protected $_field;

    /**
     * The owner content of the indexed field.
     *
     * @var AbstractClassContent
     *
     * @ORM\ManyToOne(targetEntity="BackBee\ClassContent\AbstractClassContent", fetch="EXTRA_LAZY")
     * @ORM\JoinColumn(name="owner_uid", referencedColumnName="uid")
     */
    protected $_owner;

    /**
     * The value of the indexed field.
     *
     * @var string
     *
     * @ORM\Column(type="string", name="value")
     */
    protected $_value;

    /**
     * The optional callback to apply while indexing.
     *
     * @var string
     *
     * @ORM\Column(type="string", name="callback", nullable=true)
     */
    protected $_callback;

    /**
     * Class constructor.
     *
     * @param AbstractClassContent $content_uid The unique identifier of the indexed content
     * @param string        $field       The indexed field of the indexed content
     * @param AbstractClassContent $owner_uid   The unique identifier of the owner content of the field
     * @param string        $value       The value of the indexed field
     * @param string        $callback    The optional callback to apply while indexing the value
     */
    public function __construct($content = null, $field = null, $owner = null, $value = null, $callback = null)
    {
        $this
            ->setContent($content)
            ->setField($field)
            ->setOwner($owner)
            ->setValue($value)
            ->setCallback($callback)
        ;
    }

    /**
     * @codeCoverageIgnore
     *
     * @return string
     */
    public function getField()
    {
        return $this->_field;
    }

    /**
     * @codeCoverageIgnore
     *
     * @return function
     */
    public function getCallback()
    {
        return $this->_callback;
    }

    /**
     * @codeCoverageIgnore
     *
     * @return string
     */
    public function getValue()
    {
        return $this->_value;
    }

    /**
     * @codeCoverageIgnore
     *
     * @return string
     */
    public function getContent()
    {
        return $this->_content;
    }

    /**
     * @codeCoverageIgnore
     *
     * @param string $content
     *
     * @return self
     */
    public function setContent($content)
    {
        $this->_content = $content;

        return $this;
    }

    /**
     * @codeCoverageIgnore
     *
     * @param string $field
     *
     * @return self
     */
    public function setField($field)
    {
        $this->_field = $field;

        return $this;
    }

    /**
     * @codeCoverageIgnore
     *
     * @param \Symfony\Component\Security\Acl\Model\SecurityIdentityInterface $owner
     *
     * @return self
     */
    public function setOwner($owner)
    {
        $this->_owner = $owner;

        return $this;
    }

    /**
     * @codeCoverageIgnore
     *
     * @param string $value
     *
     * @return self
     */
    public function setValue($value)
    {
        $this->_value = $value;

        return $this;
    }

    /**
     * @codeCoverageIgnore
     *
     * @param function $callback
     *
     * @return self
     */
    public function setCallback($callback)
    {
        $this->_callback = $callback;

        return $this;
    }
}
