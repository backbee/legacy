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

use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * Settable paginator.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class SettablePaginator extends Paginator
{
    /**
     * @var int
     */
    private $_count;

    /**
     * @var array
     */
    private $_result;

    /**
     * Sets the number of results.
     *
     * @param int $count
     *
     * @return \BackBee\Util\Doctrine\SettablePaginator
     */
    public function setCount($count)
    {
        $this->_count = $count;

        return $this;
    }

    /**
     * Sets the first set of results.
     *
     * @param array $result
     *
     * @return \BackBee\Util\Doctrine\SettablePaginator
     */
    public function setResult(array $result)
    {
        $this->_result = $result;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        if (null === $this->_count) {
            return parent::count();
        }

        return $this->_count;
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        if (null === $this->_result) {
            return parent::getIterator();
        }

        return new \ArrayIterator($this->_result);
    }
}
