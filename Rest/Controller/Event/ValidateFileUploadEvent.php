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

namespace BackBee\Rest\Controller\Event;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use BackBee\Event\Event;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ValidateFileUploadEvent extends Event
{
    const EVENT_NAME = 'file_upload.validation';

    protected $filepath;

    /**
     * @param string $filepath
     */
    public function __construct($filepath)
    {
        $this->filepath = (string) $filepath;

        parent::__construct($this->filepath);
    }

    /**
     * Returns the path of the file to validate.
     *
     * @return string
     */
    public function getFilepath()
    {
        return $this->filepath;
    }

    /**
     * Invalidates the provided file by throwing an exception.
     *
     * @param  string $message
     * @throws BadRequestHttpException
     */
    public function invalidateFile($message)
    {
        unlink($this->filepath);

        throw new BadRequestHttpException($message);
    }
}
