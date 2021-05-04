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

namespace BackBee\Logging;

use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;

/**
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      k.golovin
 */
class DebugStackLogger extends Logger implements DebugLoggerInterface
{
    protected $records = array();

    /**
     * Executed SQL queries.
     *
     * @var array
     */
    public $queries = array();

    /**
     * If Debug Stack is enabled (log queries) or not.
     *
     * @var boolean
     */
    public $enabled = true;

    /**
     * @var float|null
     */
    public $start = null;

    /**
     * @var integer
     */
    public $currentQuery = 0;

    /**
     * @inheritDoc
     */
    public function startQuery($sql, array $params = null, array $types = null)
    {
        if ($this->enabled) {
            $this->start = microtime(true);
            $this->queries[++$this->currentQuery] = array('sql' => $sql, 'params' => $params, 'types' => $types, 'executionMS' => 0);
        }

        parent::startQuery($sql, $params, $types);
    }

    /**
     * @inheritDoc
     */
    public function stopQuery()
    {
        if ($this->enabled) {
            $this->queries[$this->currentQuery]['executionMS'] = microtime(true) - $this->start;
        }
        parent::stopQuery();
    }

    /**
     * @inheritDoc
     */
    public function log($level, $message, array $context = array())
    {
        parent::log($level, $message, $context);

        $this->records[] = array(
            'message' => $message,
            'priority' => $level,
            'priorityName' => $this->getPriorityName($level) ? $this->getPriorityName($level) : $level,
            'context'      => isset($context['name']) ? $context['name'] : 'default',
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getLogs()
    {
        return $this->records;
    }

    /**
     * @inheritDoc
     */
    public function countErrors()
    {
        return count($this->records);
    }
}
