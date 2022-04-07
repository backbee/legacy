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

namespace BackBee\Util;

use BackBee\ClassContent\Element\File as ElementFile;
use BackBee\Exception\InvalidArgumentException;
use BackBee\Util\File\File;

/**
 * Set of utility methods to deal with media files.
 *
 * @category    BackBee
 *
 * 
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class Media
{
    /**
     * Returns the computed storage filename of an element file.
     *
     * @param \BackBee\ClassContent\Element\File $content
     * @param int                                $folder_size Optional, size in characters of the storing folder
     *
     * @return string
     *
     * @throws \BackBee\Exception\InvalidArgumentException Occurs if the provided element file is empty
     */
    public static function getPathFromContent(ElementFile $content, $folder_size = 3)
    {
        if (null === $content->getUid()
                || null === $content->originalname) {
            throw new InvalidArgumentException('Enable to compute path, the provided element file is not yet initialized');
        }

        $folder = '';
        $filename = $content->getUid();
        if (null !== $draft = $content->getDraft()) {
            $filename = $draft->getUid();
        }

        if (0 < $folder_size && strlen($filename) > $folder_size) {
            $folder = substr($filename, 0, $folder_size).'/';
            $filename = substr($filename, $folder_size);
        }

        $extension = File::getExtension($content->originalname, true);

        return $folder.$filename.$extension;
    }

    /**
     * Returns the computed storage filename base on an uid.
     *
     * @param string  $uid
     * @param string  $originalname
     * @param int     $folder_size
     * @param boolean $include_originalname
     *
     * @return string
     *
     * @throws \BackBee\Exception\InvalidArgumentException Occurs if the provided $uid is invalid
     */
    public static function getPathFromUid($uid, $originalname, $folder_size = 3, $include_originalname = false)
    {
        if (false === is_string($uid) || true === empty($uid)) {
            throw new InvalidArgumentException('Enable to compute path, the provided uid is not a valid string');
        }

        $folder = '';
        $filename = $uid;
        if (0 < $folder_size && strlen($uid) > $folder_size) {
            $folder = substr($uid, 0, $folder_size).DIRECTORY_SEPARATOR;
            $filename = substr($uid, $folder_size);
        }

        if (true === $include_originalname) {
            $filename .= DIRECTORY_SEPARATOR.$include_originalname;
        } else {
            $extension = File::getExtension($originalname, true);
            $filename .= $extension;
        }

        return $folder.$filename;
    }

    /**
     * Resizes an image and saves it to the provided file path.
     *
     * @param string $source The filepath of the source image
     * @param string $dest   The filepath of the target image
     * @param int    $width
     * @param int    $height
     *
     * @return boolean Returns TRUE on success, FALSE on failure
     *
     * @throws \BackBee\Exception\BBException Occures if gd extension is not loaded
     * @throws InvalidArgumentException       Occures on unsupported file type or unreadable file source
     */
    public static function resize($source, $dest, $width, $height)
    {
        if (false === extension_loaded('gd')) {
            throw new \BackBee\Exception\BBException('gd extension is required');
        }

        if (false === is_readable($source)) {
            throw new InvalidArgumentException('Enable to read source file');
        }

        if (false === $size = getimagesize($source)) {
            throw new InvalidArgumentException('Unsupported picture type');
        }

        $source_width = $size[0];
        $source_height = $size[1];
        $mime_type = MimeType::getInstance()->guess($source);

        switch ($mime_type) {
            case 'image/jpeg':
                $source_img = imagecreatefromjpeg($source);
                break;
            case 'image/png':
                $source_img = imagecreatefrompng($source);
                break;
            case 'image/gif':
                $source_img = imagecreatefromgif($source);
                break;
            default:
                throw new InvalidArgumentException('Unsupported picture type');
        }

        if ($source_width < $width && $source_height < $height) {
            // Picture to small, no resize
            return @copy($source, $dest);
        }

        $ratio = min($width / $source_width, $height / $source_height);
        $width = $source_width * $ratio;
        $height = $source_height * $ratio;

        $target_img = imagecreatetruecolor($width, $height);

        if ('image/jpeg' !== $mime_type) {
            // Preserve alpha
            imagecolortransparent($target_img, imagecolorallocatealpha($target_img, 0, 0, 0, 127));
            imagealphablending($target_img, false);
            imagesavealpha($target_img, true);
        }

        imagecopyresampled($target_img, $source_img, 0, 0, 0, 0, $width, $height, $source_width, $source_height);

        switch ($mime_type) {
            case 'image/jpeg':
                return imagejpeg($target_img, $dest);
                break;
            case 'image/png':
                return @imagepng($target_img, $dest);
                break;
            case 'image/gif':
                return @imagegif($target_img, $dest);
                break;
        }
    }
}
