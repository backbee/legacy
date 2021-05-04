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

namespace BackBee\Util\Doctrine;

use Doctrine\ORM\EntityManager;
use Symfony\Component\Security\Core\Util\ClassUtils;

use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\Revision;

/**
 * Utility class to deal with managed Doctrine entities.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class ScheduledEntities
{
    /**
     * Returns an array of scheduled entities by classname for insertions.
     *
     * @param \Doctrine\ORM\EntityManager $em
     * @param string|array Classnames
     *
     * @return array
     */
    public static function getScheduledEntityInsertionsByClassname(EntityManager $em, $classnames)
    {
        $entities = array();
        $classnames = (array) $classnames;

        foreach ($em->getUnitOfWork()->getScheduledEntityInsertions() as $entity) {
            if (true === in_array(ClassUtils::getRealClass($entity), $classnames)) {
                $entities[] = $entity;
            }
        }

        return $entities;
    }

    /**
     * Returns an array of scheduled entities by classname for updates.
     *
     * @param \Doctrine\ORM\EntityManager $em
     * @param string|array Classnames
     *
     * @return array
     */
    public static function getScheduledEntityUpdatesByClassname(EntityManager $em, $classnames)
    {
        $entities = array();
        $classnames = (array) $classnames;

        foreach ($em->getUnitOfWork()->getScheduledEntityUpdates() as $entity) {
            if (true === in_array(ClassUtils::getRealClass($entity), $classnames)) {
                $entities[] = $entity;
            }
        }

        return $entities;
    }

    /**
     * Returns an array of scheduled entities by classname for deletions.
     *
     * @param \Doctrine\ORM\EntityManager $em
     * @param string|array Classnames
     *
     * @return array
     */
    public static function getScheduledEntityDeletionsByClassname(EntityManager $em, $classnames)
    {
        $entities = array();
        $classnames = (array) $classnames;

        foreach ($em->getUnitOfWork()->getScheduledEntityDeletions() as $entity) {
            if (true === in_array(ClassUtils::getRealClass($entity), $classnames)) {
                $entities[] = $entity;
            }
        }

        return $entities;
    }

    /**
     * Returns an array of scheduled entities by classname for insertions, updates or deletions.
     *
     * @param \Doctrine\ORM\EntityManager $em
     * @param string|array Classnames
     *
     * @return array
     */
    public static function getScheduledEntityByClassname(EntityManager $em, $classnames)
    {
        return array_merge(
                self::getScheduledEntityInsertionsByClassname($em, $classnames),
                self::getScheduledEntityUpdatesByClassname($em, $classnames),
                self::getScheduledEntityDeletionsByClassname($em, $classnames)
        );
    }

    /**
     * Returns an array of AbstractClassContent scheduled for insertions.
     *
     * @param \Doctrine\ORM\EntityManager $em
     * @param boolean                     $with_revision Includes AClassContent which has scheduled revision
     *
     * @return array
     */
    public static function getScheduledAClassContentInsertions(EntityManager $em, $with_revision = false, $exclude_base_element = false)
    {
        $entities = array();
        foreach ($em->getUnitOfWork()->getScheduledEntityInsertions() as $entity) {
            if (false !== $tmp = self::getScheduledEntity($entity, $with_revision, $exclude_base_element)) {
                $entities[] = $tmp;
            }
        }

        return $entities;
    }

    /**
     * Returns an array of AbstractClassContent scheduled for updates.
     *
     * @param \Doctrine\ORM\EntityManager $em
     * @param boolean                     $with_revision Includes AClassContent which has scheduled revision
     *
     * @return array
     */
    public static function getScheduledAClassContentUpdates(EntityManager $em, $with_revision = false, $exclude_base_element = false)
    {
        $entities = array();
        foreach ($em->getUnitOfWork()->getScheduledEntityUpdates() as $entity) {
            if (false !== $tmp = self::getScheduledEntity($entity, $with_revision, $exclude_base_element)) {
                $entities[] = $tmp;
            }
        }

        return $entities;
    }

    private static function getScheduledEntity($entity, $with_revision, $exclude_base_element)
    {
        if ($entity instanceof AbstractClassContent &&
            (!$exclude_base_element || !$entity->isElementContent())) {
            return $entity;
        } elseif (true === $with_revision && $entity instanceof Revision) {
            if (!$exclude_base_element || !$entity->isElementContent()) {
                return $entity->getContent();
            }
        }

        return false;
    }

    /**
     * Returns an array of AbstractClassContent scheduled for deletions.
     *
     * @param \Doctrine\ORM\EntityManager $em
     *
     * @return array
     */
    public static function getSchedulesAClassContentDeletions(EntityManager $em, $exclude_base_element = false)
    {
        $entities = array();
        foreach ($em->getUnitOfWork()->getScheduledEntityDeletions() as $entity) {
            if ($entity instanceof AbstractClassContent &&
                (!$exclude_base_element || !$entity->isElementContent())) {
                $entities[] = $entity;
            }
        }

        return $entities;
    }

    /**
     * Returns an array of AbstractClassContent scheduled for insertions or updates.
     *
     * @param \Doctrine\ORM\EntityManager $em
     * @param boolean                     $with_revision Includes AClassContent which has scheduled revision
     *
     * @return array
     */
    public static function getScheduledAClassContentNotForDeletions(EntityManager $em, $with_revision = false)
    {
        return array_merge(self::getScheduledAClassContentInsertions($em, $with_revision), self::getScheduledAClassContentUpdates($em, $with_revision));
    }

    /**
     * Returns TRUE if a entity of $classname is scheduled for insertion or update.
     *
     * @param \Doctrine\ORM\EntityManager $em
     * @param string                      $classname
     *
     * @return boolean
     */
    public static function hasScheduledEntitiesNotForDeletions(EntityManager $em, $classname)
    {
        $entities = array_merge($em->getUnitOfWork()->getScheduledEntityInsertions(), $em->getUnitOfWork()->getScheduledEntityUpdates());

        foreach ($entities as $entity) {
            if (is_a($entity, $classname)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns TRUE is a \BackBee\NestedNode\Page is scheduled for insertion or update.
     *
     * @param \Doctrine\ORM\EntityManager $em
     *
     * @return boolean
     */
    public static function hasScheduledPageNotForDeletions(EntityManager $em)
    {
        return self::hasScheduledEntitiesNotForDeletions($em, '\BackBee\NestedNode\Page');
    }
}
