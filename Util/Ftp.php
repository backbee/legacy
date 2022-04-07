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

namespace BackBee\Util;

/**
 * FTP Utils.
 *
 * @category    BackBee
 *
 *
 * @author      k.golovin
 */
class Ftp
{
    protected $params = array(
        'user' => 'anonymous',
        'pass' => null,
        'path' => '/',
        'timeout' => 90,
        'port' => 21,

        'retryAttempts' => 0,
        // sleep between retries, in seconds
        'retrySleep' => 0,
        'passive_mode' => true,
        'transfer_mode' => \FTP_BINARY,
        'create_mask' => 0777,
    );

    protected $connection;

    /**
     * @param type  $url
     * @param array $params
     *
     * @throws \InvalidArgumentException
     */
    public function __construct($url, array $params = array())
    {
        if (0 === strpos($url, 'ftp://') && 0 === strpos($url, 'ftps://')) {
            throw new \InvalidArgumentException("Supplied url must have ftp or ftps scheme: ".$url);
        }

        $parsedUrl = parse_url($url);

        if (!isset($parsedUrl['host'])) {
            throw new \InvalidArgumentException("Invalid url provided: host missing");
        }

        $this->params = array_merge($this->params, $params, $parsedUrl);
    }

    /**
     * Get parameter.
     *
     * @param string $name
     *
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    protected function getParam($name)
    {
        if (!isset($this->params[$name])) {
            throw new \InvalidArgumentException("Param is not valid: ".$name);
        }

        return $this->params[$name];
    }

    /**
     * Connect to server.
     *
     * @return self
     */
    public function connect()
    {
        $this->connection = $this->doConnect();
        $this->doLogin();
        $this->doPasv();

        return $this;
    }

    /**
     * Wrapper for ftp_connect().
     *
     * @return boolean
     *
     * @throws \RuntimeException
     */
    protected function doConnect()
    {
        $host = $this->getParam('host');
        $port = $this->getParam('port');
        $timeout = $this->getParam('timeout');

        return $this->retry(function () use ($host, $port, $timeout) {
            $res = @ftp_connect($host, $port, $timeout);

            if (false === $res) {
                throw new \RuntimeException("Couldn't connect to: $host:$port");
            }

            return $res;
        });
    }

    /**
     * Wrapper for ftp_login().
     *
     * @return type void
     *
     * @throws \RuntimeException
     */
    protected function doLogin()
    {
        $connection = $this->connection;
        $user = $this->getParam('user');
        $password = $this->getParam('pass');

        return $this->retry(function () use ($connection, $user, $password) {
            $res = @ftp_login($connection, $user, $password);

            if (!$res) {
                throw new \RuntimeException("Login failed for user: ".$user);
            }
        });
    }

    /**
     * @return type void
     *
     * @throws \RuntimeException
     */
    protected function doPasv()
    {
        $connection = $this->connection;
        $passiveMode = $this->getParam('passive_mode');

        return $this->retry(function () use ($connection, $passiveMode) {
            $res = @ftp_pasv($connection, $passiveMode);

            if (false === $res) {
                throw new \RuntimeException("Couldn't set passive mode");
            }
        });
    }

    /**
     * Conditionally retry a closure if it yields an exception.
     *
     * @param \Closure $retry
     *
     * @return mixed
     */
    protected function retry(\Closure $retry)
    {
        $numRetries = $this->getParam('retryAttempts');
        $sleepBetweenRetries = $this->getParam('retrySleep');
        if ($numRetries < 1) {
            return $retry();
        }

        $firstException = null;

        for ($i = 0; $i <= $numRetries; $i++) {
            try {
                return $retry();
            } catch (\Exception $e) {
                if ($firstException === null) {
                    $firstException = $e;
                }

                if ($i === $numRetries) {
                    $ex = new \Exception($firstException->getMessage().', retry count: '.$i);
                    throw $ex;
                }

                if ($sleepBetweenRetries > 0) {
                    usleep(1000 * $sleepBetweenRetries);
                }

                $this->reconnect();
            }
        }
    }

