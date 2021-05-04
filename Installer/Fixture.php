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

use Doctrine\Common\Annotations\SimpleAnnotationReader;
use Doctrine\ORM\Mapping\ClassMetadata;
use BackBee\BBApplication;
use BackBee\Installer\Annotation\Fixture as AnnotationFixture;
use BackBee\Installer\Annotation\Fixtures as AnnotationFixtures;

class Fixture
{
    private $_application;
    private $_cacheDir;
    private $_annotationReader;

    public function __construct(BBApplication $application)
    {
        $this->_application = $application;
        $this->_cacheDir = $this->_application->getCacheDir().DIRECTORY_SEPARATOR.'Fixtures';
        if (!file_exists($this->_cacheDir)) {
            mkdir($this->_cacheDir);
        }
        $this->_entityFinder = new EntityFinder($this->_application->getBaseDir());
    }

    public function populate()
    {
        $classes = array();
        foreach ($this->_entityFinder->getEntities($this->_application->getBBDir()) as $className) {
            $classes[] = $this->_application->getEntityManager()->getClassMetadata($className);
        }
        foreach ($classes as $classMetadata) {
            if ($this->isFixturedEntity($classMetadata->getReflectionClass())) {
                $this->getClass($classMetadata);
            }
        }
    }

    public function populateClass(ClassMetadata $classMetadata)
    {
        if (!$this->isFixturedEntity($classMetadata->getReflectionClass())) {
            return;
        }
        $class = $this->getClass($classMetadata);
        for ($i = 0; $i < $this->getClassAnnotation($classMetadata->getReflectionClass())->qty; $i++) {
            $entity = $class->setUp();
            $this->_application->getEntityManager()->persist($entity);
            $this->_application->getEntityManager()->flush();
        }
    }

    public function getClass(ClassMetadata $classMetadata)
    {
        if (
            !file_exists($this->_cacheDir.str_replace('\\', '_', $classMetadata->getName()))
        ) {
            $this->createProxyFile($classMetadata);
        }
        include_once $this->_cacheDir.DIRECTORY_SEPARATOR.str_replace('\\', '_', $classMetadata->getName());
        $className = $classMetadata->getName().'\\Fixture';

        return new $className();
    }

    private function createProxyFile(ClassMetadata $classMetadata)
    {
        $fileName = str_replace('\\', '_', $classMetadata->getName());
        $reflection = $classMetadata->getReflectionClass();
        $filecontent = $this->getFileContent($classMetadata->getName());

        foreach ($reflection->getProperties() as $property) {
            /* @var $property \ReflectionProperty */
            $annotation = $this->getPropertyAnnotation($property);
            if (!is_null($annotation)) {
                $filecontent .= '        $obj->'.$property->getName().' = '.$annotation->getFixture()."\n";
            }
        }
        foreach ($classMetadata->getAssociationMappings() as $association) {
            $filecontent .= '        $obj->'.$association['fieldName'].' = array_key_exists("'.$association['fieldName'].'", $dpdc) ? $dpdc["'.$association['fieldName'].'"] : null;'."\n";
        }
        $filecontent .= '        return $obj;'."\n".'    }'."\n".'}';
        file_put_contents($this->_cacheDir.DIRECTORY_SEPARATOR.$fileName, $filecontent);
    }

    private function getFileContent($className)
    {
        $filecontent = '<?php'."\n";
        $filecontent .= 'namespace '.$className.";\n\n";
        $filecontent .= 'class Fixture extends \\'.$className."\n";
        $filecontent .= '{'."\n";
        $filecontent .= '    private $faker;'."\n\n";
        $filecontent .= '    public function __construct(){'."\n";
        $filecontent .= '        $this->faker = \Faker\Factory::create();'."\n";
        $filecontent .= '    }'."\n\n";
        $filecontent .= '    public function setUp(array $dpdc = array()) {'."\n";
        $filecontent .= '        $obj = new \\'.$className.'();'."\n";

        return $filecontent;
    }

    private function isFixturedEntity(\ReflectionClass $class)
    {
        return !is_null($this->getClassAnnotation($class));
    }

    /**
     * @param \ReflectionProperty $property
     *
     * @return AnnotationFixture or null
     */
    private function getPropertyAnnotation(\ReflectionProperty $property)
    {
        return $this->getAnnotationReader()->getPropertyAnnotation($property, new AnnotationFixture());
    }

    /**
     * @param \ReflectionProperty $property
     *
     * @return AnnotationFixture or null
     */
    private function getClassAnnotation(\ReflectionClass $class)
    {
        return $this->getAnnotationReader()->getClassAnnotation($class, new AnnotationFixtures());
    }

    /**
     * @return SimpleAnnotationReader
     */
    private function getAnnotationReader()
    {
        if (!$this->_annotationReader) {
            $this->_annotationReader = new SimpleAnnotationReader();
            $this->_annotationReader->addNamespace('BackBee\Installer\Annotation');
        }

        return $this->_annotationReader;
    }
}
