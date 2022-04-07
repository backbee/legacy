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

namespace BackBee\Logging\Appender;

use BackBee\Logging\Exception\LoggingException;
use BackBee\Logging\Formatter\FormatterInterface;
use BackBee\Logging\Formatter\Simple;

/**
 * @category    BackBee
 *
 * 
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class File implements AppenderInterface
{
    private $_fhandler = null;
    private $_formatter = null;

    public function __construct($options)
    {
        if (!array_key_exists('logfile', $options)) {
            throw new LoggingException('None log file specified');
        }

        $logfile = $options['logfile'];
        $dirname = dirname($logfile);
        $mode = array_key_exists('mode', $options) ? $options['mode'] : 'a';

        if (!is_dir($dirname) && !@mkdir($dirname, 0711, true)) {
            throw new LoggingException(sprintf('Unable to create log directory `%s`.', $dirname));
        }

        if (!$this->_fhandler = @fopen($logfile, $mode, false)) {
            throw new LoggingException(sprintf('Unable to open the file `%s` with mode `%s`.', $logfile, $mode));
        }

        $this->setFormatter(new Simple());
    }

    public function close()
    {
        if (is_resource($this->_fhandler)) {
            fclose($this->_fhandler);
        }
    }

    public function setFormatter(FormatterInterface $formatter)
    {
        $this->_formatter = $formatter;

        return $this;
    }

    public function write($event)
    {
        if (is_resource($this->_fhandler)) {
            $log = $this->_formatter->format($event);

            if (false === @fwrite($this->_fhandler, $log)) {
                throw new LoggingException('Unable to write log entry.');
            }
        }
    }
}
