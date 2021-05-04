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

namespace BackBee\Site\Fixture;

use Faker\Factory;
use BackBee\Installer\Annotation as BB;
use BackBee\Site\Site;

/**
 * @BB\Fixture
 */
class SiteFixture extends Site
{
    protected $faker;

    public function __construct($local = Factory::DEFAULT_LOCALE)
    {
        $this->faker = Factory::create($local);
    }

    public function setUp()
    {
        $site = new Site();
        $site->_label = $this->faker->domainWord;
        $site->_server_name = $this->faker->domainName;
        $site->_created = $site->_modified = new \DateTime();

        return $site;
    }
}
