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

use BackBee\ClassContent\Exception\UnknownPropertyException;
use BackBee\Exception\BBException;
use BackBee\NestedNode\Page;

use Doctrine\ORM\Mapping as ORM;

/**
 * A set of content objects in BackBee
 * Implements Iterator, Countable.
 *
 * @category    BackBee
 *
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 * @ORM\Entity(repositoryClass="BackBee\ClassContent\Repository\ClassContentRepository")
 * @ORM\Table(name="content")
 * @ORM\HasLifecycleCallbacks
 */
class ContentSet extends AbstractClassContent implements \Iterator, \Countable
{
    /**
     * Internal position in iterator.
     *
     * @var int
     */
    protected $index = 0;

    /**
     * Pages owning this contentset.
     *
     * @var \Doctrine\Common\Collections\ArrayCollection
     *
     * @ORM\OneToMany(targetEntity="BackBee\NestedNode\Page", mappedBy="_contentset", fetch="EXTRA_LAZY")
     */
    protected $_pages;

    /**
     * {@inheritdoc}
     */
    public function __construct($uid = null, $options = null)
    {
        parent::__construct($uid, $options);

        $this->initData();
    }

    /**
     * Returns the owning pages.
     *
     * @return \Doctrine\Common\Collections\ArrayCollection
     */
    public function getPages()
    {
        return $this->_pages;
    }

    /**
     * Initialized data on postLoad doctrine event.
     */
    public function postLoad()
    {
        // Ensure class content are known
        $data = (array) $this->_data;
        foreach ($data as $dataEntry) {
            $type = key($dataEntry);

            $dataEntry = array_pop($dataEntry);
            if (true === is_array($dataEntry)) {
                $type = key($dataEntry);
            }

            if ($type !== 'scalar' && $type !== 'array') {
                self::getFullClassname($type);
            }
        }

        parent::postLoad();
    }

    /**
     * Alternative recursive clone method, created because of problems related to doctrine clone method.
     *
     * @param \BackBee\NestedNode\Page $originPage
     *
     * @return \BackBee\ClassContent\ContentSet
     */
    public function createClone(Page $originPage = null)
    {
        $clone = parent::createClone($originPage);

        $zones = array();
        $mainNodeUid = null;

        if (null !== $originPage) {
            $mainNodeUid = $originPage->getUid();
            if ($originPage->getContentSet()->getUid() === $this->getUid()) {
                $zones = $originPage->getLayout()->getZones();
            }
        }

        foreach ($this as $subcontent) {
            if (!($subcontent instanceof AbstractClassContent)) {
                continue;
            }

            if (
                $this->getProperty('clonemode') === 'none'
                || ($this->key() < count($zones) && $zones[$this->key()]->defaultClassContent === 'inherited')
                || (null !== $subcontent->getMainNode() && $subcontent->getMainNode()->getUid() !== $mainNodeUid)
            ) {
                $clone->push($subcontent);
            } else {
                $newSubcontent = $subcontent->createClone($originPage);
                $clone->push($newSubcontent);
            }
        }

        return $clone;
    }

    /**
     * Empty the current set of contents.
     */
    public function clear()
    {
        if (null !== $this->getDraft()) {
            $this->getDraft()->clear();
        } else {
            $this->_subcontent->clear();
            $this->subcontentmap = array();
            $this->_data = array();
            $this->index = 0;
        }
    }

    /**
     * @see Countable::count()
     * @return int
     * @codeCoverageIgnore
     */
    public function count()
    {
        return (null === $this->getDraft()) ? count($this->_data) : $this->getDraft()->count();
    }

    /**
     * @see Iterator::current()
     * @return AbstractContent
     * @codeCoverageIgnore
     */
    public function current()
    {
        return (null === $this->getDraft()) ? $this->getData($this->index) : $this->getDraft()->current();
    }

    /**
     * Return the first subcontent of the set.
     *
     * @return AbstractClassContent the first element
     * @codeCoverageIgnore
     */
    public function first()
    {
        return $this->getData(0);
    }

