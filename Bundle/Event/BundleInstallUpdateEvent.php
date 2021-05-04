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

namespace BackBee\Bundle\Event;

use BackBee\Bundle\BundleInterface;
use BackBee\Event\Event;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class BundleInstallUpdateEvent extends Event
{
    private $bundle;
    private $forced;
    private $logs;

    /**
     * Creates an instance of BundleInstallUpdateEvent.
     *
     * @param  BundleInterface $target
     * @param  mixed           $eventArgs
     * @throws \InvalidArgumentException if the provided target does not implement BackBee\Bundle\BundleInterface
     */
    public function __construct($target, $eventArgs = null)
    {
        if (!($target instanceof BundleInterface)) {
            throw new \InvalidArgumentException(
                'Target of bundle update or action event must be instance of BackBee\Bundle\BundleInterface'
            );
        }

        parent::__construct($target, $eventArgs);
        $this->bundle = $target;
        $this->forced = isset($eventArgs['force']) ? (boolean) $eventArgs['force'] : false;
        $this->logs = isset($eventArgs['logs']) ? (array) $eventArgs['logs'] : [];
    }

    /**
     * Returns the bundle which is updating.
     *
     * @return
     */
    public function getBundle()
    {
        return $this->bundle;
    }

    /**
     * Returns true if current update action is forced, else false.
     *
     * @return boolean
     */
    public function isForced()
    {
        return $this->forced;
    }

    /**
     * Adds new message to logs.
     *
     * @param string $key
     * @param string $message
     * @return self
     * @throws \InvalidArgumentException if message argument is not type of string
     */
    public function addLog($key, $message)
    {
        if (!is_string($message)) {
            throw new \InvalidArgumentException(sprintf(
                '[%s]: "message" must be type of string, %s given.',
                __METHOD__,
                gettype($message)
            ));
        }

        if (!isset($this->logs[$key])) {
            $this->logs[$key] = [];
        }

        $this->logs[$key][] = $message;

        return $this;
    }

    /**
     * Returns bundle update logs.
     *
     * @return array
     */
    public function getLogs()
    {
        return $this->logs;
    }
}
