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

namespace BackBee\Session;

use BackBee\Config\Config;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;
use \SessionHandlerInterface;

/**
 * Allow to configure the session regarding the environment
 *
 * @author      Mickaël Andrieu <mickael.andrieu@lp-digital.fr>
 */
class SessionFactory
{
    protected $sessionConfig;
    protected $sessionStorage;
    protected $sessionHandler;

    public function __construct(Config $config, SessionStorageInterface $sessionStorage, SessionHandlerInterface $sessionHandler = null)
    {
        $this->sessionConfig = is_array($config->getSessionConfig()) ? $config->getSessionConfig() : [];
        $this->sessionStorage = $sessionStorage;
        $this->sessionHandler = $sessionHandler;
    }

    /**
     * @return Session
     */
    public function createSession()
    {
        if ($this->sessionStorage instanceof NativeSessionStorage) {
            $this->sessionStorage->setOptions($this->sessionConfig);

            if($this->sessionHandler !== null) {
                $this->sessionStorage->setSaveHandler($this->sessionHandler);
            }
        }

        $session = new Session($this->sessionStorage);
        $session->start();

        return $session;
    }
}
