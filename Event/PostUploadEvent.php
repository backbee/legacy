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

namespace BackBee\Event;

/**
 * A event dispatch after a file was uploaded in BB application.
 *
 * @category    BackBee
 *
 * 
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class PostUploadEvent extends Event
{
    /**
     * Class constructor.
     *
     * @param string $source_file Path to the uploaded file
     * @param string $target_file Target path to copy uploaded file
     */
    public function __construct($source_file, $target_file = null)
    {
        parent::__construct($source_file, $target_file);
    }

    /**
     * Returns the path to the uploaded file.
     *
     * @return string
     */
    public function getSourceFile()
    {
        return $this->target;
    }

    /**
     * Returns the target path if exists, NULL otherwise.
     *
     * @return string|NULL
     */
    public function getTargetFile()
    {
        return $this->args;
    }

    /**
     * Is the source file is a valid readable file ?
     *
     * @return boolean
     */
    public function hasValidSourceFile()
    {
        $sourcefile = $this->getSourceFile();

        return (true === is_readable($sourcefile) && false === is_dir($sourcefile));
    }
}
