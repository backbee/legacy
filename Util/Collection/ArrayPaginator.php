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

namespace BackBee\Util\Collection;

use ArrayIterator;
use Countable;
use IteratorAggregate;

/**
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class ArrayPaginator implements Countable, IteratorAggregate
{
    private $collection;
    private $currentPage;

    public static function paginate(array $collection, $page = 1, $pageSize = 1)
    {
        return new ArrayPaginator($collection, $page, $pageSize);
    }

    public function __construct(array $collection, $page, $pageSize)
    {
        $this->currentPage = (int) $page;
        $this->collection = array_chunk($collection, (int) $pageSize, true);
    }

    /**
     * @return ArrayIterator
     */
    public function getIterator()
    {
        return new ArrayIterator($this->collection[$this->currentPage]);
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->collection);
    }

    public function getNextPageNumber()
    {
        if ($this->currentPage + 1 > ($this->count() - 1)) {
            return $this->count() - 1;
        }

        return $this->currentPage + 1;
    }

    public function getPreviousPageNumber()
    {
        if ($this->currentPage - 1 < 0) {
            return 0;
        }

        return $this->currentPage - 1;
    }

    public function isNextPage()
    {
        return !($this->currentPage + 1 > ($this->count() - 1));
    }

    public function isPreviousPage()
    {
        return !($this->currentPage - 1 < 0);
    }

    /**
     * @return int
     */
    public function getCurrentPageNumber()
    {
        return $this->currentPage;
    }
}
