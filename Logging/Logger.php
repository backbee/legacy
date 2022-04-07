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

namespace BackBee\Logging;

use BackBee\BBApplication;
use BackBee\Logging\Appender\AppenderInterface;
use BackBee\Logging\Exception\LoggingException;

use Doctrine\DBAL\Logging\DebugStack;
use Doctrine\DBAL\Logging\SQLLogger;
use Psr\Log\LoggerInterface;

/**
 * @category    BackBee
 *
 *
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class Logger extends DebugStack implements LoggerInterface, SQLLogger
{
    const ERROR = 1;
    const WARNING = 2;
    const NOTICE = 3;
    const INFO = 4;
    const DEBUG = 5;

    private $_application;
    private $_uniqid;
    private $_appenders;
    private $_level;
    private $_priorities;
    private $_priorities_name;
    private $_buffer;
    private $_start;

    public function __call($method, $args)
    {
        $priorityname = strtoupper($method);

        if (!($priority = array_search($priorityname, $this->_priorities))) {
            throw new LoggingException(sprintf('Unkown priority `%s`.', $priorityname));
        }

        if (0 == count($args)) {
            throw new LoggingException('None log message provided.');
        }

        $this->log($priority, $args[0], 0 < count($args) ? $args[1] : array());
    }

    public function __construct(BBApplication $application = null, AppenderInterface $appender = null)
    {
        if (null !== $application) {
            $this->_application = $application;
        }

        if (null !== $appender) {
            $this->addAppender($appender);
        }

        $this->_uniqid = uniqid('', true);

        $r = new \ReflectionClass($this);
        $this->_priorities_name = $r->getConstants();
        $this->_priorities = array_flip($this->_priorities_name);

        $this->setLevel(self::ERROR);

        if (null !== $this->_application) {
            if (null !== $config = $this->_application->getConfig()->getLoggingConfig()) {
                if ($this->_application->isDebugMode()) {
                    error_reporting(E_ALL);
                    $this->setLevel(Logger::DEBUG);
                } elseif (array_key_exists('level', $config)) {
                    $this->setLevel(strtoupper($config['level']));
                }

                if (array_key_exists('appender', $config)) {
                    $appenders = (array) $config['appender'];
                    foreach ($appenders as $appender) {
                        $this->addAppender(new $appender($config));
                    }
                }

                if (array_key_exists('mailto', $config)) {
                    $this->_mailto = $config['mailto'];
                }
            }
        }
    }

    public function __destruct()
    {
        if (null !== $this->_appenders) {
            foreach ($this->_appenders as $appender) {
                $appender->close();
            }
        }
    }

    /**
     * @codeCoverageIgnore
     * @param  \BackBee\Logging\Appender\AppenderInterface $appender
     *
     * @return \BackBee\Logging\Logger
     */
    public function addAppender(AppenderInterface $appender)
    {
        $this->_appenders[] = $appender;

        return $this;
    }

    public function log($level, $message, array $context = array())
    {
        if (null !== $this->_buffer) {
            $buffer = $this->_buffer;
            $this->_buffer = null;
            $this->log($level, $buffer);
        }

        if (0 == count($this->_appenders)) {
            throw new LoggingException('None appenders defined.');
        }

        if (array_key_exists(strtoupper($level), $this->_priorities_name)) {
            $level = $this->_priorities_name[strtoupper($level)];
        }

        if (!array_key_exists($level, $this->_priorities)) {
            throw new LoggingException(sprintf('Unkown priority level `%d`.', $level));
        }

        if ($level > $this->_level) {
            return;
        }

        if (isset($_SERVER['HTTP_HOST']) && isset($_SERVER['REQUEST_URI'])) {
            $message = sprintf("http://%s%s : %s", $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'], $message);
        } elseif (PHP_SAPI == 'cli') {
            if (isset($argv)) {
                $message = sprintf("%s : %s", implode(" ", $argv), $message);
            }
        }

        foreach ($this->_appenders as $appender) {
            $appender->write(array('d' => @date('Y/m/d H:i:s'),
                'p' => $this->_priorities[$level],
                'm' => $message,
                'u' => $this->_uniqid, ));
        }
    }

    public function setLevel($level)
    {
        if (!is_numeric($level)) {
            $r = new \ReflectionClass($this);
            $level = $r->getConstant($level);
        }

        if (!array_key_exists($level, $this->_priorities)) {
            throw new LoggingException(sprintf('Unkown priority level `%d`.', $level));
        }

        $this->_level = $level;

        return $this;
    }

    public function startQuery($sql, array $params = null, array $types = null)
    {
        if (self::DEBUG === $this->_level) {
            $this->_start = microtime(true);
            $this->_buffer = '[Doctrine] '.$sql;
        }
    }

    public function stopQuery()
    {
        if (self::DEBUG === $this->_level) {
            $buffer = $this->_buffer;
            $this->_buffer = null;
            $this->log(self::DEBUG, $buffer.' in '.(microtime(true) - $this->_start).'ms');
        }
    }

    /**
     * @codeCoverageIgnore
     *
     * @param type  $message
     * @param array $context
     */
    public function emergency($message, array $context = array())
    {
        $this->log(self::ERROR, $message, $context);
    }

    /**
     * @codeCoverageIgnore
     *
     * @param type  $message
     * @param array $context
     */
    public function alert($message, array $context = array())
    {
        $this->log(self::WARNING, $message, $context);
    }

    public function critical($message, array $context = array())
    {
        $this->log(self::ERROR, $message, $context);
    }

    /**
     * @codeCoverageIgnore
     *
     * @param string  $message
     * @param array $context
     */
    public function error($message, array $context = array())
    {
        $this->log(self::ERROR, $message, $context);
    }

    /**
     * @codeCoverageIgnore
     *
     * @param string  $message
     * @param array $context
     */
    public function warning($message, array $context = array())
    {
        $this->log(self::WARNING, $message, $context);
    }

    /**
     * @codeCoverageIgnore
     *
     * @param string  $message
     * @param array $context
     */
    public function notice($message, array $context = array())
    {
        $this->log(self::NOTICE, $message, $context);
    }

    /**
     * @codeCoverageIgnore
     *
     * @param string  $message
     * @param array $context
     */
    public function info($message, array $context = array())
    {
        $this->log(self::INFO, $message, $context);
    }

    /**
     * @codeCoverageIgnore
     *
     * @param string  $message
     * @param array $context
     */
    public function debug($message, array $context = array())
    {
        $this->log(self::DEBUG, $message, $context);
    }

    /**
     * Get priority name by its code.
     *
     * @param int $code
     *
     * @return string|null
     */
    protected function getPriorityName($code)
    {
        return isset($this->_priorities[$code]) ? $this->_priorities[$code] : null;
    }
}
