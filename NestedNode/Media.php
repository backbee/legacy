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

namespace BackBee\NestedNode;

use BackBee\ClassContent\AbstractClassContent;

use Doctrine\ORM\Mapping as ORM;

/**
 * Media entity in BackBee.
 *
 * @category    BackBee
 *
 *
 * @author      m.baptista <michel.baptista@lp-digital.fr>
 * @ORM\Entity(repositoryClass="BackBee\NestedNode\Repository\MediaRepository")
 * @ORM\Table(name="media")
 * @ORM\HasLifecycleCallbacks
 */
class Media implements \JsonSerializable
{

    /**
     * Unique identifier of the media.
     *
     * @var integer
     *
     * @ORM\Id
     * @ORM\Column(type="integer", name="id")
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $_id;

    /**
     * The media folder owning this media.
     *
     * @var MediaFolder
     *
     * @ORM\ManyToOne(targetEntity="BackBee\NestedNode\MediaFolder", inversedBy="_medias", cascade={"persist"}, fetch="EXTRA_LAZY")
     * @ORM\JoinColumn(name="media_folder_uid", referencedColumnName="uid")
     */
    protected $_media_folder;

    /**
     * The element content of this media.
     *
     * @var AbstractClassContent
     *
     * @ORM\ManyToOne(targetEntity="BackBee\ClassContent\AbstractClassContent", cascade={"persist"}, fetch="EXTRA_LAZY")
     * @ORM\JoinColumn(name="content_uid", referencedColumnName="uid")
     */
    protected $_content;

    /**
     * The title of this media.
     *
     * @var string
     *
     * @ORM\Column(type="string", name="title")
     */
    protected $_title;

    /**
     * The publication datetime.
     *
     * @var \DateTime
     *
     * @ORM\Column(type="datetime", name="date")
     */
    protected $_date;

    /**
     * The creation datetime.
     *
     * @var \DateTime
     *
     * @ORM\Column(type="datetime", name="created")
     */
    protected $_created;

    /**
     * The last modification datetime.
     *
     * @var \DateTime
     *
     * @ORM\Column(type="datetime", name="modified")
     */
    protected $_modified;

    /**
     * Class constructor of a media entity.
     *
     * @param string    $title optional, the title of the new media, 'Untitled media' by default
     * @param \DateTime $date  optional, the publication date, current time by default
     */
    public function __construct($title = null, $date = null)
    {
        $this->_title = (is_null($title)) ? 'Untitled media' : $title;
        $this->_date = (is_null($date)) ? new \DateTime() : $date;

        $this->_created = new \DateTime();
        $this->_modified = new \DateTime();
    }

    /**
     * @param  \BackBee\ClassContent\AbstractClassContent $content
     * @return string
     *
     * @deprecated since version 0.10.0
     */
    public static function getAbsolutePath($content = null)
    {
        return __DIR__ . '/../../repository/' . Media::getUploadDir();
    }

    /**
     * @param  \BackBee\ClassContent\AbstractClassContent $content
     * @return string
     *
     * @deprecated since version 0.10.0
     */
    public static function getWebPath($content = null)
    {
        return '/images/';
    }

    /**
     * @return string
     *
     * @deprecated since version 0.10.0
     */
    public static function getUploadTmpDir()
    {
        return __DIR__ . '/../../repository/Data/Tmp/';
    }

    /**
     * @return string
     *
     * @deprecated since version 0.10.0
     */
    protected static function getUploadDir()
    {
        return 'Data/Media/';
    }

    /**
     * Sets the media folder.
     *
     * @param \BackBee\NestedNode\MediaFolder $media_folder
     *
     * @return \BackBee\NestedNode\Media
     */
    public function setMediaFolder(MediaFolder $media_folder)
    {
        $this->_media_folder = $media_folder;

        return $this;
    }

    /**
     * Sets the element content to the media.
     *
     * @param  \BackBee\ClassContent\AbstractClassContent $content
     * @return \BackBee\NestedNode\Media
     */
    public function setContent(AbstractClassContent $content)
    {
        $this->_content = $content;

        return $this;
    }

    /**
     * Sets the title.
     *
     * @param string $title
     *
     * @return \BackBee\NestedNode\Media
     */
    public function setTitle($title)
    {
        $this->_title = $title;

        return $this;
    }

    /**
     * Sets the publication date.
     *
     * @param \DateTime $date
     *
     * @return \BackBee\NestedNode\Media
     */
    public function setDate(\DateTime $date)
    {
        $this->_date = $date;

        return $this;
    }

    /**
     * Sets the created date.
     *
     * @param \DateTime $created
     *
     * @return \BackBee\NestedNode\Media
     */
    public function setCreated(\DateTime $created)
    {
        $this->_created = $created;

        return $this;
    }

    /**
     * Sets the last modified date.
     *
     * @param \DateTime $modified
     *
     * @return \BackBee\NestedNode\Media
     */
    public function setModified(\DateTime $modified)
    {
        $this->_modified = $modified;

        return $this;
    }

    /**
     * Returns the unique identifier.
     *
     * @return integer
     * @codeCoverageIgnore
     */
    public function getId()
    {
        return $this->_id;
    }

    /**
     * Gets the title.
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->_title;
    }

    /**
     * Gets the publication date.
     *
     * @return \DateTime
     */
    public function getDate()
    {
        return $this->_date;
    }

    /**
     * Gets the created date.
     *
     * @return \DateTime
     */
    public function getCreated()
    {
        return $this->_created;
    }

    /**
     * Gets the last mofified date.
     *
     * @return \DateTime
     */
    public function getModified()
    {
        return $this->_modified;
    }

    /**
     * Returns the media folder owning the media.
     *
     * @return \BackBee\NestedNode\MediaFolder
     */
    public function getMediaFolder()
    {
        return $this->_media_folder;
    }

    /**
     * Returns the element content of the media.
     *
     * @return \BackBee\ClassContent\AbstractClassContent
     */
    public function getContent()
    {
        return $this->_content;
    }

    public function jsonSerialize()
    {
        $result = [];
        $result['id'] = $this->getId();
        $result['image'] = $this->getContent() ? $this->getContent()->getImageName() : null;
        $result['media_folder'] = $this->getMediaFolder()->getUid();
        $result['title'] = $this->getTitle();

        $contentData = $this->getContent() ? $this->getContent()->jsonSerialize() : [];

        $result ['content'] = [
            'uid'   => $this->getContent()->getUid(),
            'type'  => $this->getContent()->getContentType(),
            'extra' => isset($contentData['extra']) ? $contentData['extra'] : [],
        ];

        return $result;
    }

}
