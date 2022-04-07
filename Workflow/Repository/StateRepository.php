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

namespace BackBee\Workflow\Repository;

use Doctrine\ORM\EntityRepository;
use BackBee\Site\Layout;

/**
 * Workflow state repository.
 *
 * @category    BackBee
 *
 *
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class StateRepository extends EntityRepository
{
    /**
     * Returns an array of available workflow states for the provided layout.
     *
     * @param \BackBee\Site\Layout $layout
     *
     * @return array
     */
    public function getWorkflowStatesForLayout(Layout $layout)
    {
        $states = array();
        foreach ($this->findBy(array('_layout' => null)) as $state) {
            $states[$state->getCode()] = $state;
        }

        foreach ($this->findBy(array('_layout' => $layout)) as $state) {
            $states[$state->getCode()] = $state;
        }

        ksort($states);

        return $states;
    }

    /**
     * Returns an array of available workflow states associated layout.
     *
     * @return array
     */
    public function getWorkflowStatesWithLayout()
    {
        $query = $this->createQueryBuilder('w')
                      ->andWhere('w._layout IS NOT NULL');

        return $query->getQuery()->getResult();
    }
}
