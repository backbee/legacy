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

namespace BackBee\Util\Resolver;

/**
 * This config directory resolver allows to get every folders in which we can find bootstrap.yml
 * file. It's ordered by the most specific (context + envionment) to the most global.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      e.chau <eric.chau@lp-digital.fr>
 */
class ConfigDirectory
{
    /**
     * Returns ordered directory (from specific to global) which can contains the bootstrap.yml file
     * according to context and environment.
     *
     * @return array which contains every directory (string) where we can find the bootstrap.yml
     */
    public static function getDirectories($bb_directory = null, $repository_directory, $context, $environment)
    {
        $directories = BootstrapDirectory::getDirectories($repository_directory, $context, $environment);
        if (null !== $bb_directory) {
            array_push($directories, $bb_directory.DIRECTORY_SEPARATOR.'Config');
        }

        $directories = array_reverse($directories);

        return $directories;
    }
}
