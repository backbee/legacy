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

namespace BackBee\Util\Transport;

use BackBee\Util\Transport\Exception\MisconfigurationException;

/**
 * Factory to get a transport instance
 * The array configuration should have the following structure:
 * <code>
 *    transport: <<Transport classname>>
 *    host: {<<Transport host>>}
 *    remotepath: {<<Transport remote path>>}
 *    ...
 * </code>.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class TransportFactory
{
    /**
     * Creates a new AbstractTransport instance.
     *
     * @param array $config An array containing the key 'transport' with the classname to use
     *                      and optional options depending on the transport to start
     *
     * @return \BackBee\Util\Transport\AbstractTransport
     *
     * @throws MisconfigurationException occures if $config is not valid
     */
    public static function create(array $config)
    {
        if (false === array_key_exists('transport', $config)) {
            throw new MisconfigurationException(sprintf('Can not create Transport : missing classname.'));
        }

        $classname = $config['transport'];
        if (false === class_exists($classname)) {
            throw new MisconfigurationException(sprintf('Can not create Transport : unknown classname %s.', $classname));
        }

        return new $classname($config);
    }
}