    /**
     * Searches for a given element and, if found, returns the corresponding key/index
     * of that element. The comparison of two elements is strict, that means not
     * only the value but also the type must match.
     * For objects this means reference equality.
     *
     * @param mixed   $element     The element to search for.
     * @param boolean $useIntIndex If TRUE, use integer key
     *
     * @return mixed The key/index of the element or FALSE if the element was not found.
     */
    public function indexOf($element, $useIntIndex = false)
    {
        if ($element instanceof AbstractClassContent) {
            $useIntIndex = (is_bool($useIntIndex)) ? $useIntIndex : false;
            if (false !== $key = $this->_subcontent->indexOf($element)) {
                foreach ($this->_data as $key => $data) {
                    if (false !== $index = array_search($element->getUid(), $data, true)) {
                        $index = ($useIntIndex) ? $key : $index;

                        return $index;
                    }
                }
            }

            return false;
        }

        return array_search($element, $this->_data, true);
    }

    /**
     * Returns the index of the content of $element
     *
     * @param AbstractClassContent $element
     * @param boolean              $useIntIndex
     *
     * @return integer|boolean
     */
    public function indexOfByUid($element, $useIntIndex = false)
    {
        if ($element instanceof AbstractClassContent) {
            /* find content */
            $index = 0;
            foreach ($this->getData() as $key => $content) {
                if ($content instanceof AbstractClassContent && $element->getUid() === $content->getUid()) {
                    $index = ($useIntIndex) ? (int) $key : $index;

                    return $index;
                }
                $index++;
            }

            return false;
        }

        return array_search($element, $this->_data, true);
    }

    /**
     * Replaces element at $index by $contenSet
     *
     * @param int                  $index
     * @param AbstractClassContent $contentSet
     *
     * @return boolean              Always true
     * @throw  BBException          Raises if $index is not an integer
     */
    public function replaceChildAtBy($index, AbstractClassContent $contentSet)
    {
        $index = (isset($index) && is_int($index)) ? $index : false;
        if (is_bool($index)) {
            throw new BBException(__METHOD__.' index  parameter must be an integer');
        }
        $newContentsetArr = array();

        foreach ($this->getData() as $key => $content) {
            $contentToAdd = ($key == $index) ? $contentSet : $content;
            $newContentsetArr[] = $contentToAdd;
        }

        $this->clear();
        foreach ($newContentsetArr as $key => $content) {
            if ($content instanceof AbstractClassContent) {
                $this->push($content);
            }
        }

        return true;
    }

    /**
     * Replaces $prevContentSet by $nextContentSet
     *
     * @param AbstractClassContent $prevContentSet
     * @param AbstractClassContent $nextContentSet
     *
     * @return boolean
     */
    public function replaceChildBy(AbstractClassContent $prevContentSet, AbstractClassContent $nextContentSet)
    {
        $index = $this->indexOfByUid($prevContentSet, true);
        if (is_bool($index)) {
            return false;
        }

        return $this->replaceChildAtBy($index, $nextContentSet);
    }

    /**
     * Return the item at index.
     *
     * @param  integer $index
     *
     * @return AbstractClassContent                     The item or null if $index is out of bounds
     */
    public function item($index)
    {
        if (null !== $this->getDraft()) {
            return $this->getDraft()->item($index);
        }

        if (0 <= $index && $index < $this->count()) {
            return $this->getData($index);
        }

        return;
    }

    /**
     * @see Iterator::key()
     * @return int
     * @codeCoverageIgnore
     */
    public function key()
    {
        return (null === $this->getDraft()) ? $this->index : $this->getDraft()->key();
    }

    /**
     * Return the last subcontent of the set.
     *
     * @return AbstractClassContent the last element
     * @codeCoverageIgnore
     */
    public function last()
    {
        return (null === $this->getDraft()) ? $this->getData($this->count() - 1) : $this->getDraft()->last();
    }

    /**
     * @see Iterator::next()
     * @return AbstractContent
     * @codeCoverageIgnore
     */
    public function next()
    {
        return (null === $this->getDraft()) ? $this->getData($this->index++) : $this->getDraft()->next();
    }

