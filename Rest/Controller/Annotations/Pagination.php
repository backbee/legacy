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

namespace BackBee\Rest\Controller\Annotations;

/**
 * Pagination properties annotation.
 *
 * @Annotation
 *
 * @category    BackBee
 *
 *
 * @author      k.golovin
 */
class Pagination
{
    /**
     * @var integer
     */
    public $default_start = 0;

    /**
     * @var integer
     */
    public $default_count = 100;

    /**
     * @var integer
     */
    public $max_count = 1000;

    /**
     * @var integer
     */
    public $min_count = 1;
}
