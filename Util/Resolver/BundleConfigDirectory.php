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

namespace BackBee\Util\Resolver;

/**
 * This bootstrap directory resolver allows to get every folders in which we can find bootstrap.yml
 * file. It's ordered by the most specific (context + envionment) to the most global.
 *
 * @category    BackBee
 *
 * 
 * @author      e.chau <eric.chau@lp-digital.fr>
 */
class BundleConfigDirectory
{
    const OVERRIDE_BUNDLE_CONFIG_DIRECTORY_NAME = 'bundle';

    /**
     * Returns ordered directory (from global to specific) which can contains the bundle config files
     * according to context and environment.
     *
     * @return array which contains every directory (string) where we can find the bundle config files
     */
    public static function getDirectories($base_directory, $context, $environment, $bundle_id)
    {
        $directories = array();
        foreach (BootstrapDirectory::getDirectories($base_directory, $context, $environment) as $directory) {
            $directory .= DIRECTORY_SEPARATOR.self::OVERRIDE_BUNDLE_CONFIG_DIRECTORY_NAME
                .DIRECTORY_SEPARATOR.$bundle_id
            ;

            if (true === is_dir($directory)) {
                array_unshift($directories, $directory);
            }
        }

        return $directories;
    }
}
