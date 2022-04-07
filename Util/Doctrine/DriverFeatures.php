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

namespace BackBee\Util\Doctrine;

use Doctrine\DBAL\Driver;

/**
 * Utility class to know supported features by the current driver.
 *
 * @category    BackBee
 *
 *
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class DriverFeatures
{
    /*
     * Drivers array supporting REPLACE command
     * @var array
     */

    static private $replaceDrivers = array(
        'Doctrine\DBAL\Driver\PDOMySql\Driver',
        'Doctrine\DBAL\Driver\Mysqli\Driver',
        'Doctrine\DBAL\Driver\PDOSqlite\Driver',
    );

    /**
     * Drivers array supporting multi valuated insertions
     * @var array
     */
    static private $multiValuesDrivers = array(
        'Doctrine\DBAL\Driver\PDOMySql\Driver',
        'Doctrine\DBAL\Driver\Mysqli\Driver',
    );

    /**
     * Returns TRUE if the driver support REPLACE comand.
     *
     * @param \Doctrine\DBAL\Driver $driver
     *
     * @return boolean
     */
    public static function replaceSupported(Driver $driver)
    {
        return in_array(get_class($driver), self::$replaceDrivers);
    }

    /**
     * Returns TRUE if the driver support multi valuated nsertions comand
     * @param  \Doctrine\DBAL\Driver $driver
     * @return boolean
     */
    public static function multiValuesSupported(Driver $driver)
    {
        return in_array(get_class($driver), self::$multiValuesDrivers);
    }
}
