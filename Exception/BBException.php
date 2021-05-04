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

namespace BackBee\Exception;

/**
 * BackBee parent class exception.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class BBException extends \Exception
{
    /**
     * Unknown error.
     *
     * @var int
     */
    const UNKNOWN_ERROR = 1000;

    /**
     * Invalid argument provided.
     *
     * @var int
     */
    const INVALID_ARGUMENT = 1001;

    /**
     * None BackBee application available.
     *
     * @var int
     */
    const MISSING_APPLICATION = 1002;

    /**
     * Invalid database connection.
     *
     * @var int
     */
    const INVALID_DB_CONNECTION = 1003;

    /**
     * Unknown context provided.
     *
     * @var int
     */
    const UNKNOWN_CONTEXT = 1004;

    /**
     * The default error code.
     *
     * @var int
     */
    protected $_code = self::UNKNOWN_ERROR;

    /**
     * The last source file before the exception thrown.
     *
     * @var string
     */
    private $_source;

    /**
     * The line of the source file where the exception thrown.
     *
     * @var int
     */
    private $_seek;

    /**
     * The error message.
     *
     * @var type
     */
    private $_message;

    /**
     * Class constructor.
     *
     * @param type       $message  The error message
     * @param type       $code     The error code
     * @param \Exception $previous Optional, the previous exception generated
     * @param type       $source   Optional, the last source file before the exception thrown
     * @param type       $seek     Optional, the line of the source file where the exception trown
     */
    public function __construct($message = "", $code = 0, \Exception $previous = null, $source = null, $seek = null)
    {
        if (0 !== $code) {
            $this->_code = $code;
        }

        parent::__construct($message, $this->_code, $previous);

        $this->_message = $message;

        $this->setSource($source)
                ->setSeek($seek);
    }

    /**
     * Returns the last source file before the exception thrown.
     *
     * @return string
     */
    public function getSource()
    {
        return $this->_source;
    }

    /**
     * Returns the line of the source file where the exception thrown.
     *
     * @return int
     */
    public function getSeek()
    {
        return $this->_seek;
    }

    /**
     * Sets the last source file before the exception thrown.
     *
     * @param string $source
     *
     * @return \BackBee\Exception\BBException
     */
    public function setSource($source)
    {
        $this->_source = $source;

        return $this->updateMessage();
    }

    /**
     * Sets the line of the source file where the exception thrown.
     *
     * @param type $seek
     *
     * @return \BackBee\Exception\BBException
     */
    public function setSeek($seek)
    {
        $this->_seek = $seek;

        return $this->updateMessage();
    }

    /**
     * Updates the error message according to the source and seek provided.
     *
     * @return \BackBee\Exception\BBException
     */
    private function updateMessage()
    {
        $this->message = $this->_message;

        if (null !== $this->_source && null !== $this->_seek) {
            $this->message .= sprintf(' in %s at %d.', $this->_source, $this->_seek);
        } elseif (null !== $this->_source) {
            $this->message .= sprintf(' : %s.', $this->_source);
        }

        return $this;
    }
}
