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

namespace BackBee\ClassContent\Exception;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class InvalidContentTypeException extends \InvalidArgumentException
{
    /**
     * {@inheritdoc}
     */
    public function __construct($contentType, $code = 0, \Exception $previous = null)
    {
        parent::__construct(sprintf('`%s` is not a valid content type.', $contentType), $code, $previous);
    }
}
