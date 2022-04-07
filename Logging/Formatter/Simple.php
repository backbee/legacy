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

namespace BackBee\Logging\Formatter;

/**
 * @category    BackBee
 *
 *
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class Simple implements FormatterInterface
{
    private $_format = '%d %p [%u]: %m';

    public function __construct($format = null)
    {
        if (null !== $format) {
            $this->_format = $format;
        }
    }

    public function format($event)
    {
        $output = $this->_format.PHP_EOL;

        foreach ($event as $key => $value) {
            $output = str_replace('%'.$key, $value, $output);
        }

        return $output;
    }
}
