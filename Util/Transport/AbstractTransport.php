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

namespace BackBee\Util\Transport;

/**
 * Abstract class for transport.
 *
 * @category    BackBee
 *
 *
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
abstract class AbstractTransport
{
    /**
     * The protocol used by transport.
     *
     * @var string
     */
    protected $_protocol;

    /**
     * The remose host.
     *
     * @var string
     */
    protected $_host;

    /**
     * The protocol port to be uses.
     *
     * @var int
     */
    protected $_port;

    /**
     * The login indetifier.
     *
     * @var string
     */
    protected $_username;

    /**
     * The passaword.
     *
     * @var string
     */
    protected $_password;

    /**
     * The default remote path.
     *
     * @var string
     */
    protected $_remotepath = '/';

    /**
     * The starting path e.g. the 'home' of the connection.
     *
     * @var string
     */
    protected $_startingpath;

    /**
     * The SSH public key.
     *
     * @var publickey
     */
    protected $_ssh_key_pub;

    /**
     * The SSH private key.
     *
     * @var privatekey
     */
    protected $_ssh_key_priv;

    /**
     * The SSH resource.
     *
     * @var passkey
     */
    protected $_ssh_key_pass;

    /**
     * Class constructor, config might overwrite following options:
     * * protocol
     * * host
     * * port
     * * username
     * * password
     * * remotepath.
     *
     * Should throw a \BackBee\Util\Transport\Exception\MisconfigurationException
     * on failure
     *
     * @param array $config
     */
    public function __construct(array $config = null)
    {
        if (null !== $config) {
            foreach ($config as $key => $value) {
                $property = '_'.$key;
                if (true === property_exists($this, $property)) {
                    $this->$property = $value;
                }
            }
        }
        $this->_startingpath = $this->_remotepath;
    }

    /**
     * Returns the absolute remote path of a file.
     *
     * @param string $path
     *
     * @return string
     */
    protected function _getAbsoluteRemotePath($path = null)
    {
        if (null === $path) {
            return $this->_startingpath;
        }

        $path = str_replace(DIRECTORY_SEPARATOR, '/', $path);
        if (null === $parse_url = @parse_url($path)) {
            $parse_url = array('path' => $path);
        }

        return ('/' === substr($parse_url['path'], 0, 1) ? $path : $this->_remotepath.'/'.$path);
    }

    /**
     * Trigger en PHP warning.
     *
     * @param string $message
     *
     * @return FALSE
     */
    protected function _trigger_error($message)
    {
        trigger_error($message, E_USER_WARNING);

        return false;
    }

    /**
     * Establish a new connection to the remose server.
     *
     * @param string $host
     * @param string $port
     *
     * @return \BackBee\Util\Transport\AbstractTransport
     * @throws \BackBee\Util\Transport\Exception\ConnectionException Occurs if connection failed
     */
    abstract public function connect($host = null, $port = null);

    /**
     * Authenticate on remote server.
     *
     * @param string $username
     * @param string $password
     *
     * @return \BackBee\Util\Transport\AbstractTransport
     * @throws \BackBee\Util\Transport\Exception\AuthenticationException Occurs if authentication failed
     */
    abstract public function login($username = null, $password = null);

    /**
     * Disconnect from the remote server and unset resources.
     *
     * @return \BackBee\Util\Transport\AbstractTransport
     */
    abstract public function disconnect();

    /**
     * Change remote directory
     * Should trigger an error on failure.
     *
     * @param string $dir
     *
     * @return boolean TRUE on success, FALSE on failure
     */
    abstract public function cd($dir = null);

    /**
     * List remote files.
     *
     * @param string $dir
     *
     * @return array|FALSE An array of files
     */
    abstract public function ls($dir = null);

    /**
     * Returns the current remote path.
     *
     * @return string
     */
    abstract public function pwd();

    /**
     * Copy a local file to the remote server
     * Should trigger an error on failure.
     *
     * @param string  $local_file
     * @param string  $remote_file
     * @param boolean $overwrite
     *
     * @return boolean Returns TRUE on success or FALSE on error
     */
    abstract public function send($local_file, $remote_file, $overwrite = false);

    /**
     * Copy recursively a local file and subfiles to the remote server
     * Should trigger an error on failure.
     *
     * @param string  $local_file
     * @param string  $remote_file
     * @param boolean $overwrite
     *
     * @return boolean Returns TRUE on success or FALSE on error
     */
    abstract public function sendRecursive($local_path, $remote_path, $overwrite = false);

    /**
     * Receive a remote file on local filesystem
     * Should trigger an error on failure.
     *
     * @param string  $local_file
     * @param string  $remote_file
     * @param boolean $overwrite
     *
     * @return boolean Returns TRUE on success or FALSE on error
     */
    abstract public function get($local_file, $remote_file, $overwrite = false);

    /**
     * Receive recursively a remote file and subfiles on local filesystem
     * Should trigger an error on failure.
     *
     * @param string  $local_file
     * @param string  $remote_file
     * @param boolean $overwrite
     *
     * @return boolean Returns TRUE on success or FALSE on error
     */
    abstract public function getRecursive($local_path, $remote_path, $overwrite = false);

    /**
     * Creates a new remote directory
     * Should trigger an error on failure.
     *
     * @param string  $dir
     * @param boolean $recursive
     *
     * @return boolean Returns TRUE on success or FALSE on error
     */
    abstract public function mkdir($dir, $recursive = false);

    /**
     * Deletes a remote file
     * Should trigger an error on failure.
     *
     * @param string  $remote_path
     * @param boolean $recursive
     *
     * @return boolean Returns TRUE on success or FALSE on error
     */
    abstract public function delete($remote_path, $recursive = false);

    /**
     * Renames a remote file
     * Should trigger an error on failure.
     *
     * @param string $old_name
     * @param string $new_name
     *
     * @return boolean Returns TRUE on success or FALSE on error
     */
    abstract public function rename($old_name, $new_name);
}
