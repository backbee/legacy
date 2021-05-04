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

namespace BackBee\Util\Transport;

use BackBee\Util\Transport\Exception\TransportException;

/**
 * SFTP transport
 * Openssl and libssh2 are required.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class SFTP extends AbstractTransport
{
    /**
     * The default port number.
     *
     * @var int
     */
    protected $_port = 22;

    /**
     * The SSH resource.
     *
     * @var resource
     */
    private $_ssh_resource = null;

    /**
     * The SFTP resource.
     *
     * @var resource
     */
    private $_sftp_resource = null;

    /**
     * Class constructor, config can overwrite following options:
     * * host
     * * port
     * * username
     * * password
     * * remotepath.
     *
     * @param array $config
     *
     * @throws \BackBee\Util\Transport\Exception\TransportException Occures if extensions OpenSSL or libssh2 are unavailable
     */
    public function __construct(array $config = array())
    {
        parent::__construct($config);
        if (false === extension_loaded('openssl')) {
            throw new TransportException('The SFTP transport requires openssl extension.');
        }

        if (false === function_exists('ssh2_connect')) {
            throw new TransportException('The SFTP transport requires libssh2 extension.');
        }
    }

    /**
     * Class destructor.
     */
    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * Establish a SSH connection.
     *
     * @param string $host
     * @param int    $port
     *
     * @return \BackBee\Util\Transport\SFTP
     *
     * @throws \BackBee\Util\Transport\Exception\TransportException Occurs if connection failed
     */
    public function connect($host = null, $port = null)
    {
        $this->_host = null !== $host ? $host : $this->_host;
        $this->_port = null !== $port ? $port : $this->_port;
        if (false === $this->_ssh_resource = ssh2_connect($this->_host, $this->_port)) {
            throw new TransportException(sprintf('Enable to connect to %s:%i.', $this->_host, $this->_port));
        }

        return $this;
    }

    /**
     * Authenticate on remote server.
     *
     * @param string $username
     * @param string $password
     *
     * @return \BackBee\Util\Transport\SFTP
     *
     * @throws \BackBee\Util\Transport\Exception\TransportException Occures if authentication failed
     */
    public function login($username = null, $password = null)
    {
        $this->_username = null !== $username ? $username : $this->_username;
        $this->_password = null !== $password ? $password : $this->_password;

        if (null === $this->_ssh_resource) {
            throw new TransportException(sprintf('None SSH connection available.'));
        }

        if (null === $this->_ssh_key_pub) {
            if (false === ssh2_auth_password($this->_ssh_resource, $this->_username, $this->_password)) {
                throw new TransportException(sprintf('Could not authenticate with username %s.', $this->_username));
            }
        } else {
            if (false === ssh2_auth_pubkey_file($this->_ssh_resource, $this->_username, $this->_ssh_key_pub, $this->_ssh_key_priv, $this->_ssh_key_pass)) {
                throw new TransportException(sprintf('Could not authenticate with keyfile %s.', $this->_keyfile));
            }
        }

        if (false === $this->_sftp_resource = ssh2_sftp($this->_ssh_resource)) {
            throw new TransportException("Could not initialize SFTP subsystem.");
        }

        return $this;
    }

    /**
     * Change remote directory.
     *
     * @param string $dir
     *
     * @return \BackBee\Util\Transport\SFTP
     *
     * @throws \BackBee\Util\Transport\Exception\TransportException Occures if SSH connection is invalid
     */
    public function cd($dir = null)
    {
        if (null === $this->_sftp_resource) {
            throw new TransportException(sprintf('None SSH connection available.'));
        }

        $dir = $this->_getAbsoluteRemotePath($dir);
        if (false === @ssh2_sftp_stat($this->_sftp_resource, $this->_remotepath)) {
            return $this->_trigger_error(sprintf('Unable to change remote directory to %s.', $this->_remotepath));
        }

        $this->_remotepath = $dir;

        return true;
    }

    /**
     * List remote files on $dir.
     *
     * @param string $dir
     *
     * @return array|FALSE
     *
     * @throws \BackBee\Util\Transport\Exception\TransportException Occures if SSH connection is invalid
     */
    public function ls($dir = null)
    {
        if (null === $this->_ssh_resource) {
            throw new TransportException(sprintf('None SSH connection available.'));
        }

        $dir = $this->_getAbsoluteRemotePath($dir);
        if ('' === $data = $this->exec('ls '.$dir)) {
            return $this->_trigger_error(sprintf('Unable to list remote directory to %s.', $dir));
        }

        $files = array();
        foreach (explode("\n", $data) as $file) {
            $files[] = $dir.'/'.$file;
        }

        return $files;
    }

    /**
     * Returns the current remote path.
     *
     * @return string
     *
     * @throws \BackBee\Util\Transport\Exception\TransportException Occures if SSH connection is invalid
     */
    public function pwd()
    {
        if (null === $this->_sftp_resource) {
            throw new TransportException(sprintf('None SSH connection available.'));
        }

        return $this->_remotepath;
    }

    /**
     * Copy a local file to the remote server.
     *
     * @param string  $local_file
     * @param string  $remote_file
     * @param boolean $overwrite
     *
     * @return boolean Returns TRUE on success or FALSE on error
     *
     * @throws \BackBee\Util\Transport\Exception\TransportException Occures if SSH connection is invalid
     */
    public function send($local_file, $remote_file, $overwrite = false)
    {
        if (null === $this->_sftp_resource
                || null === $this->_ssh_resource) {
            throw new TransportException(sprintf('None SSH connection available.'));
        }

        if (false === file_exists($local_file)) {
            return $this->_trigger_error(sprintf('Could not open local file: %s.', $local_file));
        }

        $remote_file = $this->_getAbsoluteRemotePath($remote_file);
        @ssh2_sftp_mkdir($this->_sftp_resource, dirname($remote_file), 0777, true);

        if (true === $overwrite || false === @ssh2_sftp_stat($this->_sftp_resource, $remote_file)) {
            if (false === @ssh2_scp_send($this->_ssh_resource, $local_file, $remote_file)) {
                return $this->_trigger_error(sprintf('Could not send data from file %s to file %s.', $local_file, $remote_file));
            }

            return true;
        }

        return $this->_trigger_error(sprintf('Remote file already exists: %s.', $remote_file));
    }

    /**
     * Copy recursively local files to the remote server.
     *
     * @param string  $local_path
     * @param string  $remote_path
     * @param boolean $overwrite
     *
     * @return boolean Returns TRUE on success or FALSE on error
     *
     * @throws \BackBee\Util\Transport\Exception\TransportException Occures if SSH connection is invalid
     */
    public function sendRecursive($local_path, $remote_path, $overwrite = false)
    {
        // @todo
        throw new TransportException(sprintf('Method not implemented yet.'));
    }

    /**
     * Copy a remote file on local filesystem.
     *
     * @param string  $local_file
     * @param string  $remote_file
     * @param boolean $overwrite
     *
     * @return boolean Returns TRUE on success or FALSE on error
     *
     * @throws \BackBee\Util\Transport\Exception\TransportException Occures if SSH connection is invalid
     */
    public function get($local_file, $remote_file, $overwrite = false)
    {
        if (null === $this->_sftp_resource
                || null === $this->_ssh_resource) {
            throw new TransportException(sprintf('None SSH connection available.'));
        }

        $remote_file = $this->_getAbsoluteRemotePath($remote_file);
        if (true === $overwrite || false === file_exists($local_file)) {
            if (false === @ssh2_sftp_stat($this->_sftp_resource, $remote_file)) {
                return $this->_trigger_error(sprintf('Could not open remote file: %s.', $remote_file));
            }

            if (false === @ssh2_scp_recv($this->_ssh_resource, $remote_file, $local_file)) {
                return $this->_trigger_error(sprintf('Could not send data from file %s to file %s.', $remote_file, $local_file));
            }

            return true;
        }

        return $this->_trigger_error(sprintf('Local file already exists: %s.', $local_file));
    }

    /**
     * Copy recursively remote files to the local filesystem.
     *
     * @param string  $local_path
     * @param string  $remote_path
     * @param boolean $overwrite
     *
     * @return boolean Returns TRUE on success or FALSE on error
     *
     * @throws \BackBee\Util\Transport\Exception\TransportException Occures if SSH connection is invalid
     */
    public function getRecursive($local_path, $remote_path, $overwrite = false)
    {
        // @todo
        throw new TransportException(sprintf('Method not implemented yet.'));
    }

    /**
     * Creates a new remote directory.
     *
     * @param string  $dir
     * @param boolean $recursive
     *
     * @return boolean Returns TRUE on success or FALSE on error
     *
     * @throws \BackBee\Util\Transport\Exception\TransportException Occures if SSH connection is invalid
     */
    public function mkdir($dir, $recursive = false)
    {
        if (null === $this->_sftp_resource) {
            throw new TransportException(sprintf('None SSH connection available.'));
        }

        $dir = $this->_getAbsoluteRemotePath($dir);
        if (false === @ssh2_sftp_mkdir($this->_sftp_resource, $dir, 0777, $recursive)) {
            return $this->_trigger_error(sprintf('Unable to make directory: %s.', $dir));
        }

        return true;
    }

    /**
     * Deletes a remote file.
     *
     * @param string  $remote_path
     * @param boolean $recursive
     *
     * @return boolean Returns TRUE on success or FALSE on error
     *
     * @throws \BackBee\Util\Transport\Exception\TransportException Occures if SSH connection is invalid
     */
    public function delete($remote_path, $recursive = false)
    {
        if (null === $this->_sftp_resource) {
            throw new TransportException(sprintf('None SSH connection available.'));
        }

        if (true === $recursive) {
            // @todo
            throw new TransportException(sprintf('REcursive option not implemented yet.'));
        }

        $remote_path = $this->_getAbsoluteRemotePath($remote_path);
        if (false === @ssh2_sftp_stat($this->_sftp_resource, $remote_path)) {
            return $this->_trigger_error(sprintf('Remote file to delete does not exist: %s.', $remote_path));
        }

        if (false === ssh2_sftp_unlink($this->_sftp_resource, $remote_path)) {
            return ssh2_sftp_rmdir($this->_sftp_resource, $remote_path);
        }

        return true;
    }

    /**
     * Renames a remote file.
     *
     * @param string $old_name
     * @param string $new_name
     *
     * @return boolean Returns TRUE on success or FALSE on error
     *
     * @throws \BackBee\Util\Transport\Exception\TransportException Occures if SSH connection is invalid
     */
    public function rename($old_name, $new_name)
    {
        if (null === $this->_sftp_resource) {
            throw new TransportException(sprintf('None SSH connection available.'));
        }

        $old_name = $this->_getAbsoluteRemotePath($old_name);
        $new_name = $this->_getAbsoluteRemotePath($new_name);

        if (false === @ssh2_sftp_stat($this->_sftp_resource, $old_name)) {
            return $this->_trigger_error(sprintf('Could not open remote file: %s.', $old_name));
        }

        if (false !== @ssh2_sftp_stat($this->_sftp_resource, $new_name)) {
            return $this->_trigger_error(sprintf('Remote file already exists: %s.', $new_name));
        }

        if (false === @ssh2_sftp_rename($this->_sftp_resource, $old_name, $new_name)) {
            return $this->_trigger_error(sprintf('Unable to rename %s to %s', $old_name, $new_name));
        }

        return true;
    }

    /**
     * Disconnect from the remote server and unset resources.
     *
     * @return \BackBee\Util\Transport\SFTP
     */
    public function disconnect()
    {
        if (null !== $this->_ssh_resource) {
            $this->exec('echo "EXITING" && exit;');
            $this->_ssh_resource = null;
            $this->_sftp_resource = null;
        }

        return $this;
    }

    /**
     * Executes a command on remote server.
     *
     * @param string $command
     *
     * @return string
     *
     * @throws \BackBee\Util\Transport\Exception\TransportException Occures if SSH connection is invalid
     */
    private function exec($command)
    {
        if (null === $this->_ssh_resource) {
            throw new TransportException(sprintf('None SSH connection available.'));
        }

        if (false === $stream = ssh2_exec($this->_ssh_resource, $command)) {
            throw new TransportException(sprintf('SSH command `%s` failed.', $command));
        }

        stream_set_blocking($stream, true);
        $data = "";
        while ($buf = fread($stream, 4096)) {
            $data .= $buf;
        }
        fclose($stream);

        return $data;
    }
}
