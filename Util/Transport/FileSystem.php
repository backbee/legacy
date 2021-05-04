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
 * A local filesystem transport.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class FileSystem extends AbstractTransport
{
    /**
     * Class constructor, config should overwrite following option:
     * * remotepath.
     *
     * @param array $config
     *
     * @throws Exception\MisconfigurationException Occures if the remote path can not be created
     */
    public function __construct(array $config = null)
    {
        parent::__construct($config);

        if (null !== $this->_remotepath &&
                false === file_exists($this->_remotepath)) {
            if (false === @mkdir($this->_remotepath, 0755, true)) {
                throw new Exception\MisconfigurationException(sprintf('Can not create remote path %s', $this->_remotepath));
            }
        }
    }

    /**
     * @param string $host
     * @param string $port
     *
     * @return \BackBee\Util\Transport\FileSystem
     * @codeCoverageIgnore
     */
    public function connect($host = null, $port = null)
    {
        return $this;
    }

    /**
     * Tries to change dir to the defined remote path.
     * An error is triggered if failed.
     *
     * @param string $username
     * @param string $password
     *
     * @return \BackBee\Util\Transport\FileSystem
     *
     * @throws \BackBee\Util\Transport\Exception\AuthenticationException
     */
    public function login($username = null, $password = null)
    {
        if (false === @$this->cd()) {
            if (true === @$this->mkdir()) {
                @$this->cd();
            } else {
                throw new Exception\AuthenticationException(sprintf('Unable to change dir to %s', $this->_remotepath));
            }
        }

        return $this;
    }

    /**
     * Disconnects.
     *
     * @return \BackBee\Util\Transport\FileSystem
     * @codeCoverageIgnore
     */
    public function disconnect()
    {
        return $this;
    }

    /**
     * Change remote directory.
     *
     * @param string $dir
     *
     * @return boolean TRUE on success
     */
    public function cd($dir = null)
    {
        $dir = $this->_getAbsoluteRemotePath($dir);
        if (false === is_dir($dir)) {
            return $this->_trigger_error(sprintf('Unable to change remote directory to %s.', $dir));
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
     */
    public function ls($dir = null)
    {
        $dir = $this->_getAbsoluteRemotePath(null === $dir ? $this->_remotepath : $dir);
        if (false === $ls = @scandir($dir)) {
            return $this->_trigger_error(sprintf('Unable to list files of remote directory %s.', $dir));
        }

        return $ls;
    }

    /**
     * Returns the current remote path.
     *
     * @return string
     * @codeCoverageIgnore
     */
    public function pwd()
    {
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
     */
    public function send($local_file, $remote_file, $overwrite = false)
    {
        if (false === file_exists($local_file)) {
            return $this->_trigger_error(sprintf('Could not open local file: %s.', $local_file));
        }

        $remote_file = $this->_getAbsoluteRemotePath($remote_file);
        if (false === $overwrite && true === file_exists($remote_file)) {
            return $this->_trigger_error(sprintf('Remote file already exists: %s.', $remote_file));
        }

        if (false === file_exists(dirname($remote_file))) {
            @$this->mkdir(dirname($remote_file), true);
        }

        if (false === @copy($local_file, $remote_file)) {
            return $this->_trigger_error(sprintf('Could not send data from file %s to file %s.', $local_file, $remote_file));
        }

        return true;
    }

    /**
     * Copy recursively a local file and subfiles to the remote server.
     *
     * @param string  $local_file
     * @param string  $remote_file
     * @param boolean $overwrite
     *
     * @return boolean Returns TRUE on success or FALSE on error
     */
    public function sendRecursive($local_path, $remote_path, $overwrite = false)
    {
        if (false === is_dir($local_path)) {
            return $this->send($local_path, $remote_path, $overwrite);
        }

        if (false === $lls = @scandir($local_path)) {
            return $this->_trigger_error(sprintf('Unable to list files of local directory %s.', $local_path));
        }

        $remote_path = $this->_getAbsoluteRemotePath($remote_path);
        if (true === file_exists($remote_path)
                && false === is_dir($remote_path)) {
            return $this->_trigger_error(sprintf('A file named %s already exists, can\'t create folder.', $remote_path));
        } elseif (false === file_exists($remote_path)
                && false === $this->mkdir($remote_path, true)) {
            return false;
        }

        $currentpwd = $this->pwd();
        $this->cd($remote_path);
        foreach ($lls as $file) {
            if ($file != "." && $file != "..") {
                $this->sendRecursive($local_path.DIRECTORY_SEPARATOR.$file, $file, $overwrite);
            }
        }
        $this->cd($currentpwd);

        return true;
    }

    /**
     * Copy a remote file on local filesystem.
     *
     * @param string  $local_file
     * @param string  $remote_file
     * @param boolean $overwrite
     *
     * @return boolean Returns TRUE on success or FALSE on error
     */
    public function get($local_file, $remote_file, $overwrite = false)
    {
        if (false === $overwrite && true === file_exists($local_file)) {
            return $this->_trigger_error(sprintf('Local file already exists: %s.', $local_file));
        }

        $remote_file = $this->_getAbsoluteRemotePath($remote_file);
        if (false === file_exists($remote_file)) {
            return $this->_trigger_error(sprintf('Could not open remote file: %s.', $remote_file));
        }

        if (false === @copy($remote_file, $local_file)) {
            return $this->_trigger_error(sprintf('Could not send data from file %s to file %s.', $remote_file, $local_file));
        }

        return true;
    }

    public function getRecursive($local_path, $remote_path, $overwrite = false)
    {
        $remote_path = $this->_getAbsoluteRemotePath($remote_path);
        if (false === is_dir($remote_path)) {
            return $this->get($local_path, $remote_path, $overwrite);
        }

        if (false === file_exists($local_path)) {
            if (false === @mkdir($local_path, 0755, true)) {
                throw new TransportException(sprintf('Unable to create local folder %s.', $local_path));
            }
        } elseif (false === is_dir($local_path)) {
            throw new TransportException(sprintf('A file named %s already exist, can\'t create folder.', $local_path));
        }

        $currentpwd = $this->pwd();
        $this->cd($remote_path);
        foreach ($this->ls() as $file) {
            if ($file != "." && $file != "..") {
                $this->getRecursive($local_path.DIRECTORY_SEPARATOR.$file, $file, $overwrite);
            }
        }
        $this->cd($currentpwd);

        return true;
    }

    /**
     * Creates a new remote directory.
     *
     * @param string  $dir
     * @param boolean $recursive
     *
     * @return boolean Returns TRUE on success or FALSE on error
     */
    public function mkdir($dir, $recursive = false)
    {
        $dir = $this->_getAbsoluteRemotePath($dir);
        if (false === @mkdir($dir, 0777, $recursive)) {
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
     */
    public function delete($remote_path, $recursive = false)
    {
        $remote_path = $this->_getAbsoluteRemotePath($remote_path);
        if (true === @is_dir($remote_path)) {
            if (true === $recursive) {
                foreach ($this->ls($remote_path) as $file) {
                    if ('.' !== $file && '..' !== $file) {
                        $this->delete($remote_path.DIRECTORY_SEPARATOR.$file, $recursive);
                    }
                }
            }

            if (false === @rmdir($remote_path)) {
                return $this->_trigger_error(sprintf('Unable to delete directory %s', $remote_path));
            }
        } else {
            if (false === @unlink($remote_path)) {
                return $this->_trigger_error(sprintf('Unable to delete file %s', $remote_path));
            }
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
     */
    public function rename($old_name, $new_name)
    {
        $old_name = $this->_getAbsoluteRemotePath($old_name);
        $new_name = $this->_getAbsoluteRemotePath($new_name);

        if (false === file_exists($old_name)) {
            return $this->_trigger_error(sprintf('Could not open remote file: %s.', $old_name));
        }

        if (true === file_exists($new_name)) {
            return $this->_trigger_error(sprintf('Remote file already exists: %s.', $new_name));
        }

        if (false === @rename($old_name, $new_name)) {
            return $this->_trigger_error(sprintf('Unable to rename %s to %s', $old_name, $new_name));
        }

        return true;
    }
}
