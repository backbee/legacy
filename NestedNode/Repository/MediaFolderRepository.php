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

namespace BackBee\NestedNode\Repository;

use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\Revision;

use Exception;

/**
 * Media folder repository.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      m.baptista <michel.baptista@lp-digital.fr>
 */
class MediaFolderRepository extends NestedNodeRepository
{

    public function getRoot()
    {
        try {
            $q = $this->createQueryBuilder('mf')
                    ->andWhere('mf._parent is null')
                    ->getQuery();

            return $q->getSingleResult();
        } catch (\Doctrine\ORM\NoResultException $e) {
            return;
        } catch (Exception $e) {
            return;
        }
    }

    public function getMediaFolders($parent, $orderInfos, $paging = array())
    {
        $qb = $this->createQueryBuilder("mf");
        $qb->andParentIs($parent);

        /* order */
        if(is_array($orderInfos)) {
            if (array_key_exists("field", $orderInfos) && array_key_exists("dir", $orderInfos)) {
                 $qb->orderBy("mf.".$orderInfos["field"], $orderInfos["dir"]);
            }
        }
        /* paging */
        if (is_array($paging) && !empty($paging)) {
           if (array_key_exists("start", $paging) && array_key_exists("limit", $paging)) {
               $qb->setFirstResult($paging["start"])
                       ->setMaxResults($paging["limit"]);
               $result = new \Doctrine\ORM\Tools\Pagination\Paginator($qb);
           }
       } else {
           $result = $qb->getQuery()->getResult();
       }
       return $result;
    }

    /**
     * Get media folder by levels.
     *
     * @param $levels
     * @return array
     */
    public function getMediaFolderByLevels($levels)
    {
        $qb = $this->createQueryBuilder('mf')
                   ->andWhere('mf._level IN (:levels)')
                   ->setParameter('levels', $levels)
                   ->orderBy('mf._leftnode', 'asc');

        return $qb->getQuery()->getResult();
    }
}
