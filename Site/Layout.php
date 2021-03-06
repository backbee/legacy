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

namespace BackBee\Site;

use BackBee\Exception\InvalidArgumentException;
use BackBee\Security\Acl\Domain\AbstractObjectIdentifiable;
use BackBee\Util\Numeric;
use Doctrine\Common\Collections\ArrayCollection;
use JMS\Serializer\Annotation as Serializer;

use Doctrine\ORM\Mapping as ORM;

/**
 * A website layout.
 *
 * If the layout is not associated to a website, it is proposed as layout template
 * to webmasters
 *
 * The stored data is a serialized standard object. The object must have the
 * following structure :
 *
 * layout: {
 *   templateLayouts: [      // Array of final droppable zones
 *     zone1: {
 *       id:                 // unique identifier of the zone
 *       defaultContainer:   // default AbstractClassContent drop at creation
 *       target:             // array of accepted AbstractClassContent dropable
 *       gridClassPrefix:    // prefix of responsive CSS classes
 *       gridSize:           // size of this zone for responsive CSS
 *     },
 *     ...
 *   ]
 * }
 *
 * @category    BackBee
 *
 *
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 * @ORM\Entity(repositoryClass="BackBee\Site\Repository\LayoutRepository")
 * @ORM\Table(name="layout",indexes={@ORM\Index(name="IDX_SITE", columns={"site_uid"})})
 * @ORM\HasLifecycleCallbacks
 *
 * @Serializer\ExclusionPolicy("all")
 */
class Layout extends AbstractObjectIdentifiable
{
    /**
     * The unique identifier.
     *
     * @var string
     * @ORM\Id
     * @ORM\Column(type="string", length=32, name="uid")
     *
     * @Serializer\Expose
     * @Serializer\Type("string")
     */
    protected $_uid;

    /**
     * The label of this layout.
     *
     * @var string
     * @ORM\Column(type="string", name="label", nullable=false)
     *
     * @Serializer\Expose
     * @Serializer\Type("string")
     */
    protected $_label;

    /**
     * The file name of the layout.
     *
     * @var string
     * @ORM\Column(type="string", name="path", nullable=false)
     *
     * @Serializer\Expose
     * @Serializer\Type("string")
     */
    protected $_path;

    /**
     * The seralized data.
     *
     * @var string
     * @ORM\Column(type="text", name="data", nullable=false)
     */
    protected $_data;

    /**
     * The creation datetime.
     *
     * @var \DateTime
     * @ORM\Column(type="datetime", name="created", nullable=false)
     *
     * @Serializer\Expose
     * @Serializer\Type("DateTime<'U'>")
     */
    protected $_created;

    /**
     * The last modification datetime.
     *
     * @var \DateTime
     * @ORM\Column(type="datetime", name="modified", nullable=false)
     *
     * @Serializer\Expose
     * @Serializer\Type("DateTime<'U'>")
     */
    protected $_modified;

    /**
     * The optional path to the layout icon.
     *
     * @var string
     * @ORM\Column(type="string", name="picpath", nullable=true)
     *
     * @Serializer\Expose
     * @Serializer\Type("string")
     */
    protected $_picpath;

    /**
     * Optional owner site.
     *
     * @var \BackBee\Site\Site
     * @ORM\ManyToOne(targetEntity="BackBee\Site\Site", inversedBy="_layouts", fetch="EXTRA_LAZY")
     * @ORM\JoinColumn(name="site_uid", referencedColumnName="uid")
     */
    protected $_site;

    /**
     * Store pages using this layout.
     * var \Doctrine\Common\Collections\ArrayCollection.
     *
     * @ORM\OneToMany(targetEntity="BackBee\NestedNode\Page", mappedBy="_layout", fetch="EXTRA_LAZY")
     */
    protected $_pages;

    /**
     * Layout states.
     *
     * var \Doctrine\Common\Collections\ArrayCollection
     *
     * @ORM\OneToMany(targetEntity="BackBee\Workflow\State", fetch="EXTRA_LAZY", mappedBy="_layout")
     */
    protected $_states;

    /**
     * The content's parameters.
     *
     * @var array
     * @ORM\Column(type="array", name="parameters", nullable = true)
     *
     * @Serializer\Expose
     * @Serializer\Type("array")
     */
    protected $_parameters = array();

    /**
     * The DOM document corresponding to the data.
     *
     * @var \DOMDocument
     */
    protected $_domdocument;

