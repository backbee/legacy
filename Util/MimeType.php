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

use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesser;

/**
 * @category    BackBee
 *
 *
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class MimeType
{
    /**
     * The singleton instance.
     *
     * @var MimeTypeGuesser
     */
    private static $instance = null;
    private static $guesser = null;

    private function __construct()
    {
        self::$guesser = MimeTypeGuesser::getInstance();
        self::$guesser->register(new ExtensionMimeTypeGuesser());
    }

    public static function getInstance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @codeCoverageIgnore
     *
     * @param type $path
     *
     * @return type
     */
    public function guess($path)
    {
        return self::$guesser->guess($path);
    }
}
