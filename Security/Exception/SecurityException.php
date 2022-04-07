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

namespace BackBee\Security\Exception;

use BackBee\Exception\BBException;

/**
 * Security Exception.
 *
 * Error codes defined are :
 *
 * * UNSUPPORTED_TOKEN : the provided token is not supported
 * * UNKNOWN_USER : the provided username does not match a UserInterface instance
 * * INVALID_CREDENTIALS : the provided credentials do not match
 * * INVALID_KEY : the provided key does not match the firewall key
 * * EXPIRED_AUTH : authentication expired
 * * EXPIRED_TOKEN : token expired
 * * FORBIDDEN_ACCESS : user does not have enough privileges
 *
 * @category    BackBee
 *
 *
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class SecurityException extends BBException
{
    /**
     * The provided token is not supported.
     *
     * @var int
     */
    const UNSUPPORTED_TOKEN = 9001;

    /**
     * The provided username does not match a UserInterface instance.
     *
     * @var int
     */
    const UNKNOWN_USER = 9002;

    /**
     * The provided credentials do not match.
     *
     * @var int
     */
    const INVALID_CREDENTIALS = 9003;

    /**
     * The provided key does not match the firewall key.
     *
     * @var int
     */
    const INVALID_KEY = 9004;

    /**
     * Authentication expired.
     *
     * @var int
     */
    const EXPIRED_AUTH = 9005;

    /**
     * Token expired.
     *
     * @var int
     */
    const EXPIRED_TOKEN = 9006;

    /**
     * User does not have enough privileges.
     *
     * @var int
     */
    const FORBIDDEN_ACCESS = 9007;
}
