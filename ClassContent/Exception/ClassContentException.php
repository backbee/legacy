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

namespace BackBee\ClassContent\Exception;

use BackBee\Exception\BBException;

/**
 * ClassContent exceptions.
 *
 * Error codes defined are :
 *
 * * UNKNOWN_PROPERTY : the property does not exist for the content
 * * UNKNOWN_METHOD : the method does not exist for the content
 * * REVISION_OUTOFDATE : the revision is out of date
 * * REVISION_ORPHAN : the revision is orphan
 * * REVISION_UPTODATE : the revision is already up to date
 * * REVISION_CONFLICTED : the revision is on conflict
 * * REVISION_ADDED : the revision is aleready added
 * * UNMATCH_REVISION : the revision does not match the content
 * * REVISION_MISSING : none revision defined for the content
 * * REVISION_UNLOADED : the revision is unloaded
 * * MALFORMED_PARAM : the parameter is malformed
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class ClassContentException extends BBException
{
    /**
     * The property does not exist for the content.
     *
     * @var int
     */
    const UNKNOWN_PROPERTY = 3001;

    /**
     * The method does not exist for the content.
     *
     * @var int
     */
    const UNKNOWN_METHOD = 3002;

    /**
     * The revision is out of date.
     *
     * @var int
     */
    const REVISION_OUTOFDATE = 3003;

    /**
     * The revision is orphan (the content does not exist anymore).
     *
     * @var int
     */
    const REVISION_ORPHAN = 3004;

    /**
     * The revision is already up to date.
     *
     * @var int
     */
    const REVISION_UPTODATE = 3005;

    /**
     * The revision is conflicted.
     *
     * @var int
     */
    const REVISION_CONFLICTED = 3006;

    /**
     * The revision is already added.
     *
     * @var int
     */
    const REVISION_ADDED = 3007;

    /**
     * The revision does not match the content.
     *
     * @var int
     */
    const UNMATCH_REVISION = 3008;

    /**
     * None revision defined for the content.
     *
     * @var int
     */
    const REVISION_MISSING = 3009;

    /**
     * The revision is unloaded.
     *
     * @var int
     */
    const REVISION_UNLOADED = 3010;

    /**
     * The parameter is malformed.
     *
     * @var int
     */
    const MALFORMED_PARAM = 3011;
}
