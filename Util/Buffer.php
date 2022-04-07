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

/**
 * @category    BackBee
 *
 *
 * @author      n.dufreche <nicolas.dufreche@lp-digital.fr>
 */
class Buffer
{
    private static $cli_colours_foreground = array(
        'black' => '0;30',
        'dark_gray' => '1;30',
        'red' => '0;31',
        'bold_red' => '1;31',
        'green' => '0;32',
        'bold_green' => '1;32',
        'brown' => '0;33',
        'yellow' => '1;33',
        'blue' => '0;34',
        'bold_blue' => '1;34',
        'purple' => '0;35',
        'bold_purple' => '1;35',
        'cyan' => '0;36',
        'bold_cyan' => '1;36',
        'white' => '1;37',
        'bold_gray' => '0;37',
       );

    public static function flush()
    {
        if (!ob_get_contents()) {
            ob_start();
        }
        ob_end_flush();
        flush();
        ob_start();
    }

    /**
     * pring the string value directly.
     *
     * @param string $string
     * @codeCoverageIgnore
     */
    public static function dump($string, $color = null, $debug = true)
    {
        if ($debug) {
            print self::formatOutput($string, $color);
            static::flush();
        }
    }

    private static function formatOutput($string, $color = null)
    {
        if ("cli" === php_sapi_name()) {
            if (isset(self::$cli_colours_foreground[$color])) {
                $string = "\033[".self::$cli_colours_foreground[$color].'m'.$string."\033[0m";
            }
        }

        return $string;
    }
}
