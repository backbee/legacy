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

namespace BackBee\Installer;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\Mapping\Entity;

class EntityFinder
{
    /**
     * @var array
     */
    private $ignoredFolder = [
        '/Resources/',
        '/Ressources/',
        '/Test/',
        '/Tests/',
        '/Mock/',
        '/TestUnit/',
        '/Exception/',
        '/Console/',
        '/Command/',
        '/Installer/',
        '/Assets/',
        '/Renderer/',
        '/Templates/',
        '/helpers/',
    ];

    /**
     * @var SimpleAnnotationReader
     */
    private $annotationReader;

    /**
     * @param string $path
     *
     * @return array
     */
    public function getEntities($path)
    {
        $entities = [];

        $Directory = new \RecursiveDirectoryIterator($path);
        $Iterator = new \RecursiveIteratorIterator($Directory);
        $objects = new \RegexIterator($Iterator, '/.*(.php)$/', \RecursiveRegexIterator::GET_MATCH);

        foreach ($objects as $filename => $object) {
            $file = explode('/', $filename);
            if (!preg_filter($this->ignoredFolder, [], $file)) {
                $classname = $this->getNamespace($filename) . NAMESPACE_SEPARATOR . substr(basename($filename), 0, -4);
                if ($this->isValidNamespace($classname)) {
                    $entities[] = $classname;
                }
            }
        }

        return $entities;
    }

    /**
     * Get the paths that should be excluded.
     *
     * @param string $path
     *
     * @return array
     */
    public function getExcludePaths($path)
    {
        $excludePaths = [];

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
        $objects = new \RegexIterator($iterator, '/.*(.php)$/', \RecursiveRegexIterator::GET_MATCH);

        foreach ($objects as $filename => $object) {
            if (preg_filter($this->ignoredFolder, [], $filename)) {
                $excludePaths[] = dirname($filename);
            }
        }

        $excludePaths = array_unique($excludePaths);

        return $excludePaths;
    }

    /**
     * @param string $folder
     *
     * @return self
     */
    public function addIgnoredFolder($folder)
    {
        $this->ignoredFolder[] = $folder;

        return $this;
    }

    /**
     * @param string $file
     *
     * @return string
     */
    public function getNamespace($file)
    {
        $src = file_get_contents($file);
        $offset = strpos($src, 'namespace');
        $src = substr($src, 0, strpos($src, ';', $offset)) . ';';
        $tokens = token_get_all($src);
        $count = count($tokens);
        $namespace = '';

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if (is_array($token) && $token[0] === T_NAMESPACE) {
                while (++$i < $count) {
                    if ($tokens[$i] === ';') {
                        $namespace = trim($namespace);
                        break;
                    }

                    $namespace .= is_array($tokens[$i]) ? $tokens[$i][1] : $tokens[$i];
                }

                break;
            }
        }

        return $namespace;
    }

    /**
     * @param string $namespace
     *
     * @return bool
     */
    private function isValidNamespace($namespace)
    {
        return class_exists($namespace) && $this->isEntity(new \ReflectionClass($namespace));
    }

    /**
     * @param \ReflectionClass $reflection
     *
     * @return boolean
     */
    private function isEntity(\ReflectionClass $reflection)
    {
        return null !== $this->getEntityAnnotation($reflection);
    }

    /**
     * @param \ReflectionClass $class
     *
     * @return Entity
     */
    private function getEntityAnnotation(\ReflectionClass $class)
    {
        if (!$this->annotationReader) {
            $this->annotationReader = new AnnotationReader();
        }

        return $this->annotationReader->getClassAnnotation($class, new Entity());
    }
}