    /**
     * Pop the content off the end of the set and return it.
     *
     * @return AbstractClassContent Returns the last content or NULL if set is empty
     */
    public function pop()
    {
        if (null !== $this->getDraft()) {
            return $this->getDraft()->pop();
        }

        $last = $this->last();

        if (null === $last) {
            return;
        }

        array_pop($this->_data);
        $content = null;
        if (isset($this->subcontentmap[$last->getUid()])) {
            $content = $this->_subcontent->get($this->subcontentmap[$last->getUid()]);
            $this->_subcontent->removeElement($content);
            unset($this->subcontentmap[$last->getUid()]);
        }

        $this->rewind();

        return $content;
    }

    /**
     * Push one element onto the end of the set.
     *
     * @param AbstractClassContent $var The pushed values
     *
     * @return ContentSet    The current content set
     */
    public function push(AbstractClassContent $var)
    {
        if (null !== $this->getDraft()) {
            return $this->getDraft()->push($var);
        }

        if ($this->isAccepted($var)) {
            if (
                    (!$this->_maxentry && !$this->_minentry) ||
                    (is_array($this->_maxentry) && is_array($this->_minentry) && 0 == count($this->_maxentry)) ||
                    ($this->_maxentry > $this->count() && $this->_minentry < $this->count())
            ) {
                $this->_data[] = array($this->_getType($var) => $var->getUid());
                if ($this->_subcontent->add($var)) {
                    $this->subcontentmap[$var->getUid()] = $this->_subcontent->indexOf($var);
                }
            }
        }

        return $this;
    }

    /**
     * @see Iterator::rewind()
     */
    public function rewind()
    {
        if (null === $this->getDraft()) {
            $this->index = 0;
        } else {
            $this->getDraft()->rewind();
        }
    }

    /**
     * Shift the content off the beginning of the set and return it.
     *
     * @return AbstractClassContent Returns the shifted content or NULL if set is empty
     */
    public function shift()
    {
        if (null !== $this->getDraft()) {
            return $this->getDraft()->shift();
        }

        $first = $this->first();
        if (null === $first) {
            return;
        }

        array_shift($this->_data);
        $content = null;
        if (isset($this->subcontentmap[$first->getUid()])) {
            $content = $this->_subcontent->get($this->subcontentmap[$first->getUid()]);
            $this->_subcontent->removeElement($content);
            unset($this->subcontentmap[$first->getUid()]);
        }

        $this->rewind();

        return $content;
    }

    /**
     * Prepend one to the beginning of the set.
     *
     * @param  AbstractClassContent $var The prepended values
     *
     * @return ContentSet    The current content set
     */
    public function unshift(AbstractClassContent $var)
    {
        if (null !== $this->getDraft()) {
            return $this->getDraft()->unshift($var);
        }

        if ($this->isAccepted($var)) {
            if (!$this->_maxentry || $this->_maxentry > $this->count()) {
                array_unshift($this->_data, array($this->_getType($var) => $var->getUid()));
                if ($this->_subcontent->add($var)) {
                    $this->subcontentmap[$var->getUid()] = $this->_subcontent->indexOf($var);
                }
            }
        }

        return $this;
    }

    /**
     * @see Iterator::valid()
     * @return boolean
     * @codeCoverageIgnore
     */
    public function valid()
    {
        return (null === $this->getDraft()) ? isset($this->_data[$this->index]) : $this->getDraft()->valid();
    }

    /*     * **************************************************************** */
    /*                                                                        */
    /*                   Implementation of RenderableInterface                */
    /*                                                                        */
    /*     * **************************************************************** */

