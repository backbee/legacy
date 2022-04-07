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

use BackBee\Logging\Formatter\FormatterInterface;
use BackBee\Logging\Formatter\Simple;

/**
 * @category    BackBee
 *
 *
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class Output implements AppenderInterface
{
    private $_formatter = null;

    public function __construct($options)
    {
        $this->setFormatter(new Simple());
    }

    public function close()
    {
    }

    public function setFormatter(FormatterInterface $formatter)
    {
        $this->_formatter = $formatter;

        return $this;
    }

    public function write($event)
    {
        echo $this->_formatter->format($event);
    }
}
