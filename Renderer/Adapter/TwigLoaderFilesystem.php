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

namespace BackBee\Renderer\Adapter;

/**
 * Extends twig default filesystem loader to override some behaviors.
 *
 * @category    BackBee
 *
 *
 * @author      e.chau <eric.chau@lp-digital.fr>
 */
class TwigLoaderFilesystem extends \Twig_Loader_Filesystem
{
    /**
     * Do same stuff than Twig_Loader_Filesystem::exists() plus check if the file is
     * readable.
     *
     * @see  Twig_Loader_Filesystem::exists()
     */
    public function exists($name)
    {
        $name = preg_replace('#/{2,}#', '/', strtr($name, '\\', '/'));
        $exists = parent::exists($name);
        $readable = false;
        if (true === $exists) {
            $readable = is_readable($this->cache[$name]);
        }

        return $readable;
    }

    public function removeAllPaths()
    {
        $this->paths = [];
        $this->cache = [];
    }

    /**
     * Do same stuff than Twig_Loader_Filesystem::exists() plus returns the file
     * itself if it is readable.
     *
     * @see  Twig_Loader_Filesystem::findTemplate()
     */
    protected function findTemplate($name)
    {
        try {
            return parent::findTemplate($name);
        } catch (\Twig_Error_Loader $e) {
            $namespace = self::MAIN_NAMESPACE;

            if (is_readable($name)) {
                return $name;
            }

            throw new \Twig_Error_Loader(sprintf(
                'Unable to find template "%s" (looked into: %s).',
                $name,
                implode(', ', $this->paths[$namespace])
            ));
        }
    }
}