    /**
     * Wrapper for ftp_get().
     *
     * @param string $sourceFile
     * @param string $destinationFile
     */
    protected function doGet($sourceFile, $destinationFile)
    {
        $connection = $this->connection;
        $ftpMode = $this->getParam('transfer_mode');
        $createMask = $this->getParam('create_mask');

        return $this->retry(function () use ($connection, $destinationFile, $sourceFile, $ftpMode, $createMask) {
            $localDir = dirname($destinationFile);

            if (!file_exists($localDir)) {
                mkdir($localDir, $createMask, true);
            }

            $res = @ftp_get($connection, $destinationFile, $sourceFile, $ftpMode);

            if (false === $res) {
                throw new \RuntimeException("Couldn't copy file: ".$sourceFile);
            }
        });
    }

    /**
     * Wrapper fo ftp_cdup().
     *
     * @throws \RuntimeException
     */
    protected function doCdUp()
    {
        $connection = $this->connection;

        return $this->retry(function () use ($connection) {
            $res = @ftp_cdup($connection);

            if (false === $res) {
                throw new \RuntimeException("Cdup failed");
            }
        });
    }

    /**
     * Wrapper for ftp_rawlist().
     *
     * @param type $dir
     * @param type $recursive
     *
     * @return type
     */
    protected function doRawList($dir, $recursive = false)
    {
        $connection = $this->connection;

        return $this->retry(function () use ($connection, $dir, $recursive) {
            $res = @ftp_rawlist($connection, $dir, $recursive);

            if (false === $res) {
                throw new \RuntimeException("Rawlist failed: ".$dir);
            }

            $items = array();

            foreach ($res as $child) {
                $chunks = preg_split("/\s+/", $child);
                list($item['rights'], $item['number'], $item['user'], $item['group'], $item['size'], $item['month'], $item['day'], $item['time']) = $chunks;
                $item['type'] = $chunks[0]{0}
                === 'd' ? 'directory' : 'file';
                array_splice($chunks, 0, 8);
                $item['name'] = implode(" ", $chunks);
                $item['pathname'] = \rtrim($dir, '/').'/'.$item['name'];
                $items[] = $item;
            }

            return $items;
        });
    }

    public function copy($from, $to)
    {
        if (!file_exists($to)) {
            $res = mkdir($to, $this->getParam('create_mask'), true);

            if (false === $res) {
                throw new \RuntimeException("Cannot create local directory: $to");
            }
        }

        foreach ($this->doRawList($from) as $file) {
            $localName = $to.'/'.$file['name'];

            if ('file' === $file['type']) {
                $this->doGet($file['pathname'], $localName);
            } elseif ('directory' === $file['type']) {
                if (!file_exists($localName)) {
                    mkdir($localName, $this->getParam('create_mask'), true);
                }
                $this->copy($file['pathname'], $localName);
            }
        }
    }

    /**
     * Change current working directory.
     *
     * @param   string  dir name
     *
     * @return bool
     */
    protected function doChangeDir($dir)
    {
        $connection = $this->connection;

        return $this->retry(function () use ($connection, $dir) {
            $res = @ftp_chdir($connection, $dir);

            if (false === $res) {
                throw new \RuntimeException("Changing directory failed: ".$dir);
            }
        });
    }

    /**
     * Check if is dir.
     *
     * @param   string  path to folder
     *
     * @return bool
     */
    protected function isDir($dir)
    {
        try {
            $this->doChangeDir($dir);
            $this->doCdUp();

            return true;
        } catch (\Exception $ex) {
            return false;
        }

        return false;
    }

    /**
     * Reconnecrt.
     */
    protected function reconnect()
    {
        $this->disconnect();
        $this->connect();
    }

    /**
     * Close ftp connection.
     *
     * @return self
     */
    public function disconnect()
    {
        for ($i = 0; $i < $this->getParam('retryAttempts') + 1; $i++) {
            $res = @ftp_close($this->connection);

            if (false !== $res) {
                $this->connection = null;
                break;
            }
        }

        return $this;
    }
}
