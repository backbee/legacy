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

namespace BackBee\Installer\Provider;

use Faker\Provider\Base;

class Lorempixel extends Base
{
    const URL = "http://lorempixel.com/";
    const WITDH = 400;
    const HEIGHT = 200;
    const CATEGORY = 'abstract';

    public function picture($params = array())
    {
        $witdh = array_key_exists('width', $params) ? $params['width'] : static::WITDH;
        $height = array_key_exists('height', $params) ? $params['height'] : static::HEIGHT;
        $category = array_key_exists('category', $params) ? $params['category'] : static::CATEGORY;

        return static::URL.'/'.$witdh.'/'.$height.'/'.$category;
    }
}