    /**
     * Return the data of this content.
     *
     * @param  string  $var        The element to be return, if NULL, all datas are returned
     * @param  Boolean $forceArray Force the return as array
     *
     * @return mixed Could be either NULL or one or array of scalar, array, AbstractClassContent instance
     * @throws \BackBee\AutoLoader\Exception\ClassNotFoundException Occurs if the class of a subcontent can not be loaded
     */
    public function getData($var = null, $forceArray = false)
    {
        try {
            return parent::getData($var, $forceArray);
        } catch (UnknownPropertyException $e) {
            return;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize($format = self::JSON_DEFAULT_FORMAT)
    {
        $data = parent::jsonSerialize($format);

        if (isset($data['elements'])) {
            $elements = [];
            foreach ($data['elements'] as $element) {
                $elements[] = [
                    'uid' => $element['uid'],
                    'type' => $element['type'],
                ];
            }

            $data['elements'] = $elements;
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    protected function initData()
    {
        if (null === $this->getProperty('auto_hydrate')) {
            $this->setProperty('auto_hydrate', false);
        }

        parent::initData();
    }

    /**
     * Sets options at the construction of a new instance.
     *
     * @param array $options Initial options for the content:
     *                       - label       the label of the content
     *                       - maxentry    the maximum number of content accepted
     *                       - minentry    the minimum number of content accepted
     *                       - accept      an array of classname accepted
     *                       - default     array default value for datas
     *
     * @return \BackBee\ClassContent\ContentSet
     */
    protected function setOptions($options = null)
    {
        if (null === $options) {
            return $this;
        }

        $options = (array) $options;

        $this->_label = isset($options['label']) ? $options['label'] : null;
        $this->_maxentry = isset($options['maxentry']) ? intval($options['maxentry']) : null;
        $this->_minentry = isset($options['minentry']) ? intval($options['minentry']) : null;

        if (isset($options['accept'])) {
            $this->_accept = [];
            $this->_addAcceptedType($options['accept']);
        }

        if (isset($options['default'])) {
            $options['default'] = (array) $options['default'];
            foreach ($options['default'] as $value) {
                $this->push($value);
            }
        }

        return $this;
    }

    /**
     * Dynamically adds and sets new element to this content.
     *
     * @param string  $var          the name of the element
     * @param string  $type         the type
     * @param array   $options      Initial options for the content (see this constructor)
     * @param Boolean $updateAccept dynamically accept or not the type for the new element
     *
     * @return \BackBee\ClassContent\AbstractClassContent The current instance
     *
     * @deprecated since version 1.0
     */
    protected function defineData($var, $type = 'scalar', $options = null, $updateAccept = false)
    {
        return $this->setOptions($options);
    }

    /**
     * {@inheritdoc}
     */
    public function setParam($key, $value = null)
    {
        parent::setParam($key, $value);

        if ('accept' === $key && is_array($value)) {
            $value = self::getShortClassname($value);

            if (null !== $this->getDraft()) {
                $this->getDraft()->setAccept($value);
            } else {
                $this->setAccept($value);
            }
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getAllParams()
    {
        $params = parent::getAllParams();

        if (isset($params['accept'])) {
            $params['accept']['value'] = self::getFullClassname($this->getAccept());
        }

        return $params;
    }

    /**
     * {@inheritdoc}
     */
    public function getParam($key)
    {
        $param = parent::getParam($key);
        if (is_array($param) && 'accept' === $key) {
            $param['value'] = self::getFullClassname($this->getAccept());
        }

        return $param;
    }

    /**
     * {@inheritdoc}
     */
    protected function defineParam($var, $options = null)
    {
        parent::defineParam($var, $options);

        if ('accept' === $var) {
            if (null !== $options) {

                $accept = array_key_exists('value', $options) ? $options['value'] : $options;

                $this->_addAcceptedType(array_merge((array) $accept, $this->getParamValue('accept')));
            }
        }

        return $this;
    }

    /**
     * Adds a new accepted type to the element.
     *
     * @param string $type the type to accept
     * @param string $var  the element
     *
     * @return \BackBee\ClassContent\AbstractClassContent The current instance
     */
    protected function _addAcceptedType($type, $var = null)
    {
        $types = self::getShortClassname($type);
        $this->_accept = array_unique(array_merge($this->_accept, $types));

        return $this;
    }
}