    /**
     * Is the layout datas are valid ?
     *
     * @var Boolean
     */
    protected $_isValid;

    /**
     * The final DOM zones on layout.
     *
     * @var array
     */
    protected $_zones;

    /**
     * Class constructor.
     *
     * @param string $uid     The unique identifier of the layout
     * @param array  $options Initial options for the layout:
     *                        - label      the default label
     *                        - path       the path to the template file
     */
    public function __construct($uid = null, $options = null)
    {
        $this->_uid = (is_null($uid)) ? md5(uniqid('', true)) : $uid;
        $this->_created = new \DateTime();
        $this->_modified = new \DateTime();

        $this->_pages = new ArrayCollection();

        if (true === is_array($options)) {
            if (true === array_key_exists('label', $options)) {
                $this->setLabel($options['label']);
            }
            if (true === array_key_exists('path', $options)) {
                $this->setPath($options['path']);
            }
        }

        $this->_states = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Returns the unique identifier.
     *
     * @codeCoverageIgnore
     *
     * @return string
     */
    public function getUid()
    {
        return $this->_uid;
    }

    /**
     * Returns the label.
     *
     * @codeCoverageIgnore
     *
     * @return string
     */
    public function getLabel()
    {
        return $this->_label;
    }

    /**
     * Returns the file name of the layout.
     *
     * @codeCoverageIgnore
     *
     * @return string
     */
    public function getPath()
    {
        return $this->_path;
    }

    /**
     * Returns the serialized data of the layout.
     *
     * @codeCoverageIgnore
     *
     * @return string
     */
    public function getData()
    {
        return $this->_data;
    }

    /**
     * Returns the unserialzed object for the layout.
     *
     * @codeCoverageIgnore
     *
     * @return \StdClass
     */
    public function getDataObject()
    {
        return json_decode($this->getData());
    }

    /**
     * Returns the path to the layout icon if defined, NULL otherwise.
     *
     * @codeCoverageIgnore
     *
     * @return string|NULL
     */
    public function getPicPath()
    {
        return $this->_picpath;
    }

    /**
     * Returns the owner site if defined, NULL otherwise.
     *
     * @codeCoverageIgnore
     *
     * @return \BackBee\Site\Site|NULL
     */
    public function getSite()
    {
        return $this->_site;
    }

    /**
     * Return the final zones (ie with contentset) for the layout.
     *
     * @return array|NULL Returns an array of zones or NULL is the layout datas
     *                    are invalid.
     */
    public function getZones()
    {
        if (null === $this->_zones) {
            if (true === $this->isValid()) {
                $this->_zones = array();
                $zonesWithChild = array();

                $zones = $this->getDataObject()->templateLayouts;
                foreach ($zones as $zone) {
                    $zonesWithChild[] = substr($zone->target, 1);
                }

                foreach ($zones as $zone) {
                    if (false === in_array($zone->id, $zonesWithChild)) {
                        if (false === property_exists($zone, 'mainZone')) {
                            $zone->mainZone = false;
                        }

                        if (false === property_exists($zone, 'defaultClassContent')) {
                            $zone->defaultClassContent = null;
                        }

                        $zone->options = $this->getZoneOptions($zone);

                        array_push($this->_zones, $zone);
                    }
                }
            }
        }

        return $this->_zones;
    }

    /**
     * Returns defined parameters.
     *
     * @param string $var The parameter to be return, if NULL, all parameters are returned
     *
     * @return mixed the parameter value or NULL if unfound
     */
    public function getParam($var = null)
    {
        $param = $this->_parameters;
        if (null !== $var) {
            if (isset($this->_parameters[$var])) {
                $param = $this->_parameters[$var];
            } else {
                $param = null;
            }
        }

        return $param;
    }

    /**
     * Goes all over the $param and keep looping until $pieces is empty to return
     * the values user is looking for.
     *
     * @param mixed $param
     * @param array $pieces
     *
     * @return mixed
     */
    private function getRecursivelyParam($param, array $pieces)
    {
        if (0 === count($pieces)) {
            return $param;
        }

        $key = array_shift($pieces);
        if (false === isset($param[$key])) {
            return;
        }

        return $this->getRecursivelyParam($param[$key], $pieces);
    }

    /**
     * Returns the zone at the index $index.
     *
     * @param int $index
     *
     * @return \StdClass|null
     *
     * @throws InvalidArgumentException
     */
    public function getZone($index)
    {
        if (false === Numeric::isPositiveInteger($index, false)) {
            throw new InvalidArgumentException('Invalid integer value.');
        }

        if (null !== $zones = $this->getZones()) {
            if ($index < count($zones)) {
                return $zones[$index];
            }
        }

        return;
    }

    /**
     * Generates and returns a DOM document according to the unserialized data object.
     *
     * @return \DOMDocument|NULL Returns a DOM document or NULL is the layout datas
     *                           are invalid.
     */
    public function getDomDocument()
    {
        if (null === $this->_domdocument) {
            if (true === $this->isValid()) {
                $mainLayoutRow = new \DOMDocument('1.0', 'UTF-8');
                $mainNode = $mainLayoutRow->createElement('div');
                $mainNode->setAttribute('class', 'row');

                $clearNode = $mainLayoutRow->createElement('div');
                $clearNode->setAttribute('class', 'clear');

                $mainId = '';
                $zones = array();
                foreach ($this->getDataObject()->templateLayouts as $zone) {
                    $mainId = $zone->defaultContainer;
                    $class = $zone->gridClassPrefix.$zone->gridSize;

                    if (true === property_exists($zone, 'alphaClass')) {
                        $class .= ' '.$zone->alphaClass;
                    }

                    if (true === property_exists($zone, 'omegaClass')) {
                        $class .= ' '.$zone->omegaClass;
                    }

                    if (true === property_exists($zone, 'typeClass')) {
                        $class .= ' '.$zone->typeClass;
                    }

                    $zoneNode = $mainLayoutRow->createElement('div');
                    $zoneNode->setAttribute('class', trim($class));
                    $zones['#'.$zone->id] = $zoneNode;

                    $parentNode = isset($zones[$zone->target]) ? $zones[$zone->target] : $mainNode;
                    $parentNode->appendChild($zoneNode);
                    if (true === property_exists($zone, 'clearAfter')
                            && 1 == $zone->clearAfter) {
                        $parentNode->appendChild(clone $clearNode);
                    }
                }

                $mainNode->setAttribute('id', substr($mainId, 1));
                $mainLayoutRow->appendChild($mainNode);

                $this->_domdocument = $mainLayoutRow;
            }
        }

        return $this->_domdocument;
    }

    /**
     * Checks for a valid structure of the unserialized data object.
     *
     * @return Boolean Returns TRUE if the data object is valid, FALSE otherwise
     */
    public function isValid()
    {
        if (null === $this->_isValid) {
            $this->_isValid = false;

            if (null !== $data_object = $this->getDataObject()) {
                if (true === property_exists($data_object, 'templateLayouts')
                        && true === is_array($data_object->templateLayouts)
                        && 0 < count($data_object->templateLayouts)) {
                    $this->_isValid = true;

                    foreach ($data_object->templateLayouts as $zone) {
                        if (false === property_exists($zone, 'id')
                                || false === property_exists($zone, 'defaultContainer')
                                || false === property_exists($zone, 'target')
                                || false === property_exists($zone, 'gridClassPrefix')
                                || false === property_exists($zone, 'gridSize')) {
                            $this->_isValid = false;
                            break;
                        }
                    }
                }
            }
        }

        return $this->_isValid;
    }

    /**
     * Sets the label.
     *
     * @codeCoverageIgnore
     *
     * @param string $label
     *
     * @return \BackBee\Site\Layout
     */
    public function setLabel($label)
    {
        $this->_label = $label;

        return $this;
    }

    /**
     * Set the filename of the layout.
     *
     * @codeCoverageIgnore
     *
     * @param string $path
     *
     * @return \BackBee\Site\Layout
     */
    public function setPath($path)
    {
        $this->_path = $path;

        return $this;
    }

    /**
     * Sets the data associated to the layout.
     * No validation checks are performed at this step.
     *
     * @param mixed $data
     *
     * @return \BackBee\Site\Layout
     */
    public function setData($data)
    {
        if (true === is_object($data)) {
            return $this->setDataObject($data);
        }

        $this->_picpath = null;
        $this->_isValid = null;
        $this->_domdocument = null;
        $this->_zones = null;

        $this->_data = $data;

        return $this;
    }

    /**
     * Sets the data associated to the layout.
     * None validity checks are performed at this step.
     *
     * @param mixed $data
     *
     * @return \BackBee\Site\Layout
     */
    public function setDataObject($data)
    {
        if (true === is_object($data)) {
            $data = json_encode($data);
        }

        return $this->setData($data);
    }

    /**
     * Sets the path to the layout icon.
     *
     * @codeCoverageIgnore
     *
     * @param string $picpath
     *
     * @return \BackBee\Site\Layout
     */
    public function setPicPath($picpath)
    {
        $this->_picpath = $picpath;

        return $this;
    }

    /**
     * Associates this layout to a website.
     *
     * @codeCoverageIgnore
     *
     * @param \BackBee\Site\Site $site
     *
     * @return \BackBee\Site\Layout
     */
    public function setSite(Site $site)
    {
        $this->_site = $site;

        return $this;
    }

    /**
     * Sets one or all parameters.
     *
     * @param string $var    the parameter name to set, if NULL all the parameters array wil be set
     * @param mixed  $values the parameter value or all the parameters if $var is NULL
     *
     * @return \BackBee\Site\Layout
     */
    public function setParam($var = null, $values = null)
    {
        if (null === $var) {
            $this->_parameters = $values;
        } else {
            $this->_parameters[$var] = $values;
        }

        return $this;
    }

    /**
     * Returns a contentset options according to the layout zone.
     *
     * @param \StdClass $zone
     *
     * @return array
     */
    private function getZoneOptions(\stdClass $zone)
    {
        $options = array(
            'parameters' => array(
                'class' => array(
                    'type' => 'scalar',
                    'options' => array('default' => 'row'),
                ),
            ),
        );

        if (true === property_exists($zone, 'accept')
                && true === is_array($zone->accept)
                && 0 < count($zone->accept)
                && $zone->accept[0] != '') {
            $options['accept'] = $zone->accept;

            $func = function (&$item, $key) {
                        $item = ('' == $item) ? null : 'BackBee\ClassContent\\'.$item;
                    };

            array_walk($options['accept'], $func);
        }

        if (true === property_exists($zone, 'maxentry') && 0 < $zone->maxentry) {
            $options['maxentry'] = $zone->maxentry;
        }

        return $options;
    }

    /**
     * @Serializer\VirtualProperty
     * @Serializer\SerializedName("site_uid")
     */
    public function getSiteUid()
    {
        return null !== $this->_site ? $this->_site->getUid() : null;
    }

    /**
     * @Serializer\VirtualProperty
     * @Serializer\SerializedName("site_label")
     */
    public function getSiteLabel()
    {
        return null !== $this->_site ? $this->_site->getLabel() : null;
    }

    /**
     * @Serializer\VirtualProperty
     * @Serializer\SerializedName("data")
     */
    public function virtualGetData()
    {
        return json_decode($this->getData(), true);
    }

    /**
     * Add state.
     *
     * @param \BackBee\Workflow\State $state
     *
     * @return \BackBee\Site\Layout
     */
    public function addState(\BackBee\Workflow\State $state)
    {
        $this->_states[] = $state;

        return $this;
    }
    /**
     * Remove state.
     *
     * @param \BackBee\Workflow\State $state
     */
    public function removeState(\BackBee\Workflow\State $state)
    {
        $this->_states->removeElement($state);
    }
    /**
     * Get states.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getStates()
    {
        return $this->_states;
    }

    /**
     * @Serializer\VirtualProperty
     * @Serializer\SerializedName("workflow_states")
     */
    public function getWokflowStates()
    {
        $workflowStates = array(
            'online'  => array(),
            'offline' => array(),
        );

        foreach ($this->getStates() as $state) {
            if (0 < $code = $state->getCode()) {
                $workflowStates['online'][$code] = array(
                    'label' => $state->getLabel(),
                    'code'  => '1_'.$code,
                );
            } else {
                $workflowStates['offline'][$code] = array(
                    'label' => $state->getLabel(),
                    'code'  => '0_'.$code,
                );
            }
        }

        $workflowStates = array_merge(
            array('0' => array('label' => 'Hors ligne', 'code' => '0')),
            $workflowStates['offline'],
            array('1' => array('label' => 'En ligne', 'code' => '1')),
            $workflowStates['online']
        );

        return $workflowStates;
    }

    /**
     * @Serializer\VirtualProperty
     * @Serializer\SerializedName("is_final")
     */
    public function isFinal()
    {
        return (bool) $this->getParam('is_final');
    }
}
