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

namespace BackBee\NestedNode\Repository;

use Doctrine\ORM\EntityRepository;
use Exception;

use BackBee\NestedNode\Page;
use BackBee\NestedNode\PageRevision;

/**
 * Page revision repository.
 *
 * @category    BackBee
 *
 * 
 * @author      m.baptista <michel.baptista@lp-digital.fr>
 */
class PageRevisionRepository extends EntityRepository
{
    public function getCurrent(Page $page)
    {
        try {
            $q = $this->createQueryBuilder('r')
                    ->andWhere('r._page = :page')
                    ->andWhere('r._version = :version')
                    ->orderBy('r._id', 'DESC')
                    ->setParameters(array(
                        'page' => $page,
                        'version' => PageRevision::VERSION_CURRENT,
                    ))
                    ->getQuery();

            return $q->getSingleResult();
        } catch (\Doctrine\ORM\NoResultException $e) {
            return;
        } catch (Exception $e) {
            return;
        }
    }
}
